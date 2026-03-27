<?php

namespace App\Services;

use App\Jobs\AnalyzePlateJob;
use App\Models\Recommendation;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class QueueService
{
    /**
     * Dispatch a recommendation analysis job
     */
    public function dispatchRecommendationAnalysis(Recommendation $recommendation): bool
    {
        try {
            // Check if there's already a job running for this recommendation
            if ($this->isJobAlreadyRunning($recommendation)) {
                Log::info('Job already running for recommendation', [
                    'recommendation_id' => $recommendation->id,
                    'user_id' => $recommendation->user_id,
                    'plat_id' => $recommendation->plat_id
                ]);
                return false;
            }

            // Update recommendation status to processing
            $recommendation->update([
                'status' => 'processing',
                'score' => null,
                'label' => null,
                'warning_message' => null
            ]);

            // Dispatch the job
            $job = new AnalyzePlateJob($recommendation);

            // Set queue name and priority if needed
            $job->onQueue('recommendations');

            Queue::push($job);

            Log::info('Recommendation job dispatched successfully', [
                'recommendation_id' => $recommendation->id,
                'user_id' => $recommendation->user_id,
                'plat_id' => $recommendation->plat_id,
                'queue' => 'recommendations'
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to dispatch recommendation job', [
                'recommendation_id' => $recommendation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Reset status on failure
            $recommendation->update(['status' => 'ready']);

            return false;
        }
    }

    /**
     * Check if a job is already running for this recommendation
     */
    private function isJobAlreadyRunning(Recommendation $recommendation): bool
    {
        // Check in the jobs table
        $jobExists = DB::table('jobs')
            ->where('queue', 'recommendations')
            ->where('payload', 'like', '%"' . $recommendation->id . '"%')
            ->exists();

        // Also check if recommendation is already processing
        $isProcessing = $recommendation->status === 'processing' &&
            $recommendation->created_at->diffInMinutes(now()) < 5; // 5 minutes timeout

        return $jobExists || $isProcessing;
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats(): array
    {
        return [
            'pending_jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'recommendations_queue' => DB::table('jobs')->where('queue', 'recommendations')->count(),
            'processing_recommendations' => Recommendation::where('status', 'processing')->count(),
            'ready_recommendations' => Recommendation::where('status', 'ready')->count(),
        ];
    }

    /**
     * Get failed recommendation jobs
     */
    public function getFailedRecommendationJobs(): array
    {
        return DB::table('failed_jobs')
            ->where('queue', 'recommendations')
            ->orderBy('failed_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'exception' => $job->exception,
                    'failed_at' => $job->failed_at,
                    'recommendation_id' => $payload['data']['command'] ?? 'unknown'
                ];
            })
            ->toArray();
    }

    /**
     * Retry failed recommendation jobs
     */
    public function retryFailedJobs(int $limit = 5): int
    {
        $failedJobs = DB::table('failed_jobs')
            ->where('queue', 'recommendations')
            ->orderBy('failed_at', 'desc')
            ->limit($limit)
            ->get();

        $retriedCount = 0;

        foreach ($failedJobs as $failedJob) {
            try {
                // Use Laravel's built-in retry mechanism
                $this->retryFailedJob($failedJob->uuid);
                $retriedCount++;

                Log::info('Failed recommendation job retried', [
                    'uuid' => $failedJob->uuid
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to retry job', [
                    'uuid' => $failedJob->uuid,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $retriedCount;
    }

    /**
     * Clear old completed recommendations
     */
    public function clearOldRecommendations(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);

        $deletedCount = Recommendation::where('status', 'ready')
            ->where('updated_at', '<', $cutoffDate)
            ->delete();

        Log::info('Old recommendations cleared', [
            'days_old' => $daysOld,
            'deleted_count' => $deletedCount
        ]);

        return $deletedCount;
    }

    /**
     * Force process a specific recommendation (sync execution)
     */
    public function forceProcessRecommendation(Recommendation $recommendation): bool
    {
        try {
            Log::info('Force processing recommendation', [
                'recommendation_id' => $recommendation->id
            ]);

            $job = new AnalyzePlateJob($recommendation);
            $job->handle();

            Log::info('Recommendation processed successfully', [
                'recommendation_id' => $recommendation->id,
                'status' => $recommendation->fresh()->status,
                'score' => $recommendation->fresh()->score
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to force process recommendation', [
                'recommendation_id' => $recommendation->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get recommendations stuck in processing state
     */
    public function getStuckRecommendations(int $minutesThreshold = 10): array
    {
        return Recommendation::where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes($minutesThreshold))
            ->with(['user', 'plate'])
            ->get()
            ->map(function ($rec) {
                return [
                    'id' => $rec->id,
                    'user_id' => $rec->user_id,
                    'user_name' => $rec->user->name ?? 'Unknown',
                    'plat_id' => $rec->plat_id,
                    'plat_name' => $rec->plate->name ?? 'Unknown',
                    'created_at' => $rec->created_at,
                    'stuck_minutes' => $rec->updated_at->diffInMinutes(now())
                ];
            })
            ->toArray();
    }

    /**
     * Reset stuck recommendations
     */
    public function resetStuckRecommendations(int $minutesThreshold = 10): int
    {
        $stuckCount = Recommendation::where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes($minutesThreshold))
            ->update(['status' => 'ready']);

        Log::info('Stuck recommendations reset', [
            'minutes_threshold' => $minutesThreshold,
            'reset_count' => $stuckCount
        ]);

        return $stuckCount;
    }

    /**
     * Retry a specific failed job by UUID
     */
    private function retryFailedJob(string $uuid): void
    {
        $failedJob = DB::table('failed_jobs')->where('uuid', $uuid)->first();

        if ($failedJob) {
            // Re-insert the job into the jobs table
            DB::table('jobs')->insert([
                'queue' => $failedJob->queue,
                'payload' => $failedJob->payload,
                'attempts' => $failedJob->attempts,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => $failedJob->created_at
            ]);

            // Remove from failed jobs
            DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        }
    }
}
