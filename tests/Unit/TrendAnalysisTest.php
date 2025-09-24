<?php

use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use App\Services\TrendAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Trend Analysis Calculations', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->trendService = new TrendAnalysisService;

        $this->vitalSignTypes = collect([
            VitalSignType::factory()->create([
                'name' => 'blood_pressure',
                'display_name' => 'Blood Pressure',
                'unit_primary' => 'mmHg',
                'unit_secondary' => 'mmHg',
                'has_secondary_value' => true,
                'normal_range_min' => 90,
                'normal_range_max' => 140,
            ]),
            VitalSignType::factory()->create([
                'name' => 'heart_rate',
                'display_name' => 'Heart Rate',
                'unit_primary' => 'bpm',
                'has_secondary_value' => false,
                'normal_range_min' => 60,
                'normal_range_max' => 100,
            ]),
            VitalSignType::factory()->create([
                'name' => 'weight',
                'display_name' => 'Weight',
                'unit_primary' => 'kg',
                'has_secondary_value' => false,
                'normal_range_min' => 50,
                'normal_range_max' => 120,
            ]),
        ]);
    });

    describe('Basic Statistical Calculations', function () {
        it('calculates mean (average) correctly', function () {
            $values = [70, 72, 68, 75, 69, 71, 73];
            $mean = $this->trendService->calculateMean($values);

            expect($mean)->toBeCloseTo(71.14, 2);
        });

        it('calculates median correctly for odd number of values', function () {
            $values = [60, 65, 70, 75, 80];
            $median = $this->trendService->calculateMedian($values);

            expect($median)->toBe(70.0);
        });

        it('calculates median correctly for even number of values', function () {
            $values = [60, 65, 70, 75];
            $median = $this->trendService->calculateMedian($values);

            expect($median)->toBe(67.5);
        });

        it('calculates standard deviation correctly', function () {
            $values = [70, 72, 68, 75, 69];
            $stdDev = $this->trendService->calculateStandardDeviation($values);

            expect($stdDev)->toBeCloseTo(2.61, 2);
        });

        it('calculates min and max values', function () {
            $values = [65, 80, 55, 90, 75];

            expect($this->trendService->calculateMin($values))->toBe(55);
            expect($this->trendService->calculateMax($values))->toBe(90);
        });

        it('calculates range correctly', function () {
            $values = [65, 80, 55, 90, 75];
            $range = $this->trendService->calculateRange($values);

            expect($range)->toBe(35); // 90 - 55
        });
    });

    describe('Trend Direction Detection', function () {
        it('detects increasing trend correctly', function () {
            // Create increasing heart rate data
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            for ($i = 0; $i < 10; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (65 + ($i * 2)), // Steadily increasing
                    'measured_at' => now()->subDays(10 - $i),
                ]);
            }

            $trend = $this->trendService->analyzeTrend($this->user->id, $heartRateType->id, 30);

            expect($trend['direction'])->toBe('increasing');
            expect($trend['slope'])->toBeGreaterThan(0);
            expect($trend['confidence'])->toBeGreaterThan(0.8);
        });

        it('detects decreasing trend correctly', function () {
            $weightType = $this->vitalSignTypes->where('name', 'weight')->first();

            for ($i = 0; $i < 10; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $weightType->id,
                    'value_primary' => (string) (75 - ($i * 0.5)), // Gradually decreasing
                    'measured_at' => now()->subDays(10 - $i),
                ]);
            }

            $trend = $this->trendService->analyzeTrend($this->user->id, $weightType->id, 30);

            expect($trend['direction'])->toBe('decreasing');
            expect($trend['slope'])->toBeLessThan(0);
            expect($trend['confidence'])->toBeGreaterThan(0.8);
        });

        it('detects stable pattern correctly', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            for ($i = 0; $i < 10; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (72 + rand(-2, 2)), // Stable with minor variations
                    'measured_at' => now()->subDays(10 - $i),
                ]);
            }

            $trend = $this->trendService->analyzeTrend($this->user->id, $heartRateType->id, 30);

            expect($trend['direction'])->toBe('stable');
            expect(abs($trend['slope']))->toBeLessThan(0.1);
        });

        it('detects volatile pattern with high variability', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            $volatileValues = [65, 85, 60, 90, 55, 95, 50, 100, 45, 105];
            for ($i = 0; $i < count($volatileValues); $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) $volatileValues[$i],
                    'measured_at' => now()->subDays(10 - $i),
                ]);
            }

            $trend = $this->trendService->analyzeTrend($this->user->id, $heartRateType->id, 30);

            expect($trend['direction'])->toBe('volatile');
            expect($trend['variability'])->toBeGreaterThan(15);
        });
    });

    describe('Linear Regression Analysis', function () {
        it('calculates regression slope and intercept correctly', function () {
            // Create data with known linear relationship: y = 2x + 60
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            for ($i = 1; $i <= 10; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (2 * $i + 60), // Linear relationship
                    'measured_at' => now()->subDays(11 - $i),
                ]);
            }

            $regression = $this->trendService->calculateLinearRegression($this->user->id, $heartRateType->id, 30);

            expect($regression['slope'])->toBeCloseTo(2.0, 1);
            expect($regression['intercept'])->toBeCloseTo(60.0, 1);
            expect($regression['r_squared'])->toBeGreaterThan(0.95); // Strong correlation
        });

        it('calculates correlation coefficient correctly', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Perfect positive correlation
            for ($i = 1; $i <= 5; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (70 + $i),
                    'measured_at' => now()->subDays(6 - $i),
                ]);
            }

            $correlation = $this->trendService->calculateCorrelation($this->user->id, $heartRateType->id, 30);

            expect($correlation)->toBeCloseTo(1.0, 1); // Perfect positive correlation
        });

        it('identifies trend significance with confidence intervals', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Create statistically significant increasing trend
            for ($i = 0; $i < 20; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (70 + ($i * 1.5) + rand(-2, 2)), // Clear trend with noise
                    'measured_at' => now()->subDays(20 - $i),
                ]);
            }

            $analysis = $this->trendService->calculateTrendSignificance($this->user->id, $heartRateType->id, 30);

            expect($analysis['is_significant'])->toBeTrue();
            expect($analysis['p_value'])->toBeLessThan(0.05);
            expect($analysis['confidence_interval']['lower'])->toBeGreaterThan(0);
            expect($analysis['confidence_interval']['upper'])->toBeGreaterThan($analysis['confidence_interval']['lower']);
        });
    });

    describe('Time Series Analysis', function () {
        it('identifies seasonal patterns in data', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Create data with weekly pattern (higher on weekdays, lower on weekends)
            for ($i = 0; $i < 28; $i++) { // 4 weeks of data
                $dayOfWeek = now()->subDays(28 - $i)->dayOfWeek;
                $baseValue = ($dayOfWeek >= 1 && $dayOfWeek <= 5) ? 75 : 65; // Weekday vs weekend

                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) ($baseValue + rand(-3, 3)),
                    'measured_at' => now()->subDays(28 - $i),
                ]);
            }

            $seasonal = $this->trendService->detectSeasonalPatterns($this->user->id, $heartRateType->id, 30);

            expect($seasonal['has_pattern'])->toBeTrue();
            expect($seasonal['pattern_type'])->toBe('weekly');
            expect($seasonal['pattern_strength'])->toBeGreaterThan(0.6);
        });

        it('calculates moving averages correctly', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            $values = [70, 72, 74, 76, 78, 80, 82];
            for ($i = 0; $i < count($values); $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) $values[$i],
                    'measured_at' => now()->subDays(count($values) - $i),
                ]);
            }

            $movingAvg = $this->trendService->calculateMovingAverage($this->user->id, $heartRateType->id, 3, 30);

            // 3-point moving average of [70, 72, 74, 76, 78, 80, 82]
            // Should give: [72, 74, 76, 78, 80] (for points 2-6)
            expect(count($movingAvg))->toBe(5);
            expect($movingAvg[0])->toBeCloseTo(72.0, 1);
            expect($movingAvg[4])->toBeCloseTo(80.0, 1);
        });

        it('detects outliers in time series data', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Normal values with one clear outlier
            $values = [70, 72, 71, 130, 69, 73, 70]; // 130 is an outlier
            for ($i = 0; $i < count($values); $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) $values[$i],
                    'measured_at' => now()->subDays(count($values) - $i),
                ]);
            }

            $outliers = $this->trendService->detectOutliers($this->user->id, $heartRateType->id, 30);

            expect(count($outliers))->toBe(1);
            expect($outliers[0]['value'])->toBe('130');
            expect($outliers[0]['z_score'])->toBeGreaterThan(2);
        });
    });

    describe('Blood Pressure Specific Analysis', function () {
        it('analyzes blood pressure trends for both systolic and diastolic', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            // Systolic increasing, diastolic stable
            for ($i = 0; $i < 10; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $bloodPressureType->id,
                    'value_primary' => (string) (110 + ($i * 3)), // Increasing systolic
                    'value_secondary' => (string) (75 + rand(-2, 2)), // Stable diastolic
                    'measured_at' => now()->subDays(10 - $i),
                ]);
            }

            $analysis = $this->trendService->analyzeBloodPressureTrends($this->user->id, $bloodPressureType->id, 30);

            expect($analysis['systolic']['direction'])->toBe('increasing');
            expect($analysis['diastolic']['direction'])->toBe('stable');
            expect($analysis['pulse_pressure']['trend'])->toBe('increasing'); // Difference increasing
        });

        it('calculates pulse pressure trends correctly', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            // Pulse pressure = systolic - diastolic
            $readings = [
                ['systolic' => 120, 'diastolic' => 80], // PP = 40
                ['systolic' => 125, 'diastolic' => 80], // PP = 45
                ['systolic' => 130, 'diastolic' => 80], // PP = 50
                ['systolic' => 135, 'diastolic' => 80], // PP = 55
            ];

            foreach ($readings as $i => $reading) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $bloodPressureType->id,
                    'value_primary' => (string) $reading['systolic'],
                    'value_secondary' => (string) $reading['diastolic'],
                    'measured_at' => now()->subDays(count($readings) - $i),
                ]);
            }

            $pulsePressure = $this->trendService->calculatePulsePressureTrend($this->user->id, $bloodPressureType->id, 30);

            expect($pulsePressure['trend'])->toBe('increasing');
            expect($pulsePressure['average'])->toBeCloseTo(47.5, 1);
            expect($pulsePressure['values'])->toEqual([40.0, 45.0, 50.0, 55.0]);
        });
    });

    describe('Period Comparison Analysis', function () {
        it('compares current period to previous period', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Previous 7 days: average ~70
            for ($i = 14; $i >= 8; $i--) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (70 + rand(-3, 3)),
                    'measured_at' => now()->subDays($i),
                ]);
            }

            // Recent 7 days: average ~80 (higher)
            for ($i = 7; $i >= 1; $i--) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (80 + rand(-3, 3)),
                    'measured_at' => now()->subDays($i),
                ]);
            }

            $comparison = $this->trendService->comparePeriods($this->user->id, $heartRateType->id, 7);

            expect($comparison['change_direction'])->toBe('increased');
            expect($comparison['change_percentage'])->toBeGreaterThan(10);
            expect($comparison['is_significant'])->toBeTrue();
            expect($comparison['current_average'])->toBeGreaterThan($comparison['previous_average']);
        });

        it('identifies improvement or deterioration in health metrics', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Create data showing improvement (getting closer to normal range)
            // Previous period: values around 110 (high)
            for ($i = 14; $i >= 8; $i--) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (110 + rand(-5, 5)),
                    'measured_at' => now()->subDays($i),
                ]);
            }

            // Recent period: values around 80 (normal range)
            for ($i = 7; $i >= 1; $i--) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (80 + rand(-5, 5)),
                    'measured_at' => now()->subDays($i),
                ]);
            }

            $health = $this->trendService->analyzeHealthImprovement($this->user->id, $heartRateType->id, 7);

            expect($health['status'])->toBe('improved');
            expect($health['improvement_score'])->toBeGreaterThan(0.7);
            expect($health['closer_to_normal'])->toBeTrue();
        });
    });

    describe('Advanced Analytics', function () {
        it('calculates trend prediction for future values', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Create clear linear increasing trend
            for ($i = 0; $i < 15; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (60 + ($i * 2)), // Predictable increase
                    'measured_at' => now()->subDays(15 - $i),
                ]);
            }

            $prediction = $this->trendService->predictFutureTrend($this->user->id, $heartRateType->id, 30, 7); // Predict 7 days ahead

            expect($prediction['predicted_values'])->toHaveCount(7);
            expect($prediction['predicted_values'][6])->toBeGreaterThan(90); // Should continue increasing
            expect($prediction['confidence_level'])->toBeGreaterThan(0.8);
            expect($prediction['trend_continuation'])->toBeTrue();
        });

        it('identifies health risk patterns', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            // Create pattern of increasing blood pressure over time
            for ($i = 0; $i < 20; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $bloodPressureType->id,
                    'value_primary' => (string) (130 + ($i * 2)), // Progressively higher
                    'value_secondary' => (string) (80 + $i),
                    'measured_at' => now()->subDays(20 - $i),
                ]);
            }

            $risk = $this->trendService->assessHealthRisk($this->user->id, $bloodPressureType->id, 30);

            expect($risk['risk_level'])->toBe('high');
            expect($risk['risk_factors'])->toContain('increasing_trend');
            expect($risk['risk_factors'])->toContain('values_outside_normal');
            expect($risk['risk_score'])->toBeGreaterThan(0.7);
        });

        it('calculates variability and consistency metrics', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Create data with known variability
            $values = [70, 75, 65, 80, 60, 85, 55, 90];
            foreach ($values as $i => $value) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) $value,
                    'measured_at' => now()->subDays(count($values) - $i),
                ]);
            }

            $variability = $this->trendService->calculateVariabilityMetrics($this->user->id, $heartRateType->id, 30);

            expect($variability['coefficient_of_variation'])->toBeGreaterThan(0.1);
            expect($variability['consistency_score'])->toBeLessThan(0.5); // Low consistency due to high variability
            expect($variability['stability_rating'])->toBe('unstable');
        });
    });

    describe('Edge Cases and Error Handling', function () {
        it('handles insufficient data gracefully', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Only create 2 data points (insufficient for trend analysis)
            VitalSignsRecord::factory()->count(2)->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'measured_at' => now()->subDays(rand(1, 7)),
            ]);

            $trend = $this->trendService->analyzeTrend($this->user->id, $heartRateType->id, 30);

            expect($trend['direction'])->toBe('insufficient_data');
            expect($trend['confidence'])->toBe(0);
            expect($trend['message'])->toContain('More data points needed');
        });

        it('handles identical values correctly', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // All identical values
            for ($i = 0; $i < 10; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => '72', // All same value
                    'measured_at' => now()->subDays(10 - $i),
                ]);
            }

            $trend = $this->trendService->analyzeTrend($this->user->id, $heartRateType->id, 30);

            expect($trend['direction'])->toBe('stable');
            expect($trend['slope'])->toBe(0);
            expect($trend['variability'])->toBe(0);
        });

        it('validates calculation inputs', function () {
            // Test with empty array
            expect($this->trendService->calculateMean([]))->toBeNull();

            // Test with null values
            expect($this->trendService->calculateStandardDeviation([null, null]))->toBeNull();

            // Test with non-numeric values
            $result = $this->trendService->calculateMean(['a', 'b', 'c']);
            expect($result)->toBeNull();
        });
    });
});
