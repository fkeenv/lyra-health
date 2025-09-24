<?php

namespace App\Services;

use App\Models\Recommendation;
use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    /**
     * Recommendation types
     */
    public const TYPE_CONGRATULATION = 'congratulation';

    public const TYPE_SUGGESTION = 'suggestion';

    public const TYPE_WARNING = 'warning';

    public const TYPE_ALERT = 'alert';

    /**
     * Recommendation severities
     */
    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    protected TrendAnalysisService $trendAnalysisService;

    public function __construct(TrendAnalysisService $trendAnalysisService)
    {
        $this->trendAnalysisService = $trendAnalysisService;
    }

    /**
     * Generate health recommendations based on vital signs data.
     *
     * @param  User  $user  The user to generate recommendations for
     * @return Collection Collection of newly generated recommendations
     */
    public function generateRecommendations(User $user): Collection
    {
        $newRecommendations = collect();

        // Get recent vital signs data for analysis
        $recentRecords = $user->vitalSignsRecords()
            ->with('vitalSignType')
            ->where('measured_at', '>=', now()->subDays(30))
            ->orderBy('measured_at', 'desc')
            ->get();

        if ($recentRecords->isEmpty()) {
            return $newRecommendations;
        }

        return DB::transaction(function () use ($user, $recentRecords, $newRecommendations) {
            // Check for flagged readings
            $flaggedReadings = $recentRecords->where('is_flagged', true);
            foreach ($flaggedReadings as $flaggedRecord) {
                $recommendation = $this->generateFlaggedRecordRecommendation($user, $flaggedRecord);
                if ($recommendation) {
                    $newRecommendations->push($recommendation);
                }
            }

            // Analyze trends for each vital sign type
            $vitalSignTypes = $recentRecords->pluck('vitalSignType')->unique('id');
            foreach ($vitalSignTypes as $vitalSignType) {
                $trendRecommendations = $this->generateTrendBasedRecommendations($user, $vitalSignType);
                $newRecommendations = $newRecommendations->merge($trendRecommendations);
            }

            // Generate consistency recommendations
            $consistencyRecommendations = $this->generateConsistencyRecommendations($user, $recentRecords);
            $newRecommendations = $newRecommendations->merge($consistencyRecommendations);

            // Generate progress congratulations
            $progressRecommendations = $this->generateProgressRecommendations($user);
            $newRecommendations = $newRecommendations->merge($progressRecommendations);

            // Clean up old recommendations to avoid spam
            $this->cleanupOldRecommendations($user);

            return $newRecommendations;
        });
    }

    /**
     * Create a new recommendation.
     *
     * @param  User  $user  The user the recommendation is for
     * @param  string  $type  Type of recommendation
     * @param  string  $title  Recommendation title
     * @param  string  $message  Recommendation message
     * @param  VitalSignsRecord|null  $record  Associated vital signs record
     * @return Recommendation The created recommendation
     */
    public function createRecommendation(
        User $user,
        string $type,
        string $title,
        string $message,
        ?VitalSignsRecord $record = null
    ): Recommendation {
        // Determine severity and action required based on type
        $severity = $this->determineSeverity($type);
        $actionRequired = in_array($type, [self::TYPE_WARNING, self::TYPE_ALERT]);

        // Set expiration date based on type
        $expiresAt = $this->determineExpirationDate($type);

        $recommendation = new Recommendation([
            'user_id' => $user->id,
            'vital_signs_record_id' => $record?->id,
            'recommendation_type' => $type,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'action_required' => $actionRequired,
            'expires_at' => $expiresAt,
            'metadata' => $this->buildMetadata($type, $record),
        ]);

        $recommendation->save();

        return $recommendation->fresh(['user', 'vitalSignsRecord']);
    }

    /**
     * Get active recommendations for a user.
     *
     * @param  User  $user  The user to get recommendations for
     * @return Collection Collection of active recommendations
     */
    public function getActiveRecommendations(User $user): Collection
    {
        return $user->recommendations()
            ->with(['vitalSignsRecord.vitalSignType'])
            ->where('is_active', true)
            ->whereNull('dismissed_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Mark a recommendation as read.
     *
     * @param  Recommendation  $recommendation  The recommendation to mark as read
     * @return bool True if successful
     */
    public function markAsRead(Recommendation $recommendation): bool
    {
        if ($recommendation->isRead()) {
            return true;
        }

        $recommendation->read_at = now();

        return $recommendation->save();
    }

    /**
     * Dismiss a recommendation.
     *
     * @param  Recommendation  $recommendation  The recommendation to dismiss
     * @return bool True if successful
     */
    public function dismissRecommendation(Recommendation $recommendation): bool
    {
        if ($recommendation->isDismissed()) {
            return true;
        }

        $recommendation->dismissed_at = now();

        return $recommendation->save();
    }

    /**
     * Generate recommendations for flagged records.
     *
     * @param  User  $user  The user
     * @param  VitalSignsRecord  $flaggedRecord  The flagged record
     * @return Recommendation|null Generated recommendation or null
     */
    protected function generateFlaggedRecordRecommendation(User $user, VitalSignsRecord $flaggedRecord): ?Recommendation
    {
        // Check if we already have a recent recommendation for this flagged record
        $existingRecommendation = $user->recommendations()
            ->where('vital_signs_record_id', $flaggedRecord->id)
            ->where('created_at', '>', now()->subHours(24))
            ->first();

        if ($existingRecommendation) {
            return null;
        }

        $vitalSignType = $flaggedRecord->vitalSignType;
        $value = $flaggedRecord->getDisplayValue();

        // Determine recommendation type based on how critical the reading is
        $type = $vitalSignType->isValueCritical($flaggedRecord->value_primary)
            ? self::TYPE_ALERT
            : self::TYPE_WARNING;

        $title = $this->generateFlaggedRecordTitle($vitalSignType, $type);
        $message = $this->generateFlaggedRecordMessage($vitalSignType, $flaggedRecord, $type);

        return $this->createRecommendation($user, $type, $title, $message, $flaggedRecord);
    }

    /**
     * Generate trend-based recommendations.
     *
     * @param  User  $user  The user
     * @param  VitalSignType  $vitalSignType  The vital sign type to analyze
     * @return Collection Collection of generated recommendations
     */
    protected function generateTrendBasedRecommendations(User $user, VitalSignType $vitalSignType): Collection
    {
        $recommendations = collect();

        // Get 30-day trends
        $trends = $this->trendAnalysisService->getTrends($user, $vitalSignType->id, 30);

        if ($trends['total_records'] < 5) {
            return $recommendations; // Not enough data for trend analysis
        }

        // Check for concerning trends
        if ($trends['trend_direction'] !== 'stable') {
            $percentageChange = abs($trends['percentage_change']);

            // Generate recommendations based on trend direction and vital sign type
            if ($this->isConcerningTrend($vitalSignType, $trends['trend_direction'], $percentageChange)) {
                $type = $percentageChange > 20 ? self::TYPE_WARNING : self::TYPE_SUGGESTION;
                $title = $this->generateTrendTitle($vitalSignType, $trends);
                $message = $this->generateTrendMessage($vitalSignType, $trends);

                $recommendation = $this->createRecommendation($user, $type, $title, $message);
                $recommendations->push($recommendation);
            }
        }

        // Analyze patterns
        $typeRecords = $user->vitalSignsRecords()
            ->where('vital_sign_type_id', $vitalSignType->id)
            ->where('measured_at', '>=', now()->subDays(30))
            ->get();

        $patterns = $this->trendAnalysisService->detectPatterns($typeRecords);

        if (in_array('high_variability', $patterns['patterns_detected'])) {
            $recommendation = $this->createRecommendation(
                $user,
                self::TYPE_SUGGESTION,
                "High Variability in {$vitalSignType->display_name}",
                "Your {$vitalSignType->display_name} readings show high variability. Consider recording readings at consistent times of day and under similar conditions for more accurate tracking."
            );
            $recommendations->push($recommendation);
        }

        return $recommendations;
    }

    /**
     * Generate consistency-based recommendations.
     *
     * @param  User  $user  The user
     * @param  Collection  $recentRecords  Recent vital signs records
     * @return Collection Collection of generated recommendations
     */
    protected function generateConsistencyRecommendations(User $user, Collection $recentRecords): Collection
    {
        $recommendations = collect();

        // Check recording frequency
        $daysCovered = $recentRecords->groupBy(function ($record) {
            return $record->measured_at->format('Y-m-d');
        })->count();

        if ($daysCovered < 10) { // Less than 10 days of recordings in the last 30 days
            $recommendation = $this->createRecommendation(
                $user,
                self::TYPE_SUGGESTION,
                'Improve Recording Consistency',
                'Regular monitoring helps track your health progress better. Try to record your vital signs at least every few days for more accurate trend analysis.'
            );
            $recommendations->push($recommendation);
        }

        // Check for long gaps in recordings
        $sortedRecords = $recentRecords->sortBy('measured_at');
        $longestGap = $this->findLongestRecordingGap($sortedRecords);

        if ($longestGap > 7) {
            $recommendation = $this->createRecommendation(
                $user,
                self::TYPE_SUGGESTION,
                'Fill Recording Gaps',
                "You had a {$longestGap}-day gap in recordings. Consistent monitoring helps identify trends and patterns in your health data."
            );
            $recommendations->push($recommendation);
        }

        return $recommendations;
    }

    /**
     * Generate progress congratulation recommendations.
     *
     * @param  User  $user  The user
     * @return Collection Collection of generated recommendations
     */
    protected function generateProgressRecommendations(User $user): Collection
    {
        $recommendations = collect();

        // Get progress summary
        $progressSummary = $this->trendAnalysisService->getProgressSummary($user, 30);

        // Congratulate on consistent recording
        if ($progressSummary['overall_statistics']['recording_consistency'] > 80) {
            $recommendation = $this->createRecommendation(
                $user,
                self::TYPE_CONGRATULATION,
                'Great Recording Consistency!',
                'Excellent work maintaining consistent health monitoring! Your dedication to tracking vital signs will help you better understand your health patterns.'
            );
            $recommendations->push($recommendation);
        }

        // Congratulate on improvements
        foreach ($progressSummary['by_vital_sign_type'] as $typeName => $typeData) {
            if ($this->isImprovingTrend($typeName, $typeData)) {
                $recommendation = $this->createRecommendation(
                    $user,
                    self::TYPE_CONGRATULATION,
                    "Improving {$typeData['latest_reading']->vitalSignType->display_name}",
                    $this->generateImprovementMessage($typeName, $typeData)
                );
                $recommendations->push($recommendation);
            }
        }

        return $recommendations;
    }

    /**
     * Determine severity based on recommendation type.
     *
     * @param  string  $type  Recommendation type
     * @return string Severity level
     */
    protected function determineSeverity(string $type): string
    {
        return match ($type) {
            self::TYPE_ALERT => self::SEVERITY_CRITICAL,
            self::TYPE_WARNING => self::SEVERITY_HIGH,
            self::TYPE_SUGGESTION => self::SEVERITY_MEDIUM,
            self::TYPE_CONGRATULATION => self::SEVERITY_LOW,
            default => self::SEVERITY_MEDIUM,
        };
    }

    /**
     * Determine expiration date based on recommendation type.
     *
     * @param  string  $type  Recommendation type
     * @return Carbon|null Expiration date
     */
    protected function determineExpirationDate(string $type): ?Carbon
    {
        return match ($type) {
            self::TYPE_ALERT => now()->addDays(3),
            self::TYPE_WARNING => now()->addDays(7),
            self::TYPE_SUGGESTION => now()->addDays(14),
            self::TYPE_CONGRATULATION => now()->addDays(7),
            default => now()->addDays(7),
        };
    }

    /**
     * Build metadata for the recommendation.
     *
     * @param  string  $type  Recommendation type
     * @param  VitalSignsRecord|null  $record  Associated record
     * @return array Metadata array
     */
    protected function buildMetadata(string $type, ?VitalSignsRecord $record): array
    {
        $metadata = [
            'generated_at' => now()->toISOString(),
            'type' => $type,
        ];

        if ($record) {
            $metadata['vital_sign_record'] = [
                'id' => $record->id,
                'value' => $record->getDisplayValue(),
                'measured_at' => $record->measured_at->toISOString(),
                'is_flagged' => $record->is_flagged,
                'flag_reason' => $record->flag_reason,
            ];
        }

        return $metadata;
    }

    /**
     * Check if a trend is concerning for a specific vital sign type.
     *
     * @param  VitalSignType  $vitalSignType  The vital sign type
     * @param  string  $direction  Trend direction
     * @param  float  $percentageChange  Percentage change
     * @return bool True if concerning
     */
    protected function isConcerningTrend(VitalSignType $vitalSignType, string $direction, float $percentageChange): bool
    {
        // Define concerning trends based on vital sign type and direction
        $concerningTrends = [
            'blood_pressure' => ['increasing' => 10, 'decreasing' => 15],
            'heart_rate' => ['increasing' => 15, 'decreasing' => 15],
            'weight' => ['increasing' => 5, 'decreasing' => 5],
            'blood_glucose' => ['increasing' => 20, 'decreasing' => 20],
            'oxygen_saturation' => ['increasing' => 5, 'decreasing' => 3],
            'body_temperature' => ['increasing' => 3, 'decreasing' => 3],
        ];

        $thresholds = $concerningTrends[$vitalSignType->name] ?? ['increasing' => 15, 'decreasing' => 15];
        $threshold = $thresholds[$direction] ?? 15;

        return $percentageChange >= $threshold;
    }

    /**
     * Generate title for flagged record recommendation.
     *
     * @param  VitalSignType  $vitalSignType  The vital sign type
     * @param  string  $type  Recommendation type
     * @return string Generated title
     */
    protected function generateFlaggedRecordTitle(VitalSignType $vitalSignType, string $type): string
    {
        $urgency = $type === self::TYPE_ALERT ? 'Critical' : 'Abnormal';

        return "{$urgency} {$vitalSignType->display_name} Reading";
    }

    /**
     * Generate message for flagged record recommendation.
     *
     * @param  VitalSignType  $vitalSignType  The vital sign type
     * @param  VitalSignsRecord  $record  The flagged record
     * @param  string  $type  Recommendation type
     * @return string Generated message
     */
    protected function generateFlaggedRecordMessage(VitalSignType $vitalSignType, VitalSignsRecord $record, string $type): string
    {
        $value = $record->getDisplayValue();
        $urgencyText = $type === self::TYPE_ALERT ? 'requires immediate attention' : 'is outside normal range';

        $message = "Your {$vitalSignType->display_name} reading of {$value} {$urgencyText}.";

        if ($type === self::TYPE_ALERT) {
            $message .= ' Please consult with a healthcare professional immediately.';
        } else {
            $message .= ' Consider monitoring more frequently and consult with a healthcare professional if readings remain abnormal.';
        }

        return $message;
    }

    /**
     * Generate title for trend-based recommendation.
     *
     * @param  VitalSignType  $vitalSignType  The vital sign type
     * @param  array  $trends  Trend analysis data
     * @return string Generated title
     */
    protected function generateTrendTitle(VitalSignType $vitalSignType, array $trends): string
    {
        $direction = ucfirst($trends['trend_direction']);

        return "{$direction} {$vitalSignType->display_name} Trend";
    }

    /**
     * Generate message for trend-based recommendation.
     *
     * @param  VitalSignType  $vitalSignType  The vital sign type
     * @param  array  $trends  Trend analysis data
     * @return string Generated message
     */
    protected function generateTrendMessage(VitalSignType $vitalSignType, array $trends): string
    {
        $direction = $trends['trend_direction'];
        $change = abs($trends['percentage_change']);

        $message = "Your {$vitalSignType->display_name} has been {$direction} by {$change}% over the last 30 days.";

        if ($change > 20) {
            $message .= ' This significant change warrants discussion with a healthcare professional.';
        } else {
            $message .= ' Consider lifestyle factors that might be contributing to this trend.';
        }

        return $message;
    }

    /**
     * Find the longest gap between recordings.
     *
     * @param  Collection  $sortedRecords  Records sorted by measured_at
     * @return int Longest gap in days
     */
    protected function findLongestRecordingGap(Collection $sortedRecords): int
    {
        if ($sortedRecords->count() < 2) {
            return 0;
        }

        $longestGap = 0;
        $previousDate = null;

        foreach ($sortedRecords as $record) {
            if ($previousDate) {
                $gap = $previousDate->diffInDays($record->measured_at);
                $longestGap = max($longestGap, $gap);
            }
            $previousDate = $record->measured_at;
        }

        return $longestGap;
    }

    /**
     * Check if a trend represents improvement for a vital sign type.
     *
     * @param  string  $typeName  Vital sign type name
     * @param  array  $typeData  Type data from progress summary
     * @return bool True if improving
     */
    protected function isImprovingTrend(string $typeName, array $typeData): bool
    {
        $direction = $typeData['trend_direction'];
        $change = abs($typeData['percentage_change']);

        // Define what constitutes improvement for each vital sign type
        $improvementDefinitions = [
            'blood_pressure' => $direction === 'decreasing' && $change > 5,
            'weight' => $direction === 'decreasing' && $change > 3,
            'blood_glucose' => $direction === 'decreasing' && $change > 10,
            'heart_rate' => $direction === 'stable' || ($direction === 'decreasing' && $change < 10),
            'oxygen_saturation' => $direction === 'increasing' || $direction === 'stable',
        ];

        return $improvementDefinitions[$typeName] ?? false;
    }

    /**
     * Generate improvement congratulation message.
     *
     * @param  string  $typeName  Vital sign type name
     * @param  array  $typeData  Type data from progress summary
     * @return string Generated message
     */
    protected function generateImprovementMessage(string $typeName, array $typeData): string
    {
        $change = $typeData['percentage_change'];

        return "Great progress! Your {$typeName} has improved by {$change}% over the last month. Keep up the excellent work with your health management!";
    }

    /**
     * Clean up old and expired recommendations.
     *
     * @param  User  $user  The user to clean up recommendations for
     * @return int Number of recommendations cleaned up
     */
    protected function cleanupOldRecommendations(User $user): int
    {
        // Mark expired recommendations as inactive
        $expiredCount = $user->recommendations()
            ->where('is_active', true)
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);

        // Remove very old recommendations (older than 90 days)
        $deletedCount = $user->recommendations()
            ->where('created_at', '<', now()->subDays(90))
            ->delete();

        return $expiredCount + $deletedCount;
    }

    /**
     * Get paginated recommendations for a user with filters.
     *
     * @param  User  $user  The user
     * @param  string|null  $type  Type filter (dietary, exercise, medical, lifestyle, medication)
     * @param  string|null  $status  Status filter (active, read, dismissed)
     * @param  string|null  $priority  Priority filter (low, medium, high, critical)
     * @param  int  $perPage  Number of records per page
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getUserRecommendations(User $user, ?string $type = null, ?string $status = null, ?string $priority = null, int $perPage = 15)
    {
        $query = $user->recommendations()
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('recommendation_type', $type);
        }

        if ($status === 'active') {
            $query->where('is_active', true)
                ->where('status', 'active');
        } elseif ($status === 'read') {
            $query->where('status', 'read');
        } elseif ($status === 'dismissed') {
            $query->where('status', 'dismissed');
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        return $query->paginate($perPage);
    }

    /**
     * Dismiss a recommendation.
     *
     * @param  Recommendation  $recommendation  The recommendation to dismiss
     * @return Recommendation The updated recommendation
     */
    public function dismiss(Recommendation $recommendation): Recommendation
    {
        $recommendation->update([
            'status' => 'dismissed',
            'is_active' => false,
            'dismissed_at' => now(),
        ]);

        return $recommendation->fresh();
    }
}
