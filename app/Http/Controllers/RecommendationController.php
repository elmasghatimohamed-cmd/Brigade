<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Recommendation;
use App\Models\Plat;
use App\Services\QueueService;

class RecommendationController extends Controller
{
    protected QueueService $queueService;

    public function __construct(QueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    public function analyze($plateId)
    {
        $plate = Plat::findOrFail($plateId);

        $recommendation = Recommendation::firstOrCreate(
            [
                'user_id' => auth()->id(),
                'plat_id' => $plateId
            ],
            [
                'status' => 'ready',
                'score' => null,
                'label' => null,
                'warning_message' => null
            ]
        );

        // Use QueueService to dispatch the job
        $dispatched = $this->queueService->dispatchRecommendationAnalysis($recommendation);

        if (!$dispatched) {
            return response()->json([
                'message' => 'Analysis already running or failed to dispatch',
                'recommendation_id' => $recommendation->id,
                'status' => $recommendation->status
            ], 409);
        }

        return response()->json([
            'message' => 'Analysis started successfully',
            'recommendation_id' => $recommendation->id,
            'queue_stats' => $this->queueService->getQueueStats()
        ]);
    }

    public function getHistory()
    {
        $recommendations = Recommendation::where('user_id', auth()->id())
            ->with('plate')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($recommendations);
    }

    public function getByPlate($plateId)
    {
        $recommendation = Recommendation::where('user_id', auth()->id())
            ->where('plat_id', $plateId)
            ->with('plate')
            ->first();

        if (!$recommendation) {
            return response()->json([
                'message' => 'No recommendation found for this plate'
            ], 404);
        }

        return response()->json($recommendation);
    }

    /**
     * Get queue statistics (admin only)
     */
    public function getQueueStats()
    {
        // Add admin middleware check if needed
        $stats = $this->queueService->getQueueStats();

        return response()->json([
            'queue_statistics' => $stats,
            'failed_jobs' => $this->queueService->getFailedRecommendationJobs(),
            'stuck_recommendations' => $this->queueService->getStuckRecommendations()
        ]);
    }

    /**
     * Force process a recommendation (admin/debug endpoint)
     */
    public function forceProcess($recommendationId)
    {
        $recommendation = Recommendation::findOrFail($recommendationId);

        // Ensure user can only process their own recommendations (or admin check)
        if ($recommendation->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $success = $this->queueService->forceProcessRecommendation($recommendation);

        if ($success) {
            return response()->json([
                'message' => 'Recommendation processed successfully',
                'recommendation' => $recommendation->fresh()
            ]);
        }

        return response()->json([
            'message' => 'Failed to process recommendation'
        ], 500);
    }

    /**
     * Retry failed jobs (admin only)
     */
    public function retryFailedJobs(Request $request)
    {
        $limit = $request->get('limit', 5);
        $retriedCount = $this->queueService->retryFailedJobs($limit);

        return response()->json([
            'message' => "Retried {$retriedCount} failed jobs",
            'retried_count' => $retriedCount
        ]);
    }

    /**
     * Reset stuck recommendations (admin only)
     */
    public function resetStuckRecommendations(Request $request)
    {
        $minutesThreshold = $request->get('minutes_threshold', 10);
        $resetCount = $this->queueService->resetStuckRecommendations($minutesThreshold);

        return response()->json([
            'message' => "Reset {$resetCount} stuck recommendations",
            'reset_count' => $resetCount,
            'minutes_threshold' => $minutesThreshold
        ]);
    }

    /**
     * Clear old recommendations (admin only)
     */
    public function clearOldRecommendations(Request $request)
    {
        $daysOld = $request->get('days_old', 30);
        $deletedCount = $this->queueService->clearOldRecommendations($daysOld);

        return response()->json([
            'message' => "Cleared {$deletedCount} old recommendations",
            'deleted_count' => $deletedCount,
            'days_old' => $daysOld
        ]);
    }
}
