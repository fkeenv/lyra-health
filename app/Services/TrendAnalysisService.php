<?php

namespace App\Services;

use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class TrendAnalysisService
{
    /**
     * Get trends for a specific vital sign type over a time period.
     *
     * @param  User  $user  The user to analyze
     * @param  int  $vitalSignTypeId  The vital sign type ID to analyze
     * @param  int  $days  Number of days to look back (default 30)
     * @return array Trend analysis data including direction, percentage change, and chart data
     */
    public function getTrends(User $user, int $vitalSignTypeId, int $days = 30): array
    {
        $endDate = now();
        $startDate = $endDate->copy()->subDays($days);

        $records = $user->vitalSignsRecords()
            ->with('vitalSignType')
            ->where('vital_sign_type_id', $vitalSignTypeId)
            ->whereBetween('measured_at', [$startDate, $endDate])
            ->orderBy('measured_at', 'asc')
            ->get();

        if ($records->isEmpty()) {
            return [
                'trend_direction' => 'no_data',
                'percentage_change' => 0,
                'total_records' => 0,
                'data_points' => [],
                'statistics' => [],
            ];
        }

        // Calculate trend direction and percentage change
        $firstValue = $records->first()->value_primary;
        $lastValue = $records->last()->value_primary;
        $percentageChange = $firstValue > 0 ? (($lastValue - $firstValue) / $firstValue) * 100 : 0;

        // Determine trend direction
        $trendDirection = 'stable';
        if (abs($percentageChange) > 5) {
            $trendDirection = $percentageChange > 0 ? 'increasing' : 'decreasing';
        }

        // Prepare data points for charting
        $dataPoints = $records->map(function ($record) {
            return [
                'date' => $record->measured_at->format('Y-m-d'),
                'value' => $record->value_primary,
                'secondary_value' => $record->value_secondary,
                'is_flagged' => $record->is_flagged,
            ];
        })->toArray();

        // Calculate basic statistics
        $statistics = $this->calculateStatistics($records);

        return [
            'trend_direction' => $trendDirection,
            'percentage_change' => round($percentageChange, 2),
            'total_records' => $records->count(),
            'data_points' => $dataPoints,
            'statistics' => $statistics,
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'days' => $days,
            ],
        ];
    }

    /**
     * Calculate averages for a collection of vital signs records.
     *
     * @param  Collection  $records  Collection of VitalSignsRecord models
     * @return array Average values and statistics
     */
    public function calculateAverages(Collection $records): array
    {
        if ($records->isEmpty()) {
            return [
                'primary_average' => 0,
                'secondary_average' => 0,
                'min_value' => 0,
                'max_value' => 0,
                'count' => 0,
            ];
        }

        $primaryValues = $records->pluck('value_primary');
        $secondaryValues = $records->whereNotNull('value_secondary')->pluck('value_secondary');

        return [
            'primary_average' => round($primaryValues->avg(), 2),
            'secondary_average' => $secondaryValues->isNotEmpty() ? round($secondaryValues->avg(), 2) : null,
            'min_value' => $primaryValues->min(),
            'max_value' => $primaryValues->max(),
            'count' => $records->count(),
            'median' => $this->calculateMedian($primaryValues->toArray()),
            'standard_deviation' => $this->calculateStandardDeviation($primaryValues->toArray()),
        ];
    }

    /**
     * Detect patterns in vital signs data.
     *
     * @param  Collection  $records  Collection of VitalSignsRecord models
     * @return array Detected patterns and insights
     */
    public function detectPatterns(Collection $records): array
    {
        if ($records->count() < 3) {
            return [
                'patterns_detected' => [],
                'insights' => ['Insufficient data for pattern analysis'],
            ];
        }

        $patterns = [];
        $insights = [];
        $values = $records->pluck('value_primary')->toArray();

        // Detect consecutive increasing/decreasing trends
        $consecutiveIncreasing = $this->detectConsecutiveTrend($values, 'increasing');
        $consecutiveDecreasing = $this->detectConsecutiveTrend($values, 'decreasing');

        if ($consecutiveIncreasing >= 3) {
            $patterns[] = 'consecutive_increasing';
            $insights[] = "Detected {$consecutiveIncreasing} consecutive increasing readings";
        }

        if ($consecutiveDecreasing >= 3) {
            $patterns[] = 'consecutive_decreasing';
            $insights[] = "Detected {$consecutiveDecreasing} consecutive decreasing readings";
        }

        // Detect high variability
        $standardDeviation = $this->calculateStandardDeviation($values);
        $average = array_sum($values) / count($values);
        $coefficientOfVariation = $average > 0 ? ($standardDeviation / $average) * 100 : 0;

        if ($coefficientOfVariation > 20) {
            $patterns[] = 'high_variability';
            $insights[] = 'High variability detected in readings';
        }

        // Detect outliers
        $outliers = $this->detectOutliers($values);
        if (count($outliers) > 0) {
            $patterns[] = 'outliers_detected';
            $insights[] = count($outliers).' potential outlier(s) detected';
        }

        // Detect time-based patterns (e.g., morning vs evening readings)
        $timePatterns = $this->detectTimePatterns($records);
        if (! empty($timePatterns)) {
            $patterns = array_merge($patterns, array_keys($timePatterns));
            $insights = array_merge($insights, array_values($timePatterns));
        }

        return [
            'patterns_detected' => $patterns,
            'insights' => $insights,
            'statistics' => [
                'coefficient_of_variation' => round($coefficientOfVariation, 2),
                'outlier_count' => count($outliers),
                'trend_consistency' => $this->calculateTrendConsistency($values),
            ],
        ];
    }

    /**
     * Get progress summary for a user across all vital sign types.
     *
     * @param  User  $user  The user to analyze
     * @param  int  $days  Number of days to look back (default 30)
     * @return array Progress summary across all vital signs
     */
    public function getProgressSummary(User $user, int $days = 30): array
    {
        $endDate = now();
        $startDate = $endDate->copy()->subDays($days);

        $allRecords = $user->vitalSignsRecords()
            ->with('vitalSignType')
            ->whereBetween('measured_at', [$startDate, $endDate])
            ->orderBy('measured_at', 'asc')
            ->get();

        $summaryByType = [];
        $overallStats = [
            'total_records' => $allRecords->count(),
            'flagged_records' => $allRecords->where('is_flagged', true)->count(),
            'recording_consistency' => $this->calculateRecordingConsistency($allRecords, $days),
        ];

        // Group by vital sign type
        foreach ($allRecords->groupBy('vital_sign_type_id') as $typeId => $typeRecords) {
            $vitalSignType = $typeRecords->first()->vitalSignType;
            $trends = $this->getTrends($user, $typeId, $days);

            $summaryByType[$vitalSignType->name] = [
                'record_count' => $typeRecords->count(),
                'trend_direction' => $trends['trend_direction'],
                'percentage_change' => $trends['percentage_change'],
                'averages' => $this->calculateAverages($typeRecords),
                'flagged_count' => $typeRecords->where('is_flagged', true)->count(),
                'latest_reading' => $typeRecords->last(),
            ];
        }

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'days' => $days,
            ],
            'overall_statistics' => $overallStats,
            'by_vital_sign_type' => $summaryByType,
            'health_score' => $this->calculateHealthScore($summaryByType),
        ];
    }

    /**
     * Compare two time frames for a specific vital sign type.
     *
     * @param  User  $user  The user to analyze
     * @param  int  $vitalSignTypeId  The vital sign type to compare
     * @param  int  $days1  Days for first time frame
     * @param  int  $days2  Days for second time frame
     * @return array Comparison analysis between two time periods
     */
    public function compareTimeframes(User $user, int $vitalSignTypeId, int $days1, int $days2): array
    {
        $period1Trends = $this->getTrends($user, $vitalSignTypeId, $days1);
        $period2Trends = $this->getTrends($user, $vitalSignTypeId, $days2);

        // Calculate improvements or deteriorations
        $averageChange = 0;
        $trendComparison = 'no_change';

        if ($period1Trends['statistics']['primary_average'] > 0 && $period2Trends['statistics']['primary_average'] > 0) {
            $averageChange = (($period1Trends['statistics']['primary_average'] - $period2Trends['statistics']['primary_average'])
                / $period2Trends['statistics']['primary_average']) * 100;
        }

        if (abs($averageChange) > 5) {
            $trendComparison = $averageChange > 0 ? 'improvement' : 'deterioration';
        }

        return [
            'comparison_type' => 'timeframe_comparison',
            'period_1' => [
                'days' => $days1,
                'label' => "Last {$days1} days",
                'trends' => $period1Trends,
            ],
            'period_2' => [
                'days' => $days2,
                'label' => "Last {$days2} days",
                'trends' => $period2Trends,
            ],
            'comparison_results' => [
                'trend_comparison' => $trendComparison,
                'average_change_percentage' => round($averageChange, 2),
                'record_count_change' => $period1Trends['total_records'] - $period2Trends['total_records'],
                'consistency_change' => $this->compareConsistency($period1Trends, $period2Trends),
            ],
        ];
    }

    /**
     * Calculate basic statistics for a collection of records.
     *
     * @param  Collection  $records  Collection of VitalSignsRecord models
     * @return array Statistical measurements
     */
    protected function calculateStatistics(Collection $records): array
    {
        $values = $records->pluck('value_primary')->toArray();

        if (empty($values)) {
            return [];
        }

        return [
            'count' => count($values),
            'mean' => round(array_sum($values) / count($values), 2),
            'median' => $this->calculateMedian($values),
            'min' => min($values),
            'max' => max($values),
            'range' => max($values) - min($values),
            'standard_deviation' => $this->calculateStandardDeviation($values),
            'variance' => $this->calculateVariance($values),
        ];
    }

    /**
     * Calculate median value from an array of numbers.
     *
     * @param  array  $values  Numeric values
     * @return float Median value
     */
    protected function calculateMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $middle = intval($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Calculate standard deviation from an array of numbers.
     *
     * @param  array  $values  Numeric values
     * @return float Standard deviation
     */
    protected function calculateStandardDeviation(array $values): float
    {
        return sqrt($this->calculateVariance($values));
    }

    /**
     * Calculate variance from an array of numbers.
     *
     * @param  array  $values  Numeric values
     * @return float Variance
     */
    protected function calculateVariance(array $values): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);

        return array_sum($squaredDifferences) / (count($values) - 1);
    }

    /**
     * Detect consecutive trends in values.
     *
     * @param  array  $values  Numeric values in chronological order
     * @param  string  $direction  'increasing' or 'decreasing'
     * @return int Number of consecutive trends detected
     */
    protected function detectConsecutiveTrend(array $values, string $direction): int
    {
        if (count($values) < 2) {
            return 0;
        }

        $maxConsecutive = 0;
        $currentConsecutive = 0;

        for ($i = 1; $i < count($values); $i++) {
            $isIncreasing = $values[$i] > $values[$i - 1];
            $isDecreasing = $values[$i] < $values[$i - 1];

            if (($direction === 'increasing' && $isIncreasing) ||
                ($direction === 'decreasing' && $isDecreasing)) {
                $currentConsecutive++;
                $maxConsecutive = max($maxConsecutive, $currentConsecutive);
            } else {
                $currentConsecutive = 0;
            }
        }

        return $maxConsecutive;
    }

    /**
     * Detect outliers using the IQR method.
     *
     * @param  array  $values  Numeric values
     * @return array Array of outlier values
     */
    protected function detectOutliers(array $values): array
    {
        if (count($values) < 4) {
            return [];
        }

        sort($values);
        $count = count($values);

        // Calculate quartiles
        $q1Index = intval($count * 0.25);
        $q3Index = intval($count * 0.75);

        $q1 = $values[$q1Index];
        $q3 = $values[$q3Index];
        $iqr = $q3 - $q1;

        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);

        return array_filter($values, function ($value) use ($lowerBound, $upperBound) {
            return $value < $lowerBound || $value > $upperBound;
        });
    }

    /**
     * Detect time-based patterns in readings.
     *
     * @param  Collection  $records  Collection of VitalSignsRecord models
     * @return array Time-based patterns detected
     */
    protected function detectTimePatterns(Collection $records): array
    {
        $patterns = [];

        // Group by hour of day
        $hourlyGroups = $records->groupBy(function ($record) {
            return $record->measured_at->hour;
        });

        // Check for morning vs evening patterns
        $morningReadings = $records->filter(function ($record) {
            return $record->measured_at->hour >= 6 && $record->measured_at->hour < 12;
        });

        $eveningReadings = $records->filter(function ($record) {
            return $record->measured_at->hour >= 18 && $record->measured_at->hour < 24;
        });

        if ($morningReadings->count() >= 3 && $eveningReadings->count() >= 3) {
            $morningAvg = $morningReadings->avg('value_primary');
            $eveningAvg = $eveningReadings->avg('value_primary');

            $difference = abs($morningAvg - $eveningAvg);
            $percentDiff = $morningAvg > 0 ? ($difference / $morningAvg) * 100 : 0;

            if ($percentDiff > 10) {
                if ($morningAvg > $eveningAvg) {
                    $patterns['morning_elevation'] = 'Readings tend to be higher in the morning';
                } else {
                    $patterns['evening_elevation'] = 'Readings tend to be higher in the evening';
                }
            }
        }

        return $patterns;
    }

    /**
     * Calculate trend consistency score.
     *
     * @param  array  $values  Numeric values in chronological order
     * @return float Consistency score (0-100)
     */
    protected function calculateTrendConsistency(array $values): float
    {
        if (count($values) < 3) {
            return 0;
        }

        $changes = [];
        for ($i = 1; $i < count($values); $i++) {
            $changes[] = $values[$i] - $values[$i - 1];
        }

        $positiveChanges = count(array_filter($changes, fn ($change) => $change > 0));
        $negativeChanges = count(array_filter($changes, fn ($change) => $change < 0));
        $totalChanges = count($changes);

        if ($totalChanges === 0) {
            return 100;
        }

        // Calculate consistency as the percentage of changes in the dominant direction
        $dominantDirection = max($positiveChanges, $negativeChanges);

        return round(($dominantDirection / $totalChanges) * 100, 2);
    }

    /**
     * Calculate recording consistency over time period.
     *
     * @param  Collection  $records  Collection of VitalSignsRecord models
     * @param  int  $days  Total days in period
     * @return float Consistency score (0-100)
     */
    protected function calculateRecordingConsistency(Collection $records, int $days): float
    {
        if ($records->isEmpty() || $days <= 0) {
            return 0;
        }

        $uniqueDays = $records->groupBy(function ($record) {
            return $record->measured_at->format('Y-m-d');
        })->count();

        return round(($uniqueDays / $days) * 100, 2);
    }

    /**
     * Compare consistency between two trend periods.
     *
     * @param  array  $period1Trends  First period trends
     * @param  array  $period2Trends  Second period trends
     * @return string Consistency comparison result
     */
    protected function compareConsistency(array $period1Trends, array $period2Trends): string
    {
        $consistency1 = $period1Trends['statistics']['trend_consistency'] ?? 0;
        $consistency2 = $period2Trends['statistics']['trend_consistency'] ?? 0;

        $difference = $consistency1 - $consistency2;

        if (abs($difference) < 10) {
            return 'similar';
        }

        return $difference > 0 ? 'more_consistent' : 'less_consistent';
    }

    /**
     * Calculate overall health score based on vital signs summary.
     *
     * @param  array  $summaryByType  Summary data by vital sign type
     * @return int Health score (0-100)
     */
    protected function calculateHealthScore(array $summaryByType): int
    {
        if (empty($summaryByType)) {
            return 0;
        }

        $totalScore = 0;
        $typeCount = 0;

        foreach ($summaryByType as $typeName => $typeData) {
            $typeScore = 100; // Start with perfect score

            // Reduce score for flagged readings
            if ($typeData['flagged_count'] > 0) {
                $flaggedPercentage = ($typeData['flagged_count'] / $typeData['record_count']) * 100;
                $typeScore -= min(50, $flaggedPercentage);
            }

            // Adjust score based on trend direction (context dependent)
            if ($typeData['trend_direction'] === 'increasing' && in_array($typeName, ['blood_pressure', 'weight'])) {
                $typeScore -= min(20, abs($typeData['percentage_change']));
            } elseif ($typeData['trend_direction'] === 'decreasing' && in_array($typeName, ['oxygen_saturation'])) {
                $typeScore -= min(20, abs($typeData['percentage_change']));
            }

            $totalScore += max(0, $typeScore);
            $typeCount++;
        }

        return $typeCount > 0 ? intval($totalScore / $typeCount) : 0;
    }

    /**
     * Get trends for all vital sign types for a user.
     *
     * @param  User  $user  The user
     * @param  string|null  $startDate  Start date filter
     * @param  string|null  $endDate  End date filter
     * @param  string  $period  Analysis period
     * @param  string  $comparisonPeriod  Comparison period
     * @param  string  $groupBy  Grouping period
     * @return array Trends data for all vital sign types
     */
    public function getUserTrends(User $user, ?string $startDate = null, ?string $endDate = null, string $period = 'monthly', string $comparisonPeriod = 'previous_period', string $groupBy = 'week'): array
    {
        // Set default date range if not provided
        $endDate = $endDate ? Carbon::parse($endDate) : now();
        $startDate = $startDate ? Carbon::parse($startDate) : $endDate->copy()->subDays($this->getPeriodDays($period));

        // Get all vital sign types that have data for this user
        $vitalSignTypes = VitalSignType::whereHas('vitalSignsRecords', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        $trends = [];

        foreach ($vitalSignTypes as $vitalSignType) {
            $records = $user->vitalSignsRecords()
                ->where('vital_sign_type_id', $vitalSignType->id)
                ->whereBetween('measured_at', [$startDate, $endDate])
                ->orderBy('measured_at')
                ->get();

            if ($records->isNotEmpty()) {
                $trends[$vitalSignType->name] = [
                    'vital_sign_type' => [
                        'id' => $vitalSignType->id,
                        'name' => $vitalSignType->name,
                        'display_name' => $vitalSignType->display_name,
                        'unit_primary' => $vitalSignType->unit_primary,
                    ],
                    'trends' => $this->getTrends($records, $groupBy),
                    'summary' => $this->calculateSummaryStatistics($records),
                ];
            }
        }

        // Add comparison with previous period if requested
        if ($comparisonPeriod !== 'none') {
            $trends = $this->addComparisonData($trends, $user, $startDate, $endDate, $comparisonPeriod);
        }

        return [
            'vital_sign_types' => $trends,
            'period_info' => [
                'start_date' => $startDate->toISOString(),
                'end_date' => $endDate->toISOString(),
                'period' => $period,
                'comparison_period' => $comparisonPeriod,
                'group_by' => $groupBy,
            ],
            'summary' => $this->calculateOverallSummary(array_values($trends)),
        ];
    }

    /**
     * Get trends for a specific vital sign type for a user.
     *
     * @param  User  $user  The user
     * @param  int  $vitalSignTypeId  Vital sign type ID
     * @param  string|null  $startDate  Start date filter
     * @param  string|null  $endDate  End date filter
     * @param  string  $period  Analysis period
     * @param  string  $comparisonPeriod  Comparison period
     * @param  string  $groupBy  Grouping period
     * @param  bool  $includeAverages  Include moving averages
     * @param  bool  $includeMinMax  Include min/max values
     * @return array Detailed trends data for the vital sign type
     */
    public function getVitalSignTypeTrends(User $user, int $vitalSignTypeId, ?string $startDate = null, ?string $endDate = null, string $period = 'monthly', string $comparisonPeriod = 'previous_period', string $groupBy = 'week', bool $includeAverages = true, bool $includeMinMax = false): array
    {
        $vitalSignType = VitalSignType::findOrFail($vitalSignTypeId);

        // Set default date range if not provided
        $endDate = $endDate ? Carbon::parse($endDate) : now();
        $startDate = $startDate ? Carbon::parse($startDate) : $endDate->copy()->subDays($this->getPeriodDays($period));

        // Get records for the specified period
        $records = $user->vitalSignsRecords()
            ->where('vital_sign_type_id', $vitalSignTypeId)
            ->whereBetween('measured_at', [$startDate, $endDate])
            ->orderBy('measured_at')
            ->get();

        if ($records->isEmpty()) {
            return [
                'vital_sign_type' => [
                    'id' => $vitalSignType->id,
                    'name' => $vitalSignType->name,
                    'display_name' => $vitalSignType->display_name,
                    'unit_primary' => $vitalSignType->unit_primary,
                    'unit_secondary' => $vitalSignType->unit_secondary,
                ],
                'trends' => [],
                'summary' => [],
                'message' => 'No data available for the selected period',
            ];
        }

        $trendsData = $this->getTrends($records, $groupBy);
        $summary = $this->calculateSummaryStatistics($records);

        // Add moving averages if requested
        if ($includeAverages) {
            $trendsData['moving_averages'] = $this->calculateMovingAverages($records, 7); // 7-day moving average
        }

        // Add min/max values if requested
        if ($includeMinMax) {
            $trendsData['min_max'] = $this->calculateMinMaxValues($records, $groupBy);
        }

        // Add patterns and anomalies detection
        $trendsData['patterns'] = $this->detectPatterns($records);
        $trendsData['anomalies'] = $this->detectAnomalies($records);

        $result = [
            'vital_sign_type' => [
                'id' => $vitalSignType->id,
                'name' => $vitalSignType->name,
                'display_name' => $vitalSignType->display_name,
                'unit_primary' => $vitalSignType->unit_primary,
                'unit_secondary' => $vitalSignType->unit_secondary,
                'normal_range_min' => $vitalSignType->normal_range_min,
                'normal_range_max' => $vitalSignType->normal_range_max,
                'warning_range_min' => $vitalSignType->warning_range_min,
                'warning_range_max' => $vitalSignType->warning_range_max,
            ],
            'trends' => $trendsData,
            'summary' => $summary,
            'period_info' => [
                'start_date' => $startDate->toISOString(),
                'end_date' => $endDate->toISOString(),
                'period' => $period,
                'comparison_period' => $comparisonPeriod,
                'group_by' => $groupBy,
                'total_records' => $records->count(),
                'date_range_days' => $startDate->diffInDays($endDate),
            ],
        ];

        // Add comparison with previous period if requested
        if ($comparisonPeriod !== 'none') {
            $result['comparison'] = $this->getComparisonData($user, $vitalSignTypeId, $startDate, $endDate, $comparisonPeriod);
        }

        return $result;
    }

    /**
     * Get the number of days for a given period.
     *
     * @param  string  $period  The period string
     * @return int Number of days
     */
    protected function getPeriodDays(string $period): int
    {
        return match ($period) {
            'weekly' => 7,
            'monthly' => 30,
            'quarterly' => 90,
            'yearly' => 365,
            default => 30,
        };
    }

    /**
     * Add comparison data to trends.
     *
     * @param  array  $trends  Current trends data
     * @param  User  $user  The user
     * @param  Carbon  $startDate  Current period start
     * @param  Carbon  $endDate  Current period end
     * @param  string  $comparisonPeriod  Comparison period type
     * @return array Trends with comparison data
     */
    protected function addComparisonData(array $trends, User $user, Carbon $startDate, Carbon $endDate, string $comparisonPeriod): array
    {
        foreach ($trends as $typeName => &$typeData) {
            $comparisonData = $this->getComparisonData($user, $typeData['vital_sign_type']['id'], $startDate, $endDate, $comparisonPeriod);
            $typeData['comparison'] = $comparisonData;
        }

        return $trends;
    }

    /**
     * Get comparison data for a previous period.
     *
     * @param  User  $user  The user
     * @param  int  $vitalSignTypeId  Vital sign type ID
     * @param  Carbon  $currentStart  Current period start
     * @param  Carbon  $currentEnd  Current period end
     * @param  string  $comparisonPeriod  Comparison period type
     * @return array Comparison data
     */
    protected function getComparisonData(User $user, int $vitalSignTypeId, Carbon $currentStart, Carbon $currentEnd, string $comparisonPeriod): array
    {
        $daysDiff = $currentStart->diffInDays($currentEnd);

        if ($comparisonPeriod === 'previous_period') {
            $compareStart = $currentStart->copy()->subDays($daysDiff);
            $compareEnd = $currentStart->copy();
        } else { // previous_year
            $compareStart = $currentStart->copy()->subYear();
            $compareEnd = $currentEnd->copy()->subYear();
        }

        $compareRecords = $user->vitalSignsRecords()
            ->where('vital_sign_type_id', $vitalSignTypeId)
            ->whereBetween('measured_at', [$compareStart, $compareEnd])
            ->orderBy('measured_at')
            ->get();

        if ($compareRecords->isEmpty()) {
            return [
                'period' => $comparisonPeriod,
                'data_available' => false,
                'message' => 'No data available for comparison period',
            ];
        }

        return [
            'period' => $comparisonPeriod,
            'data_available' => true,
            'start_date' => $compareStart->toISOString(),
            'end_date' => $compareEnd->toISOString(),
            'summary' => $this->calculateSummaryStatistics($compareRecords),
            'record_count' => $compareRecords->count(),
        ];
    }

    /**
     * Calculate overall summary across all vital sign types.
     *
     * @param  array  $typesTrends  Array of trends for each type
     * @return array Overall summary
     */
    protected function calculateOverallSummary(array $typesTrends): array
    {
        if (empty($typesTrends)) {
            return [];
        }

        $totalRecords = 0;
        $totalFlagged = 0;
        $typesWithData = count($typesTrends);

        foreach ($typesTrends as $typeData) {
            $summary = $typeData['summary'] ?? [];
            $totalRecords += $summary['total_records'] ?? 0;
            $totalFlagged += $summary['flagged_count'] ?? 0;
        }

        return [
            'total_vital_sign_types' => $typesWithData,
            'total_records' => $totalRecords,
            'total_flagged' => $totalFlagged,
            'flagged_percentage' => $totalRecords > 0 ? round(($totalFlagged / $totalRecords) * 100, 2) : 0,
            'average_records_per_type' => $typesWithData > 0 ? round($totalRecords / $typesWithData, 1) : 0,
        ];
    }

    /**
     * Calculate moving averages for records.
     *
     * @param  Collection  $records  Collection of records
     * @param  int  $windowSize  Size of moving average window
     * @return array Moving averages data
     */
    protected function calculateMovingAverages(Collection $records, int $windowSize = 7): array
    {
        if ($records->count() < $windowSize) {
            return [];
        }

        $averages = [];
        $values = $records->pluck('value_primary')->toArray();

        for ($i = $windowSize - 1; $i < count($values); $i++) {
            $window = array_slice($values, $i - $windowSize + 1, $windowSize);
            $averages[] = [
                'date' => $records[$i]->measured_at->toISOString(),
                'average' => round(array_sum($window) / count($window), 2),
            ];
        }

        return $averages;
    }

    /**
     * Calculate min/max values grouped by period.
     *
     * @param  Collection  $records  Collection of records
     * @param  string  $groupBy  Grouping period
     * @return array Min/max values by period
     */
    protected function calculateMinMaxValues(Collection $records, string $groupBy): array
    {
        $groupedRecords = $records->groupBy(function ($record) use ($groupBy) {
            return match ($groupBy) {
                'day' => $record->measured_at->format('Y-m-d'),
                'week' => $record->measured_at->format('Y-W'),
                'month' => $record->measured_at->format('Y-m'),
                default => $record->measured_at->format('Y-m-d'),
            };
        });

        $minMaxData = [];

        foreach ($groupedRecords as $period => $periodRecords) {
            $values = $periodRecords->pluck('value_primary');
            $minMaxData[] = [
                'period' => $period,
                'min' => $values->min(),
                'max' => $values->max(),
                'count' => $periodRecords->count(),
            ];
        }

        return $minMaxData;
    }
}
