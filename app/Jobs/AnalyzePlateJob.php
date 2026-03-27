<?php

namespace App\Jobs;

use App\Models\Recommendation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyzePlateJob implements ShouldQueue
{
    use Queueable;

    public Recommendation $recommendation;

    public function __construct(Recommendation $recommendation)
    {
        $this->recommendation = $recommendation;
    }

    public function handle()
    {
        try {
            $user = $this->recommendation->user;
            $plate = $this->recommendation->plate;

            $userTags = $user->dietary_tags ?? [];
            $ingredients = $plate->ingredients->pluck('name')->toArray();
            $plateData = [
                'name' => $plate->name,
                'description' => $plate->description,
                'price' => $plate->price,
                'category' => $plate->category ? $plate->category->name : null,
                'ingredients' => $ingredients
            ];

            $aiResponse = $this->callHuggingFaceAPI($userTags, $plateData);
            
            $score = $aiResponse['score'] ?? $this->calculateFallbackScore($userTags, $ingredients);
            $label = $this->getLabel($score);
            $warning = $aiResponse['warning_message'] ?? ($score < 50 ? 'This plate may not be suitable for your dietary profile' : null);

            $this->recommendation->update([
                'score' => $score,
                'label' => $label,
                'warning_message' => $warning,
                'status' => 'ready'
            ]);

        } catch (\Exception $e) {
            Log::error('Recommendation analysis failed: ' . $e->getMessage());
            
            $fallbackScore = $this->calculateFallbackScore(
                $this->recommendation->user->dietary_tags ?? [],
                $this->recommendation->plate->ingredients->pluck('name')->toArray()
            );
            
            $this->recommendation->update([
                'score' => $fallbackScore,
                'label' => $this->getLabel($fallbackScore),
                'warning_message' => 'Analysis completed with basic scoring',
                'status' => 'ready'
            ]);
        }
    }

    private function callHuggingFaceAPI($userTags, $plateData)
    {
        $apiKey = env('HUGGINGFACE_API_KEY');
        $apiUrl = env('HUGGINGFACE_API_URL');

        if (!$apiKey || !$apiUrl) {
            throw new \Exception('HuggingFace API credentials not configured');
        }

        $prompt = $this->buildPrompt($userTags, $plateData);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post($apiUrl, [
            'inputs' => $prompt,
            'parameters' => [
                'max_new_tokens' => 150,
                'temperature' => 0.7,
                'return_full_text' => false
            ]
        ]);

        if (!$response->successful()) {
            throw new \Exception('HuggingFace API request failed: ' . $response->body());
        }

        return $this->parseAIResponse($response->json());
    }

    private function buildPrompt($userTags, $plateData)
    {
        $userProfile = implode(', ', $userTags);
        $ingredients = implode(', ', $plateData['ingredients']);
        
        return "Hello! I need you to analyze food compatibility based on user dietary preferences.

User Dietary Profile: {$userProfile}
Plate Information:
- Name: {$plateData['name']}
- Description: {$plateData['description']}
- Category: {$plateData['category']}
- Price: {$plateData['price']}
- Ingredients: {$ingredients}

Please analyze this compatibility and provide:
1. A score from 0-100
2. A brief explanation if score < 50%

Respond in JSON format:
{
    \"score\": 85,
    \"warning_message\": \"Optional warning if score < 50\"
}

Consider:
- Dietary restrictions (vegan, gluten-free, no-sugar, etc.)
- Ingredient compatibility
- Health implications
- Allergen concerns";
    }

    private function parseAIResponse($response)
    {
        if (!isset($response[0]['generated_text'])) {
            throw new \Exception('Invalid AI response format');
        }

        $generatedText = $response[0]['generated_text'];
        
        if (preg_match('/\{[^}]+\}/', $generatedText, $matches)) {
            $jsonStr = $matches[0];
            $data = json_decode($jsonStr, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($data['score'])) {
                return [
                    'score' => min(100, max(0, (int)$data['score'])),
                    'warning_message' => $data['warning_message'] ?? null
                ];
            }
        }

        throw new \Exception('Could not parse AI response');
    }

    private function calculateFallbackScore($userTags, $ingredientNames)
    {
        $score = 100;

        if (in_array('no_sugar', $userTags) && collect($ingredientNames)->contains('sugar'))
            $score -= 30;

        if (in_array('vegan', $userTags) && collect($ingredientNames)->contains('meat'))
            $score -= 50;

        if (in_array('gluten_free', $userTags) && collect($ingredientNames)->contains('wheat'))
            $score -= 40;

        if (in_array('vegetarian', $userTags) && collect($ingredientNames)->contains('meat'))
            $score -= 45;

        return max(0, $score);
    }

    private function getLabel($score)
    {
        if ($score >= 80)
            return '✅ Highly Recommended';

        if ($score >= 50)
            return '🟡 Recommended with notes';

        return '⚠️ Not Recommended';
    }
}