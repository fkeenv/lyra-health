<?php

use App\Jobs\GenerateRecommendations;
use App\Models\Recommendation;
use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Recommendation Logic Unit Tests', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'date_of_birth' => '1980-06-15', // 44 years old
            'gender' => 'male',
            'height' => 175,
        ]);

        $this->recommendationService = new RecommendationService;

        $this->vitalSignTypes = collect([
            VitalSignType::factory()->create([
                'name' => 'blood_pressure',
                'display_name' => 'Blood Pressure',
                'unit_primary' => 'mmHg',
                'unit_secondary' => 'mmHg',
                'has_secondary_value' => true,
                'normal_range_min' => 90,
                'normal_range_max' => 140,
                'warning_range_min' => 80,
                'warning_range_max' => 160,
            ]),
            VitalSignType::factory()->create([
                'name' => 'heart_rate',
                'display_name' => 'Heart Rate',
                'unit_primary' => 'bpm',
                'has_secondary_value' => false,
                'normal_range_min' => 60,
                'normal_range_max' => 100,
                'warning_range_min' => 40,
                'warning_range_max' => 120,
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

    describe('Health Alert Recommendation Logic', function () {
        it('generates health alert for multiple abnormal readings', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Create multiple flagged readings
            for ($i = 0; $i < 3; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => '130', // Above warning range
                    'is_flagged' => true,
                    'measured_at' => now()->subDays($i),
                ]);
            }

            $recommendations = $this->recommendationService->generateHealthAlerts($this->user->id);

            expect($recommendations)->toHaveCount(1);
            expect($recommendations[0]['recommendation_type'])->toBe('health_alert');
            expect($recommendations[0]['priority'])->toBeIn(['medium', 'high']);
            expect($recommendations[0]['title'])->toContain('Abnormal');
            expect($recommendations[0]['data']['vital_sign'])->toBe('heart_rate');
            expect($recommendations[0]['data']['reading_count'])->toBe(3);
        });

        it('determines correct priority based on severity', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Extremely high readings (critical)
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '180', // Extremely high
                'is_flagged' => true,
                'measured_at' => now(),
            ]);

            $alerts = $this->recommendationService->generateHealthAlerts($this->user->id);
            expect($alerts[0]['priority'])->toBe('high');

            // Clear existing records
            VitalSignsRecord::where('user_id', $this->user->id)->delete();

            // Moderately high readings
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '125', // Moderately high
                'is_flagged' => true,
                'measured_at' => now(),
            ]);

            $alerts = $this->recommendationService->generateHealthAlerts($this->user->id);
            expect($alerts[0]['priority'])->toBe('medium');
        });

        it('includes relevant context and suggestions', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $bloodPressureType->id,
                'value_primary' => '170',
                'value_secondary' => '95',
                'is_flagged' => true,
                'measured_at' => now(),
            ]);

            $alerts = $this->recommendationService->generateHealthAlerts($this->user->id);

            expect($alerts[0]['message'])->toContain('blood pressure');
            expect($alerts[0]['message'])->toContain('consult');
            expect($alerts[0]['data']['suggestions'])->toContain('healthcare provider');
        });
    });

    describe('Trend Observation Recommendation Logic', function () {
        it('generates trend observation for increasing pattern', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            // Create readings with increasing trend
            for ($i = 0; $i < 10; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $bloodPressureType->id,
                    'value_primary' => (string) (100 + ($i * 3)), // Gradually increasing
                    'value_secondary' => (string) (70 + ($i * 2)),
                    'measured_at' => now()->subDays(10 - $i),
                ]);
            }

            $trendObs = $this->recommendationService->generateTrendObservations($this->user->id);

            expect($trendObs)->toHaveCount(1);
            expect($trendObs[0]['recommendation_type'])->toBe('trend_observation');
            expect($trendObs[0]['data']['trend_direction'])->toBe('increasing');
            expect($trendObs[0]['data']['vital_sign'])->toBe('blood_pressure');
            expect($trendObs[0]['title'])->toContain('Trend');
        });

        it('generates different messages for different trend types', function () {
            $weightType = $this->vitalSignTypes->where('name', 'weight')->first();

            // Create decreasing weight trend
            for ($i = 0; $i < 8; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $weightType->id,
                    'value_primary' => (string) (75 - ($i * 0.5)), // Gradually decreasing
                    'measured_at' => now()->subDays(8 - $i),
                ]);
            }

            $trendObs = $this->recommendationService->generateTrendObservations($this->user->id);

            expect($trendObs[0]['data']['trend_direction'])->toBe('decreasing');
            expect($trendObs[0]['message'])->toContain('decreasing');
            expect($trendObs[0]['message'])->toContain('weight');
        });

        it('calculates trend strength and duration correctly', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Strong consistent trend over 14 days
            for ($i = 0; $i < 14; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (60 + ($i * 2)), // Strong linear trend
                    'measured_at' => now()->subDays(14 - $i),
                ]);
            }

            $trendObs = $this->recommendationService->generateTrendObservations($this->user->id);

            expect($trendObs[0]['data']['trend_strength'])->toBeGreaterThan(0.8);
            expect($trendObs[0]['data']['duration_days'])->toBe(14);
            expect($trendObs[0]['data']['confidence'])->toBeGreaterThan(0.9);
        });
    });

    describe('Lifestyle Recommendation Logic', function () {
        it('generates lifestyle recommendation for infrequent monitoring', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Create sparse readings (only 3 in 30 days)
            $dates = [25, 18, 8]; // Days ago
            foreach ($dates as $daysAgo) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => '72',
                    'measured_at' => now()->subDays($daysAgo),
                ]);
            }

            $lifestyle = $this->recommendationService->generateLifestyleRecommendations($this->user->id, 30);

            expect($lifestyle)->toHaveCount(1);
            expect($lifestyle[0]['recommendation_type'])->toBe('lifestyle');
            expect($lifestyle[0]['priority'])->toBe('low');
            expect($lifestyle[0]['title'])->toContain('Regular Monitoring');
            expect($lifestyle[0]['data']['monitoring_frequency'])->toBe('low');
            expect($lifestyle[0]['data']['suggestion'])->toContain('regular');
        });

        it('suggests optimal monitoring frequency based on health status', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            // Create readings that are consistently high-normal
            for ($i = 0; $i < 5; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $bloodPressureType->id,
                    'value_primary' => '135', // High-normal
                    'value_secondary' => '85',
                    'measured_at' => now()->subDays(rand(20, 30)),
                ]);
            }

            $lifestyle = $this->recommendationService->generateLifestyleRecommendations($this->user->id, 30);

            expect($lifestyle[0]['data']['suggested_frequency'])->toBe('daily');
            expect($lifestyle[0]['message'])->toContain('daily monitoring');
        });

        it('provides personalized advice based on user demographics', function () {
            // Create older user
            $olderUser = User::factory()->create([
                'date_of_birth' => '1950-01-01', // 74 years old
                'gender' => 'female',
            ]);

            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            VitalSignsRecord::factory()->create([
                'user_id' => $olderUser->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '65',
                'measured_at' => now()->subDays(20),
            ]);

            $lifestyle = $this->recommendationService->generateLifestyleRecommendations($olderUser->id, 30);

            expect($lifestyle[0]['data']['age_specific'])->toBeTrue();
            expect($lifestyle[0]['message'])->toContain('age');
        });
    });

    describe('Goal Progress Recommendation Logic', function () {
        it('generates goal progress for excellent control', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Create readings very close to optimal (75 bpm, center of 60-100 range)
            for ($i = 0; $i < 7; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (75 + rand(-2, 2)), // Very close to optimal
                    'measured_at' => now()->subDays($i),
                ]);
            }

            $goalProgress = $this->recommendationService->generateGoalProgressRecommendations($this->user->id);

            expect($goalProgress)->toHaveCount(1);
            expect($goalProgress[0]['recommendation_type'])->toBe('goal_progress');
            expect($goalProgress[0]['title'])->toContain('Excellent');
            expect($goalProgress[0]['data']['performance'])->toBe('excellent');
            expect($goalProgress[0]['data']['consistency_score'])->toBeGreaterThan(0.8);
        });

        it('calculates performance metrics accurately', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            // Mix of good and borderline readings
            $readings = [
                ['systolic' => 115, 'diastolic' => 75], // Good
                ['systolic' => 135, 'diastolic' => 85], // Borderline
                ['systolic' => 120, 'diastolic' => 80], // Good
                ['systolic' => 130, 'diastolic' => 82], // Borderline
                ['systolic' => 118, 'diastolic' => 78], // Good
            ];

            foreach ($readings as $i => $reading) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $bloodPressureType->id,
                    'value_primary' => (string) $reading['systolic'],
                    'value_secondary' => (string) $reading['diastolic'],
                    'measured_at' => now()->subDays($i),
                ]);
            }

            $goalProgress = $this->recommendationService->generateGoalProgressRecommendations($this->user->id);

            expect($goalProgress[0]['data']['performance'])->toBe('good');
            expect($goalProgress[0]['data']['readings_in_range'])->toBe(3); // 3 out of 5 good
            expect($goalProgress[0]['data']['improvement_areas'])->toContain('consistency');
        });

        it('provides motivational messaging for progress', function () {
            $weightType = $this->vitalSignTypes->where('name', 'weight')->first();

            // Consistent weight maintenance
            for ($i = 0; $i < 10; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $weightType->id,
                    'value_primary' => (string) (70 + rand(-1, 1)), // Very stable
                    'measured_at' => now()->subDays($i),
                ]);
            }

            $goalProgress = $this->recommendationService->generateGoalProgressRecommendations($this->user->id);

            expect($goalProgress[0]['message'])->toContain('maintaining');
            expect($goalProgress[0]['message'])->toContain('keep up');
            expect($goalProgress[0]['data']['motivation_type'])->toBe('maintenance');
        });
    });

    describe('Recommendation Deduplication Logic', function () {
        it('prevents duplicate recommendations within time window', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Create flagged reading
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '130',
                'is_flagged' => true,
                'measured_at' => now(),
            ]);

            // First generation
            $recommendations1 = $this->recommendationService->generateHealthAlerts($this->user->id);
            expect($recommendations1)->toHaveCount(1);

            // Save the first recommendation
            Recommendation::create([
                'user_id' => $this->user->id,
                'recommendation_type' => 'health_alert',
                'title' => $recommendations1[0]['title'],
                'message' => $recommendations1[0]['message'],
                'priority' => $recommendations1[0]['priority'],
                'data' => $recommendations1[0]['data'],
                'is_active' => true,
                'created_at' => now(),
            ]);

            // Second generation (should detect duplicate)
            $recommendations2 = $this->recommendationService->generateHealthAlerts($this->user->id);
            expect($recommendations2)->toHaveCount(0); // Should be filtered out
        });

        it('allows similar recommendations after expiry period', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '130',
                'is_flagged' => true,
                'measured_at' => now(),
            ]);

            // Create old recommendation (beyond deduplication window)
            Recommendation::factory()->create([
                'user_id' => $this->user->id,
                'recommendation_type' => 'health_alert',
                'title' => 'Abnormal Heart Rate Detected',
                'is_active' => true,
                'created_at' => now()->subDays(8), // Older than 7-day window
            ]);

            $newRecommendations = $this->recommendationService->generateHealthAlerts($this->user->id);
            expect($newRecommendations)->toHaveCount(1); // Should be allowed
        });
    });

    describe('Recommendation Prioritization Logic', function () {
        it('prioritizes high-severity health alerts over lifestyle suggestions', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Critical health alert
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '180', // Critical
                'is_flagged' => true,
                'measured_at' => now(),
            ]);

            // Lifestyle issue (infrequent monitoring)
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '72',
                'measured_at' => now()->subDays(20),
            ]);

            $allRecommendations = $this->recommendationService->generateAllRecommendations($this->user->id);

            // Health alert should be first (highest priority)
            expect($allRecommendations[0]['recommendation_type'])->toBe('health_alert');
            expect($allRecommendations[0]['priority'])->toBe('high');

            // Lifestyle should be later or absent due to higher priority alert
            $lifestyleCount = collect($allRecommendations)->where('recommendation_type', 'lifestyle')->count();
            expect($lifestyleCount)->toBeLessThanOrEqual(1);
        });

        it('limits total number of active recommendations per user', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Create many different issues that could trigger recommendations
            for ($i = 0; $i < 20; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => (string) (130 + rand(0, 50)), // Various high values
                    'is_flagged' => true,
                    'measured_at' => now()->subDays($i),
                ]);
            }

            $allRecommendations = $this->recommendationService->generateAllRecommendations($this->user->id);

            // Should limit to reasonable number (e.g., max 5 active recommendations)
            expect(count($allRecommendations))->toBeLessThanOrEqual(5);
        });
    });

    describe('Recommendation Personalization Logic', function () {
        it('adapts recommendations based on user history and patterns', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            // User with history of well-controlled BP suddenly has spike
            for ($i = 30; $i > 5; $i--) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $bloodPressureType->id,
                    'value_primary' => (string) (115 + rand(-5, 5)), // Well controlled
                    'value_secondary' => (string) (75 + rand(-3, 3)),
                    'measured_at' => now()->subDays($i),
                ]);
            }

            // Recent spike
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $bloodPressureType->id,
                'value_primary' => '160', // Sudden spike
                'value_secondary' => '95',
                'is_flagged' => true,
                'measured_at' => now(),
            ]);

            $alerts = $this->recommendationService->generateHealthAlerts($this->user->id);

            expect($alerts[0]['data']['context'])->toBe('unusual_for_user');
            expect($alerts[0]['message'])->toContain('unusual');
            expect($alerts[0]['data']['historical_control'])->toBeTrue();
        });

        it('considers user engagement patterns in recommendation frequency', function () {
            // User who frequently records but doesn't act on recommendations
            $this->user->update(['created_at' => now()->subMonths(6)]);

            // Create many readings
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();
            for ($i = 0; $i < 50; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $heartRateType->id,
                    'value_primary' => '72',
                    'measured_at' => now()->subDays($i),
                ]);
            }

            // Create many previous recommendations (shows user gets many but doesn't engage)
            Recommendation::factory()->count(10)->create([
                'user_id' => $this->user->id,
                'is_active' => false,
                'dismissed_at' => now()->subDays(rand(1, 30)),
                'created_at' => now()->subDays(rand(31, 60)),
            ]);

            $userProfile = $this->recommendationService->analyzeUserEngagement($this->user->id);

            expect($userProfile['engagement_level'])->toBe('low');
            expect($userProfile['recommendation_fatigue'])->toBeTrue();
            expect($userProfile['suggested_frequency'])->toBe('reduced');
        });
    });

    describe('Recommendation Expiry and Cleanup Logic', function () {
        it('automatically expires old recommendations', function () {
            $oldRecommendation = Recommendation::factory()->create([
                'user_id' => $this->user->id,
                'is_active' => true,
                'created_at' => now()->subDays(35), // Older than 30-day expiry
                'expires_at' => now()->subDays(5),
            ]);

            $this->recommendationService->cleanupExpiredRecommendations();

            $oldRecommendation->refresh();
            expect($oldRecommendation->is_active)->toBeFalse();
            expect($oldRecommendation->dismissed_at)->not->toBeNull();
            expect($oldRecommendation->dismissal_reason)->toBe('auto_cleanup');
        });

        it('keeps recent recommendations active', function () {
            $recentRecommendation = Recommendation::factory()->create([
                'user_id' => $this->user->id,
                'is_active' => true,
                'created_at' => now()->subDays(5),
                'expires_at' => now()->addDays(25),
            ]);

            $this->recommendationService->cleanupExpiredRecommendations();

            $recentRecommendation->refresh();
            expect($recentRecommendation->is_active)->toBeTrue();
        });
    });

    describe('Integration with GenerateRecommendations Job', function () {
        it('job generates appropriate mix of recommendation types', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();
            $weightType = $this->vitalSignTypes->where('name', 'weight')->first();

            // Create conditions for multiple recommendation types

            // Health alert condition
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '130',
                'is_flagged' => true,
                'measured_at' => now(),
            ]);

            // Goal progress condition (good control)
            for ($i = 1; $i <= 5; $i++) {
                VitalSignsRecord::factory()->create([
                    'user_id' => $this->user->id,
                    'vital_sign_type_id' => $weightType->id,
                    'value_primary' => (string) (70 + rand(-1, 1)),
                    'measured_at' => now()->subDays($i),
                ]);
            }

            $job = new GenerateRecommendations($this->user->id, false);
            $job->handle();

            $recommendations = Recommendation::where('user_id', $this->user->id)->get();

            $types = $recommendations->pluck('recommendation_type')->unique();
            expect($types->contains('health_alert'))->toBeTrue();
            expect($types->contains('goal_progress'))->toBeTrue();
            expect($recommendations->count())->toBeGreaterThan(0);
        });

        it('respects job configuration options', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '75', // Good reading
                'measured_at' => now(),
            ]);

            // Job configured for only lifestyle recommendations
            $job = new GenerateRecommendations($this->user->id, false, [
                'recommendation_types' => ['lifestyle'],
                'lookback_days' => 7,
            ]);
            $job->handle();

            $recommendations = Recommendation::where('user_id', $this->user->id)->get();
            $types = $recommendations->pluck('recommendation_type')->unique();

            expect($types->count())->toBeLessThanOrEqual(1);
            if ($types->count() > 0) {
                expect($types->first())->toBe('lifestyle');
            }
        });
    });
});
