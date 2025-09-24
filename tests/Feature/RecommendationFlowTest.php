<?php

use App\Jobs\GenerateRecommendations;
use App\Models\Recommendation;
use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('Recommendation Generation Flow', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'name' => 'Alex Health',
            'email' => 'alex@example.com',
        ]);

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
                'normal_range_min' => 60,
                'normal_range_max' => 100,
                'warning_range_min' => 40,
                'warning_range_max' => 120,
            ]),
        ]);
    });

    it('generates health alert recommendations for abnormal readings', function () {
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        // Create multiple flagged readings to trigger health alerts
        for ($i = 0; $i < 3; $i++) {
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '130', // Above warning range
                'is_flagged' => true,
                'measured_at' => now()->subDays($i),
            ]);
        }

        // Run recommendation generation
        $job = new GenerateRecommendations($this->user->id, false);
        $job->handle();

        // Verify health alert recommendations were generated
        $recommendations = Recommendation::where('user_id', $this->user->id)
            ->where('recommendation_type', 'health_alert')
            ->get();

        expect($recommendations->count())->toBeGreaterThan(0);

        $healthAlert = $recommendations->first();
        expect($healthAlert->title)->toContain('Abnormal');
        expect($healthAlert->priority)->toBeIn(['medium', 'high']);
        expect($healthAlert->is_active)->toBeTrue();
    });

    it('generates trend observation recommendations for significant patterns', function () {
        $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

        // Create readings with an increasing trend
        for ($i = 0; $i < 10; $i++) {
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $bloodPressureType->id,
                'value_primary' => (string) (100 + ($i * 3)), // Gradually increasing
                'value_secondary' => (string) (70 + ($i * 2)),
                'measured_at' => now()->subDays(10 - $i),
            ]);
        }

        // Run recommendation generation
        $job = new GenerateRecommendations($this->user->id, false);
        $job->handle();

        // Verify trend observation recommendations were generated
        $trendRecommendations = Recommendation::where('user_id', $this->user->id)
            ->where('recommendation_type', 'trend_observation')
            ->get();

        expect($trendRecommendations->count())->toBeGreaterThan(0);

        $trendObservation = $trendRecommendations->first();
        expect($trendObservation->title)->toContain('Trend');
        expect($trendObservation->data)->toHaveKey('trend_direction');
        expect($trendObservation->data['trend_direction'])->toBe('increasing');
    });

    it('generates lifestyle recommendations for monitoring patterns', function () {
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        // Create sparse readings (low frequency) to trigger lifestyle recommendations
        VitalSignsRecord::factory()->create([
            'user_id' => $this->user->id,
            'vital_sign_type_id' => $heartRateType->id,
            'measured_at' => now()->subDays(25),
        ]);

        VitalSignsRecord::factory()->create([
            'user_id' => $this->user->id,
            'vital_sign_type_id' => $heartRateType->id,
            'measured_at' => now()->subDays(20),
        ]);

        VitalSignsRecord::factory()->create([
            'user_id' => $this->user->id,
            'vital_sign_type_id' => $heartRateType->id,
            'measured_at' => now()->subDays(15),
        ]);

        // Run recommendation generation with 30-day lookback
        $job = new GenerateRecommendations($this->user->id, false, [
            'lookback_days' => 30,
            'recommendation_types' => ['lifestyle'],
        ]);
        $job->handle();

        // Verify lifestyle recommendations were generated
        $lifestyleRecommendations = Recommendation::where('user_id', $this->user->id)
            ->where('recommendation_type', 'lifestyle')
            ->get();

        expect($lifestyleRecommendations->count())->toBeGreaterThan(0);

        $lifestyleRec = $lifestyleRecommendations->first();
        expect($lifestyleRec->title)->toContain('Regular Monitoring');
        expect($lifestyleRec->priority)->toBe('low');
    });

    it('generates goal progress recommendations for excellent control', function () {
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        // Create readings very close to optimal (75 bpm, near center of 60-100 range)
        for ($i = 0; $i < 7; $i++) {
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => (string) (75 + rand(-2, 2)), // Very close to optimal
                'measured_at' => now()->subDays($i),
            ]);
        }

        // Run recommendation generation
        $job = new GenerateRecommendations($this->user->id, false, [
            'recommendation_types' => ['goal_progress'],
        ]);
        $job->handle();

        // Verify goal progress recommendations were generated
        $goalRecommendations = Recommendation::where('user_id', $this->user->id)
            ->where('recommendation_type', 'goal_progress')
            ->get();

        expect($goalRecommendations->count())->toBeGreaterThan(0);

        $goalRec = $goalRecommendations->first();
        expect($goalRec->title)->toContain('Excellent');
        expect($goalRec->data)->toHaveKey('performance');
        expect($goalRec->data['performance'])->toBe('excellent');
    });

    it('prevents duplicate recommendations within time window', function () {
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        // Create flagged readings
        VitalSignsRecord::factory()->create([
            'user_id' => $this->user->id,
            'vital_sign_type_id' => $heartRateType->id,
            'value_primary' => '130',
            'is_flagged' => true,
        ]);

        // Run recommendation generation twice
        $job1 = new GenerateRecommendations($this->user->id, false);
        $job1->handle();

        $job2 = new GenerateRecommendations($this->user->id, false);
        $job2->handle();

        // Should not create duplicate recommendations
        $recommendations = Recommendation::where('user_id', $this->user->id)->get();
        $titles = $recommendations->pluck('title')->unique();

        expect($recommendations->count())->toBe($titles->count()); // No duplicates
    });

    it('allows users to view recommendations dashboard', function () {
        // Create some recommendations
        Recommendation::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($this->user)->get('/recommendations');

        $response->assertSuccessful();
        $response->assertInertia(function ($page) {
            $page->component('Recommendations/Index');
        });

        // Test API endpoint
        $response = $this->actingAs($this->user)->getJson('/api/recommendations');

        $response->assertSuccessful();
        $data = $response->json();

        expect($data)->toHaveKey('data');
        expect(count($data['data']))->toBe(3);
    });

    it('allows users to mark recommendations as read', function () {
        $recommendation = Recommendation::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/recommendations/{$recommendation->id}/read");

        $response->assertSuccessful();

        $recommendation->refresh();
        expect($recommendation->read_at)->not->toBeNull();
    });

    it('allows users to dismiss recommendations', function () {
        $recommendation = Recommendation::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'dismissed_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/recommendations/{$recommendation->id}/dismiss", [
                'dismissal_reason' => 'Already following this advice',
            ]);

        $response->assertSuccessful();

        $recommendation->refresh();
        expect($recommendation->dismissed_at)->not->toBeNull();
        expect($recommendation->dismissal_reason)->toBe('Already following this advice');
        expect($recommendation->is_active)->toBeFalse();
    });

    it('filters recommendations by type and status', function () {
        // Create different types of recommendations
        Recommendation::factory()->create([
            'user_id' => $this->user->id,
            'recommendation_type' => 'health_alert',
            'priority' => 'high',
            'is_active' => true,
        ]);

        Recommendation::factory()->create([
            'user_id' => $this->user->id,
            'recommendation_type' => 'lifestyle',
            'priority' => 'low',
            'is_active' => true,
            'read_at' => now(),
        ]);

        Recommendation::factory()->create([
            'user_id' => $this->user->id,
            'recommendation_type' => 'goal_progress',
            'is_active' => false,
            'dismissed_at' => now(),
        ]);

        // Test filtering by type
        $response = $this->actingAs($this->user)
            ->getJson('/api/recommendations?type=health_alert');

        $response->assertSuccessful();
        $data = $response->json();
        expect(count($data['data']))->toBe(1);
        expect($data['data'][0]['recommendation_type'])->toBe('health_alert');

        // Test filtering by status
        $response = $this->actingAs($this->user)
            ->getJson('/api/recommendations?status=unread');

        $response->assertSuccessful();
        $data = $response->json();
        expect(count($data['data']))->toBe(1); // Only unread active recommendation
    });

    it('supports bulk operations on recommendations', function () {
        $recommendations = Recommendation::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'read_at' => null,
        ]);

        $recommendationIds = $recommendations->pluck('id')->toArray();

        // Bulk mark as read
        $response = $this->actingAs($this->user)
            ->postJson('/api/recommendations/bulk-read', [
                'recommendation_ids' => $recommendationIds,
            ]);

        $response->assertSuccessful();

        // Verify all were marked as read
        $updatedRecommendations = Recommendation::whereIn('id', $recommendationIds)->get();
        foreach ($updatedRecommendations as $rec) {
            expect($rec->read_at)->not->toBeNull();
        }
    });

    it('expires old recommendations automatically', function () {
        // Create old recommendation
        $oldRecommendation = Recommendation::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'created_at' => now()->subDays(35),
            'expires_at' => now()->subDays(5),
        ]);

        // Run recommendation generation with cleanup
        $job = new GenerateRecommendations($this->user->id, false, [
            'force_regenerate' => true,
        ]);
        $job->handle();

        $oldRecommendation->refresh();
        expect($oldRecommendation->is_active)->toBeFalse();
        expect($oldRecommendation->dismissed_at)->not->toBeNull();
        expect($oldRecommendation->dismissal_reason)->toBe('auto_cleanup');
    });

    it('queues recommendation generation jobs properly', function () {
        Queue::fake();

        // Test queuing job for specific user
        $this->artisan('health:generate-recommendations', [
            '--user' => $this->user->id,
        ])->assertSuccessful();

        Queue::assertPushed(GenerateRecommendations::class, function ($job) {
            return $job->userId === $this->user->id && ! $job->processAllUsers;
        });

        // Test queuing job for all users
        Queue::fake();

        $this->artisan('health:generate-recommendations')
            ->assertSuccessful();

        Queue::assertPushed(GenerateRecommendations::class, function ($job) {
            return $job->userId === null && $job->processAllUsers;
        });
    });

    it('handles recommendation generation with different options', function () {
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        // Create readings
        for ($i = 0; $i < 5; $i++) {
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '70',
                'measured_at' => now()->subDays($i),
            ]);
        }

        // Test with custom options
        $job = new GenerateRecommendations($this->user->id, false, [
            'lookback_days' => 7,
            'min_readings_required' => 3,
            'recommendation_types' => ['lifestyle', 'goal_progress'],
        ]);
        $job->handle();

        $recommendations = Recommendation::where('user_id', $this->user->id)->get();

        // Should have generated some recommendations
        expect($recommendations->count())->toBeGreaterThan(0);

        // Should only include specified types
        $types = $recommendations->pluck('recommendation_type')->unique()->toArray();
        foreach ($types as $type) {
            expect($type)->toBeIn(['lifestyle', 'goal_progress']);
        }
    });

    it('integrates recommendations with dashboard display', function () {
        // Create recommendations with different priorities
        Recommendation::factory()->create([
            'user_id' => $this->user->id,
            'priority' => 'high',
            'recommendation_type' => 'health_alert',
            'is_active' => true,
        ]);

        Recommendation::factory()->create([
            'user_id' => $this->user->id,
            'priority' => 'low',
            'recommendation_type' => 'lifestyle',
            'is_active' => true,
        ]);

        // Test dashboard shows recommendations
        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertInertia(function ($page) {
            $page->has('recommendations')
                ->where('recommendations.0.priority', 'high'); // Should prioritize high priority
        });
    });

    it('prevents unauthorized access to other users recommendations', function () {
        $otherUser = User::factory()->create();
        $recommendation = Recommendation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Other user cannot access the recommendation
        $response = $this->actingAs($otherUser)
            ->getJson("/api/recommendations/{$recommendation->id}");

        $response->assertForbidden();

        // Other user cannot modify the recommendation
        $response = $this->actingAs($otherUser)
            ->postJson("/api/recommendations/{$recommendation->id}/read");

        $response->assertForbidden();
    });

    it('tracks recommendation engagement analytics', function () {
        $recommendation = Recommendation::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'created_at' => now()->subDays(2),
        ]);

        // User views the recommendation
        $this->actingAs($this->user)
            ->getJson("/api/recommendations/{$recommendation->id}");

        // User marks it as read
        $this->actingAs($this->user)
            ->postJson("/api/recommendations/{$recommendation->id}/read");

        $recommendation->refresh();

        // Verify engagement tracking
        expect($recommendation->read_at)->not->toBeNull();

        // In a real implementation, this would track:
        // - View time
        // - Read time
        // - Click-through rates
        // - Action completion rates
    });
});
