<?php

use App\Models\Recommendation;
use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Registration and Vital Signs Flow', function () {
    beforeEach(function () {
        // Create vital sign types for testing
        $this->vitalSignTypes = collect([
            VitalSignType::factory()->create([
                'name' => 'blood_pressure',
                'display_name' => 'Blood Pressure',
                'unit_primary' => 'mmHg',
                'unit_secondary' => 'mmHg',
                'has_secondary_value' => true,
                'input_type' => 'dual_numeric',
                'normal_range_min' => 90,
                'normal_range_max' => 140,
                'warning_range_min' => 80,
                'warning_range_max' => 160,
                'is_active' => true,
            ]),
            VitalSignType::factory()->create([
                'name' => 'heart_rate',
                'display_name' => 'Heart Rate',
                'unit_primary' => 'bpm',
                'has_secondary_value' => false,
                'input_type' => 'numeric',
                'normal_range_min' => 60,
                'normal_range_max' => 100,
                'warning_range_min' => 40,
                'warning_range_max' => 120,
                'is_active' => true,
            ]),
            VitalSignType::factory()->create([
                'name' => 'weight',
                'display_name' => 'Weight',
                'unit_primary' => 'kg',
                'has_secondary_value' => false,
                'input_type' => 'decimal',
                'normal_range_min' => 50,
                'normal_range_max' => 120,
                'is_active' => true,
            ]),
        ]);
    });

    it('allows user registration and profile setup', function () {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'date_of_birth' => '1985-03-15',
            'gender' => 'male',
            'height' => 175,
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '+1-555-0101',
        ];

        // Test user registration
        $response = $this->post('/register', $userData);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'name' => 'John Doe',
        ]);

        $user = User::where('email', 'john.doe@example.com')->first();
        expect($user)->not->toBeNull();
        expect($user->date_of_birth->format('Y-m-d'))->toBe('1985-03-15');
        expect($user->gender)->toBe('male');
    });

    it('enables authenticated user to view dashboard with empty state', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSuccessful();
        $response->assertInertia(function ($page) {
            $page->component('Dashboard')
                ->has('summary')
                ->where('summary.total_records', 0)
                ->where('summary.flagged_records', 0)
                ->has('recentVitalSigns')
                ->where('recentVitalSigns', [])
                ->has('recommendations');
        });
    });

    it('allows user to create their first vital signs record', function () {
        $user = User::factory()->create();
        $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

        // Test accessing the creation form
        $response = $this->actingAs($user)->get('/vital-signs/create');

        $response->assertSuccessful();
        $response->assertInertia(function ($page) {
            $page->component('VitalSigns/Create')
                ->has('vitalSignTypes')
                ->where('vitalSignTypes.0.name', 'blood_pressure');
        });

        // Test creating a vital signs record via API
        $vitalSignsData = [
            'vital_sign_type_id' => $bloodPressureType->id,
            'value_primary' => '120',
            'value_secondary' => '80',
            'measured_at' => now()->toISOString(),
            'notes' => 'Morning reading after exercise',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/vital-signs', $vitalSignsData);

        $response->assertCreated();

        $this->assertDatabaseHas('vital_signs_records', [
            'user_id' => $user->id,
            'vital_sign_type_id' => $bloodPressureType->id,
            'value_primary' => '120',
            'value_secondary' => '80',
            'notes' => 'Morning reading after exercise',
        ]);

        // Verify the record was created correctly
        $vitalSignsRecord = VitalSignsRecord::where('user_id', $user->id)->first();
        expect($vitalSignsRecord)->not->toBeNull();
        expect($vitalSignsRecord->is_flagged)->toBeFalse(); // Normal reading
    });

    it('creates multiple vital signs records and tracks user progress', function () {
        $user = User::factory()->create();
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();
        $weightType = $this->vitalSignTypes->where('name', 'weight')->first();

        // Create multiple records over different days
        $records = [
            [
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '72',
                'measured_at' => now()->subDays(5)->toISOString(),
                'notes' => 'Resting heart rate',
            ],
            [
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '85',
                'measured_at' => now()->subDays(3)->toISOString(),
                'notes' => 'After light exercise',
            ],
            [
                'vital_sign_type_id' => $weightType->id,
                'value_primary' => '70.5',
                'measured_at' => now()->subDays(2)->toISOString(),
            ],
            [
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '68',
                'measured_at' => now()->subDay()->toISOString(),
                'notes' => 'Morning resting rate',
            ],
        ];

        foreach ($records as $recordData) {
            $response = $this->actingAs($user)
                ->postJson('/api/vital-signs', $recordData);
            $response->assertCreated();
        }

        // Verify all records were created
        expect(VitalSignsRecord::where('user_id', $user->id)->count())->toBe(4);

        // Test dashboard shows updated data
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $page->where('summary.total_records', 4)
                ->where('summary.flagged_records', 0)
                ->has('recentVitalSigns', 4); // Should show recent records
        });
    });

    it('flags abnormal vital signs readings automatically', function () {
        $user = User::factory()->create();
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        // Create an abnormal reading (above warning range)
        $abnormalReading = [
            'vital_sign_type_id' => $heartRateType->id,
            'value_primary' => '130', // Above warning range (120)
            'measured_at' => now()->toISOString(),
            'notes' => 'Post-workout measurement',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/vital-signs', $abnormalReading);

        $response->assertCreated();

        // Verify the reading was flagged
        $vitalSignsRecord = VitalSignsRecord::where('user_id', $user->id)->first();
        expect($vitalSignsRecord->is_flagged)->toBeTrue();

        // Dashboard should show flagged record
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $page->where('summary.flagged_records', 1);
        });
    });

    it('allows user to update recent vital signs records', function () {
        $user = User::factory()->create();
        $weightType = $this->vitalSignTypes->where('name', 'weight')->first();

        // Create a record
        $vitalSignsRecord = VitalSignsRecord::factory()->create([
            'user_id' => $user->id,
            'vital_sign_type_id' => $weightType->id,
            'value_primary' => '70.0',
            'measured_at' => now()->subHour(), // Recent enough to edit (within 24h)
        ]);

        // Test updating the record
        $updateData = [
            'value_primary' => '70.2',
            'notes' => 'Corrected measurement - with shoes removed',
        ];

        $response = $this->actingAs($user)
            ->putJson("/api/vital-signs/{$vitalSignsRecord->id}", $updateData);

        $response->assertSuccessful();

        $vitalSignsRecord->refresh();
        expect($vitalSignsRecord->value_primary)->toBe('70.2');
        expect($vitalSignsRecord->notes)->toBe('Corrected measurement - with shoes removed');
    });

    it('prevents updating old vital signs records', function () {
        $user = User::factory()->create();
        $weightType = $this->vitalSignTypes->where('name', 'weight')->first();

        // Create an old record (older than 24 hours)
        $oldRecord = VitalSignsRecord::factory()->create([
            'user_id' => $user->id,
            'vital_sign_type_id' => $weightType->id,
            'value_primary' => '70.0',
            'measured_at' => now()->subDays(2), // Too old to edit
        ]);

        $updateData = [
            'value_primary' => '70.5',
        ];

        $response = $this->actingAs($user)
            ->putJson("/api/vital-signs/{$oldRecord->id}", $updateData);

        $response->assertForbidden(); // Should be prevented by policy
    });

    it('displays vital signs trends and analytics', function () {
        $user = User::factory()->create();
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        // Create a series of heart rate readings with a trend
        $baseDate = now()->subDays(10);
        for ($i = 0; $i < 10; $i++) {
            VitalSignsRecord::factory()->create([
                'user_id' => $user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => (string) (70 + $i), // Increasing trend
                'measured_at' => $baseDate->copy()->addDays($i),
            ]);
        }

        // Test trends page
        $response = $this->actingAs($user)->get('/vital-signs/trends');

        $response->assertSuccessful();
        $response->assertInertia(function ($page) {
            $page->component('VitalSigns/Trends')
                ->has('vitalSignTypes')
                ->where('vitalSignTypes.0.name', 'blood_pressure');
        });

        // Test API endpoint for trends data
        $response = $this->actingAs($user)
            ->getJson("/api/vital-signs/trends?type_id={$heartRateType->id}&period=30");

        $response->assertSuccessful();
        $data = $response->json();

        expect($data)->toHaveKey('data');
        expect(count($data['data']))->toBe(10);
        expect($data)->toHaveKey('statistics');
        expect($data['statistics'])->toHaveKey('trend');
    });

    it('handles user deletion and data cleanup', function () {
        $user = User::factory()->create();
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        // Create some vital signs records
        VitalSignsRecord::factory()->count(3)->create([
            'user_id' => $user->id,
            'vital_sign_type_id' => $heartRateType->id,
        ]);

        // Create some recommendations
        Recommendation::factory()->count(2)->create([
            'user_id' => $user->id,
        ]);

        // Verify data exists
        expect(VitalSignsRecord::where('user_id', $user->id)->count())->toBe(3);
        expect(Recommendation::where('user_id', $user->id)->count())->toBe(2);

        // Delete user (should cascade)
        $userId = $user->id;
        $user->delete();

        // Verify related data was cleaned up
        expect(VitalSignsRecord::where('user_id', $userId)->count())->toBe(0);
        expect(Recommendation::where('user_id', $userId)->count())->toBe(0);
    });

    it('validates vital signs input correctly', function () {
        $user = User::factory()->create();
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        // Test invalid data
        $invalidData = [
            'vital_sign_type_id' => $heartRateType->id,
            'value_primary' => 'invalid', // Should be numeric
            'measured_at' => 'invalid-date',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/vital-signs', $invalidData);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['value_primary', 'measured_at']);

        // Test missing required fields
        $response = $this->actingAs($user)
            ->postJson('/api/vital-signs', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['vital_sign_type_id', 'value_primary']);
    });

    it('integrates with recommendation generation', function () {
        $user = User::factory()->create();
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        // Create multiple flagged readings to trigger recommendations
        for ($i = 0; $i < 3; $i++) {
            VitalSignsRecord::factory()->create([
                'user_id' => $user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '130', // Abnormal reading
                'is_flagged' => true,
                'measured_at' => now()->subDays($i),
            ]);
        }

        // Test manual recommendation generation
        $this->artisan('health:generate-recommendations', [
            '--user' => $user->id,
            '--sync' => true,
        ])->assertSuccessful();

        // Verify recommendations were generated
        $recommendations = Recommendation::where('user_id', $user->id)->get();
        expect($recommendations->count())->toBeGreaterThan(0);

        // Should have health alert recommendations
        expect($recommendations->where('recommendation_type', 'health_alert')->count())
            ->toBeGreaterThan(0);
    });
});
