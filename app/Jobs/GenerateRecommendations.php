<?php

namespace App\Jobs;

use App\Models\Recommendation;
use App\Models\User;
use App\Services\RecommendationGenerationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateRecommendations implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    public ?int $userId;

    public bool $processAllUsers;

    public array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $userId = null, bool $processAllUsers = true, array $options = [])
    {
        $this->userId = $userId;
        $this->processAllUsers = $processAllUsers;
        $this->options = array_merge([
            'lookback_days' => 30,
            'min_readings_required' => 3,
            'force_regenerate' => false,
            'recommendation_types' => ['health_alert', 'lifestyle', 'trend_observation', 'goal_progress'],
        ], $options);
    }

    /**
     * Execute the job.
     */
    public function handle(RecommendationGenerationService $recommendationService): void
    {
        Log::info('Starting recommendation generation job', [
            'user_id' => $this->userId,
            'process_all_users' => $this->processAllUsers,
            'options' => $this->options,
        ]);

        $startTime = microtime(true);
        $processedUsers = 0;
        $generatedRecommendations = 0;

        try {
            if ($this->userId) {
                // Process specific user
                $user = User::find($this->userId);
                if ($user) {
                    $recommendations = $this->generateRecommendationsForUser($user, $recommendationService);
                    $generatedRecommendations += count($recommendations);
                    $processedUsers = 1;
                }
            } elseif ($this->processAllUsers) {
                // Process all active users with recent vital signs
                $cutoffDate = Carbon::now()->subDays($this->options['lookback_days']);

                $users = User::whereHas('vitalSignsRecords', function ($query) use ($cutoffDate) {
                    $query->where('measured_at', '>=', $cutoffDate);
                })
                    ->with(['vitalSignsRecords' => function ($query) use ($cutoffDate) {
                        $query->where('measured_at', '>=', $cutoffDate)
                            ->with('vitalSignType')
                            ->orderBy('measured_at', 'desc');
                    }])
                    ->chunk(50, function ($userChunk) use (&$processedUsers, &$generatedRecommendations, $recommendationService) {
                        foreach ($userChunk as $user) {
                            $recommendations = $this->generateRecommendationsForUser($user, $recommendationService);
                            $generatedRecommendations += count($recommendations);
                            $processedUsers++;
                        }
                    });
            }

            $executionTime = round(microtime(true) - $startTime, 2);

            Log::info('Recommendation generation completed', [
                'processed_users' => $processedUsers,
                'generated_recommendations' => $generatedRecommendations,
                'execution_time_seconds' => $executionTime,
            ]);

        } catch (\Exception $e) {
            Log::error('Error during recommendation generation', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate recommendations for a specific user.
     */
    private function generateRecommendationsForUser(User $user, RecommendationGenerationService $recommendationService): array
    {
        // Dismiss old recommendations if force regenerate is enabled
        if ($this->options['force_regenerate']) {
            $this->dismissOldRecommendations($user);
        }

        // Use the recommendation service to generate comprehensive recommendations
        $serviceRecommendations = $recommendationService->generateComprehensiveRecommendations(
            $user->id,
            $this->options
        );

        $createdRecommendations = [];

        // Convert service recommendations to Recommendation model instances
        foreach ($serviceRecommendations as $recommendationData) {
            $recommendation = $this->createRecommendation($user, [
                'recommendation_type' => $recommendationData['recommendation_type'],
                'title' => $recommendationData['title'],
                'message' => $recommendationData['content'],
                'priority' => $recommendationData['priority'],
                'data' => $recommendationData['data'] ?? [],
            ]);

            if ($recommendation) {
                $createdRecommendations[] = $recommendation;
            }
        }

        return $createdRecommendations;
    }


    /**
     * Create a recommendation record.
     */
    private function createRecommendation(User $user, array $data): ?Recommendation
    {
        // Check if similar recommendation already exists
        $existingRecommendation = Recommendation::where('user_id', $user->id)
            ->where('recommendation_type', $data['recommendation_type'])
            ->where('title', $data['title'])
            ->where('is_active', true)
            ->whereNull('dismissed_at')
            ->where('created_at', '>=', Carbon::now()->subDays(7)) // Don't duplicate recent recommendations
            ->first();

        if ($existingRecommendation) {
            return null; // Don't create duplicate
        }

        try {
            return Recommendation::create(array_merge($data, [
                'user_id' => $user->id,
                'is_active' => true,
                'generated_at' => now(),
                'expires_at' => now()->addDays(30), // Recommendations expire after 30 days
            ]));
        } catch (\Exception $e) {
            Log::error('Failed to create recommendation', [
                'user_id' => $user->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Dismiss old recommendations to avoid clutter.
     */
    private function dismissOldRecommendations(User $user): void
    {
        Recommendation::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('dismissed_at')
            ->where('created_at', '<', Carbon::now()->subDays(30))
            ->update([
                'is_active' => false,
                'dismissed_at' => now(),
                'dismissal_reason' => 'auto_cleanup',
            ]);
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateRecommendations job failed', [
            'user_id' => $this->userId,
            'process_all_users' => $this->processAllUsers,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
