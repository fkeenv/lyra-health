<?php

namespace App\Services;

use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use App\Models\Recommendation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecommendationGenerationService
{
    protected VitalSignsValidationService $validationService;
    protected TrendAnalysisService $trendService;

    public function __construct(
        VitalSignsValidationService $validationService,
        TrendAnalysisService $trendService
    ) {
        $this->validationService = $validationService;
        $this->trendService = $trendService;
    }

    /**
     * Generate recommendations for a specific user and vital sign type.
     */
    public function generateRecommendations(
        int $userId,
        int $vitalSignTypeId,
        array $options = []
    ): array {
        $user = User::findOrFail($userId);
        $vitalSignType = VitalSignType::findOrFail($vitalSignTypeId);

        $lookbackDays = $options['lookback_days'] ?? 30;
        $recommendationTypes = $options['recommendation_types'] ?? [
            'health_alert',
            'lifestyle',
            'trend_observation',
            'goal_progress'
        ];

        // Get recent vital signs records
        $records = VitalSignsRecord::where('user_id', $userId)
            ->where('vital_sign_type_id', $vitalSignTypeId)
            ->where('measured_at', '>=', now()->subDays($lookbackDays))
            ->orderBy('measured_at')
            ->get();

        $recommendations = [];

        foreach ($recommendationTypes as $type) {
            $typeRecommendations = match ($type) {
                'health_alert' => $this->generateHealthAlerts($user, $vitalSignType, $records),
                'lifestyle' => $this->generateLifestyleRecommendations($user, $vitalSignType, $records),
                'trend_observation' => $this->generateTrendObservations($user, $vitalSignType, $records),
                'goal_progress' => $this->generateGoalProgressRecommendations($user, $vitalSignType, $records),
                default => [],
            };

            $recommendations = array_merge($recommendations, $typeRecommendations);
        }

        // Sort by priority (high -> medium -> low)
        usort($recommendations, function ($a, $b) {
            $priorities = ['high' => 3, 'medium' => 2, 'low' => 1];
            return ($priorities[$b['priority']] ?? 1) <=> ($priorities[$a['priority']] ?? 1);
        });

        return $recommendations;
    }

    /**
     * Generate health alert recommendations for abnormal readings.
     */
    protected function generateHealthAlerts(User $user, VitalSignType $vitalSignType, Collection $records): array
    {
        $alerts = [];

        // Find flagged readings
        $flaggedRecords = $records->where('is_flagged', true);

        if ($flaggedRecords->count() >= 3) {
            $alerts[] = [
                'recommendation_type' => 'health_alert',
                'title' => 'Multiple Abnormal ' . $vitalSignType->display_name . ' Readings',
                'content' => "You have {$flaggedRecords->count()} abnormal readings in the recent period. Please consult with your healthcare provider to discuss these concerning values.",
                'priority' => 'high',
                'action_required' => true,
                'data' => [
                    'flagged_count' => $flaggedRecords->count(),
                    'latest_flagged' => $flaggedRecords->last(),
                    'vital_sign_type' => $vitalSignType->name,
                ],
            ];
        } elseif ($flaggedRecords->count() > 0) {
            $alerts[] = [
                'recommendation_type' => 'health_alert',
                'title' => 'Abnormal ' . $vitalSignType->display_name . ' Detected',
                'content' => 'Recent readings show values outside the normal range. Monitor closely and consider consulting with a healthcare professional.',
                'priority' => 'medium',
                'action_required' => false,
                'data' => [
                    'flagged_count' => $flaggedRecords->count(),
                    'vital_sign_type' => $vitalSignType->name,
                ],
            ];
        }

        // Check for critical individual readings
        foreach ($records as $record) {
            $validation = $this->validationService->validateReading(
                $vitalSignType,
                $record->value_primary,
                $record->value_secondary
            );

            if ($validation['warning_level'] === 'critical') {
                $alerts[] = [
                    'recommendation_type' => 'health_alert',
                    'title' => 'Critical ' . $vitalSignType->display_name . ' Reading',
                    'content' => 'A critical reading was detected that requires immediate medical attention.',
                    'priority' => 'high',
                    'action_required' => true,
                    'data' => [
                        'record_id' => $record->id,
                        'value' => $record->value_primary,
                        'measured_at' => $record->measured_at,
                        'vital_sign_type' => $vitalSignType->name,
                    ],
                ];
            }
        }

        return $alerts;
    }

    /**
     * Generate lifestyle recommendations based on monitoring patterns.
     */
    protected function generateLifestyleRecommendations(User $user, VitalSignType $vitalSignType, Collection $records): array
    {
        $recommendations = [];

        // Check monitoring frequency
        $uniqueDays = $records->groupBy(fn($r) => $r->measured_at->format('Y-m-d'))->count();
        $totalDays = 30; // Default lookback period

        if ($uniqueDays < ($totalDays * 0.3)) { // Less than 30% of days covered
            $recommendations[] = [
                'recommendation_type' => 'lifestyle',
                'title' => 'Improve Regular Monitoring of ' . $vitalSignType->display_name,
                'content' => 'Regular monitoring helps track your health progress better. Try to record your vital signs at least 3 times per week for more accurate trend analysis.',
                'priority' => 'low',
                'action_required' => false,
                'data' => [
                    'monitoring_frequency' => $uniqueDays,
                    'recommended_frequency' => ceil($totalDays * 0.5),
                    'vital_sign_type' => $vitalSignType->name,
                ],
            ];
        }

        // Check for time-based patterns that suggest lifestyle adjustments
        $patterns = $this->trendService->detectPatterns($records);

        if (in_array('high_variability', $patterns['patterns_detected'] ?? [])) {
            $recommendations[] = [
                'recommendation_type' => 'lifestyle',
                'title' => 'Reduce ' . $vitalSignType->display_name . ' Variability',
                'content' => 'Your readings show high variability. Consider taking measurements at consistent times of day and under similar conditions for more reliable tracking.',
                'priority' => 'low',
                'action_required' => false,
                'data' => [
                    'variability_detected' => true,
                    'vital_sign_type' => $vitalSignType->name,
                ],
            ];
        }

        return $recommendations;
    }

    /**
     * Generate trend observation recommendations.
     */
    protected function generateTrendObservations(User $user, VitalSignType $vitalSignType, Collection $records): array
    {
        $observations = [];

        if ($records->count() < 5) {
            return $observations; // Need at least 5 readings for trend analysis
        }

        $trend = $this->trendService->getTrends($user, $vitalSignType->id, 30);

        if ($trend['trend_direction'] !== 'stable' && $trend['total_records'] >= 5) {
            $direction = $trend['trend_direction'];
            $percentageChange = abs($trend['percentage_change']);

            $observations[] = [
                'recommendation_type' => 'trend_observation',
                'title' => ucfirst($direction) . ' Trend in ' . $vitalSignType->display_name,
                'content' => "Your {$vitalSignType->display_name} shows a {$direction} trend over the past month. This pattern suggests consistent changes in your health metrics.",
                'priority' => $percentageChange > 15 ? 'medium' : 'low',
                'action_required' => false,
                'data' => [
                    'trend_direction' => $direction,
                    'percentage_change' => $percentageChange,
                    'total_records' => $trend['total_records'],
                    'vital_sign_type' => $vitalSignType->name,
                ],
            ];
        }

        // Look for patterns in the data
        $patterns = $this->trendService->detectPatterns($records);

        if (in_array('high_variability', $patterns['patterns_detected'] ?? [])) {
            $observations[] = [
                'recommendation_type' => 'trend_observation',
                'title' => 'Variability Pattern in ' . $vitalSignType->display_name,
                'content' => 'Your readings show high variability patterns. Understanding these patterns can help you better manage your health.',
                'priority' => 'low',
                'action_required' => false,
                'data' => [
                    'patterns_detected' => $patterns['patterns_detected'] ?? [],
                    'insights' => $patterns['insights'] ?? [],
                    'vital_sign_type' => $vitalSignType->name,
                ],
            ];
        }

        return $observations;
    }

    /**
     * Generate goal progress recommendations for excellent control.
     */
    protected function generateGoalProgressRecommendations(User $user, VitalSignType $vitalSignType, Collection $records): array
    {
        $recommendations = [];

        if ($records->count() < 5) {
            return $recommendations;
        }

        $statistics = $this->trendService->calculateAverages($records);
        $mean = $statistics['primary_average'];

        // Check if readings are consistently within optimal ranges
        $normalCount = 0;
        $totalCount = $records->count();

        foreach ($records as $record) {
            $validation = $this->validationService->validateReading(
                $vitalSignType,
                $record->value_primary,
                $record->value_secondary
            );

            if ($validation['is_normal']) {
                $normalCount++;
            }
        }

        $normalPercentage = ($normalCount / $totalCount) * 100;

        // Excellent control (>90% normal readings)
        if ($normalPercentage >= 90) {
            $recommendations[] = [
                'recommendation_type' => 'goal_progress',
                'title' => 'Excellent ' . $vitalSignType->display_name . ' Control',
                'content' => "Congratulations! You're maintaining excellent control of your {$vitalSignType->display_name} with {$normalPercentage}% of readings in the normal range. Keep up the great work!",
                'priority' => 'low',
                'action_required' => false,
                'data' => [
                    'performance' => 'excellent',
                    'normal_percentage' => $normalPercentage,
                    'total_readings' => $totalCount,
                    'vital_sign_type' => $vitalSignType->name,
                ],
            ];
        }
        // Good control (70-90% normal readings)
        elseif ($normalPercentage >= 70) {
            $recommendations[] = [
                'recommendation_type' => 'goal_progress',
                'title' => 'Good ' . $vitalSignType->display_name . ' Progress',
                'content' => "You're making good progress with {$normalPercentage}% of your readings in the normal range. Consider small adjustments to reach even better control.",
                'priority' => 'low',
                'action_required' => false,
                'data' => [
                    'performance' => 'good',
                    'normal_percentage' => $normalPercentage,
                    'total_readings' => $totalCount,
                    'vital_sign_type' => $vitalSignType->name,
                ],
            ];
        }

        // Check for consistent improvement over time
        if ($records->count() >= 10) {
            $firstHalf = $records->take($records->count() / 2);
            $secondHalf = $records->skip($records->count() / 2);

            $firstHalfAvg = $firstHalf->avg('value_primary');
            $secondHalfAvg = $secondHalf->avg('value_primary');

            $isImproving = $this->determineImprovement($vitalSignType->name, $firstHalfAvg, $secondHalfAvg);

            if ($isImproving) {
                $recommendations[] = [
                    'recommendation_type' => 'goal_progress',
                    'title' => 'Improving ' . $vitalSignType->display_name . ' Trend',
                    'content' => "Your recent readings show improvement compared to earlier in the period. This positive trend indicates your health management efforts are working!",
                    'priority' => 'low',
                    'action_required' => false,
                    'data' => [
                        'performance' => 'improving',
                        'first_half_average' => round($firstHalfAvg, 2),
                        'second_half_average' => round($secondHalfAvg, 2),
                        'vital_sign_type' => $vitalSignType->name,
                    ],
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Determine if change represents improvement for a vital sign type.
     */
    protected function determineImprovement(string $vitalSignTypeName, float $oldValue, float $newValue): bool
    {
        return match ($vitalSignTypeName) {
            'blood_pressure' => $newValue < $oldValue, // Lower is better
            'heart_rate' => abs($newValue - 75) < abs($oldValue - 75), // Closer to ideal ~75 bpm
            'weight' => $newValue < $oldValue, // Usually lower is better
            'blood_glucose' => abs($newValue - 90) < abs($oldValue - 90), // Closer to ideal ~90 mg/dL
            'oxygen_saturation' => $newValue > $oldValue, // Higher is better
            'body_temperature' => abs($newValue - 98.6) < abs($oldValue - 98.6), // Closer to normal
            default => false,
        };
    }

    /**
     * Generate comprehensive recommendations for all vital sign types.
     */
    public function generateComprehensiveRecommendations(
        int $userId,
        array $options = []
    ): array {
        $user = User::findOrFail($userId);

        $lookbackDays = $options['lookback_days'] ?? 30;
        $minReadings = $options['min_readings_required'] ?? 3;

        // Get all vital sign types with enough data
        $vitalSignTypes = VitalSignType::whereHas('vitalSignsRecords', function ($query) use ($userId, $lookbackDays) {
            $query->where('user_id', $userId)
                ->where('measured_at', '>=', now()->subDays($lookbackDays));
        })->get()->filter(function ($vitalSignType) use ($userId, $lookbackDays, $minReadings) {
            $count = $vitalSignType->vitalSignsRecords()
                ->where('user_id', $userId)
                ->where('measured_at', '>=', now()->subDays($lookbackDays))
                ->count();
            return $count >= $minReadings;
        });

        $allRecommendations = [];

        foreach ($vitalSignTypes as $vitalSignType) {
            $typeRecommendations = $this->generateRecommendations(
                $userId,
                $vitalSignType->id,
                $options
            );

            $allRecommendations = array_merge($allRecommendations, $typeRecommendations);
        }

        // Sort by priority and deduplicate similar recommendations
        $allRecommendations = $this->prioritizeAndDeduplicateRecommendations($allRecommendations);

        return $allRecommendations;
    }

    /**
     * Prioritize and deduplicate similar recommendations.
     */
    protected function prioritizeAndDeduplicateRecommendations(array $recommendations): array
    {
        // Group by type and title to detect duplicates
        $grouped = [];

        foreach ($recommendations as $recommendation) {
            $key = $recommendation['recommendation_type'] . '|' . $recommendation['title'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = $recommendation;
            } else {
                // Keep the one with higher priority
                if ($this->getPriorityWeight($recommendation['priority']) >
                    $this->getPriorityWeight($grouped[$key]['priority'])) {
                    $grouped[$key] = $recommendation;
                }
            }
        }

        $deduplicated = array_values($grouped);

        // Sort by priority
        usort($deduplicated, function ($a, $b) {
            return $this->getPriorityWeight($b['priority']) <=> $this->getPriorityWeight($a['priority']);
        });

        return $deduplicated;
    }

    /**
     * Get numeric weight for priority sorting.
     */
    protected function getPriorityWeight(string $priority): int
    {
        return match ($priority) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 1,
        };
    }
}