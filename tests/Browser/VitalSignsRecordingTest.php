<?php

use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Vital Signs Recording Browser Flow', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'date_of_birth' => '1985-06-15',
            'gender' => 'male',
            'height' => 175,
        ]);

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

    it('allows user to navigate to vital signs creation page', function () {
        $page = visit('/dashboard')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->assertSee('Dashboard')
            ->assertSee('Vital Signs')
            ->click('Record New Reading')
            ->assertSee('Record Vital Signs')
            ->assertSee('Blood Pressure')
            ->assertSee('Heart Rate')
            ->assertSee('Weight');

        expect($page->url())->toContain('/vital-signs/create');
    });

    it('successfully records blood pressure reading', function () {
        visit('/vital-signs/create')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type_id', $this->vitalSignTypes->where('name', 'blood_pressure')->first()->id)
            ->fill('value_primary', '120')
            ->fill('value_secondary', '80')
            ->fill('notes', 'Morning reading before breakfast')
            ->click('Save Reading')
            ->assertSee('Vital sign recorded successfully')
            ->assertUrlContains('/dashboard');

        // Verify the record was saved to database
        expect(VitalSignsRecord::where('user_id', $this->user->id)->count())->toBe(1);

        $record = VitalSignsRecord::where('user_id', $this->user->id)->first();
        expect($record->value_primary)->toBe('120');
        expect($record->value_secondary)->toBe('80');
        expect($record->notes)->toBe('Morning reading before breakfast');
        expect($record->is_flagged)->toBeFalse(); // Normal reading
    });

    it('successfully records heart rate reading with flagging', function () {
        visit('/vital-signs/create')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type_id', $this->vitalSignTypes->where('name', 'heart_rate')->first()->id)
            ->fill('value_primary', '130') // Above warning range (120)
            ->fill('notes', 'After intense workout')
            ->click('Save Reading')
            ->assertSee('Vital sign recorded successfully')
            ->assertSee('This reading has been flagged as abnormal');

        // Verify the record was flagged
        $record = VitalSignsRecord::where('user_id', $this->user->id)->first();
        expect($record->is_flagged)->toBeTrue();
    });

    it('displays validation errors for invalid input', function () {
        visit('/vital-signs/create')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type_id', $this->vitalSignTypes->where('name', 'heart_rate')->first()->id)
            ->fill('value_primary', 'invalid_number')
            ->click('Save Reading')
            ->assertSee('The value primary field must be a number');

        // No record should be saved
        expect(VitalSignsRecord::where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('shows different input fields based on vital sign type', function () {
        $page = visit('/vital-signs/create')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors();

        // Blood pressure should show dual inputs
        $page->select('vital_sign_type_id', $this->vitalSignTypes->where('name', 'blood_pressure')->first()->id)
            ->assertSee('Systolic (mmHg)')
            ->assertSee('Diastolic (mmHg)');

        // Heart rate should show single input
        $page->select('vital_sign_type_id', $this->vitalSignTypes->where('name', 'heart_rate')->first()->id)
            ->assertSee('Heart Rate (bpm)')
            ->assertDontSee('Diastolic');
    });

    it('allows user to view and edit recent readings', function () {
        // Create existing reading
        $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();
        VitalSignsRecord::factory()->create([
            'user_id' => $this->user->id,
            'vital_sign_type_id' => $bloodPressureType->id,
            'value_primary' => '118',
            'value_secondary' => '75',
            'measured_at' => now()->subMinutes(30), // Recent enough to edit
            'notes' => 'Original reading',
        ]);

        visit('/vital-signs')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->assertSee('Your Vital Signs')
            ->assertSee('118/75')
            ->assertSee('Blood Pressure')
            ->click('Edit')
            ->fill('value_primary', '120')
            ->fill('value_secondary', '78')
            ->fill('notes', 'Corrected reading')
            ->click('Update Reading')
            ->assertSee('Vital sign updated successfully')
            ->assertSee('120/78');

        // Verify the update in database
        $record = VitalSignsRecord::where('user_id', $this->user->id)->first();
        expect($record->value_primary)->toBe('120');
        expect($record->value_secondary)->toBe('78');
        expect($record->notes)->toBe('Corrected reading');
    });

    it('prevents editing old readings', function () {
        // Create old reading (more than 24 hours ago)
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();
        VitalSignsRecord::factory()->create([
            'user_id' => $this->user->id,
            'vital_sign_type_id' => $heartRateType->id,
            'value_primary' => '72',
            'measured_at' => now()->subDays(2), // Too old to edit
        ]);

        visit('/vital-signs')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->assertSee('72')
            ->assertSee('Heart Rate')
            ->assertDontSee('Edit'); // Edit button should not be present for old readings
    });

    it('displays dashboard with vital signs summary', function () {
        // Create some vital signs records
        $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        VitalSignsRecord::factory()->create([
            'user_id' => $this->user->id,
            'vital_sign_type_id' => $bloodPressureType->id,
            'value_primary' => '130',
            'value_secondary' => '85',
            'is_flagged' => true, // Flagged reading
        ]);

        VitalSignsRecord::factory()->create([
            'user_id' => $this->user->id,
            'vital_sign_type_id' => $heartRateType->id,
            'value_primary' => '75',
            'is_flagged' => false,
        ]);

        visit('/dashboard')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->assertSee('Welcome, John!')
            ->assertSee('2 Total Records')
            ->assertSee('1 Flagged Reading')
            ->assertSee('Recent Readings')
            ->assertSee('130/85')
            ->assertSee('75 bpm')
            ->assertSee('Record New Reading')
            ->assertSee('View All Records');
    });

    it('supports mobile responsive design', function () {
        visit('/vital-signs/create')
            ->actingAs($this->user)
            ->resize(375, 812) // iPhone X dimensions
            ->assertNoJavascriptErrors()
            ->assertSee('Record Vital Signs')
            ->select('vital_sign_type_id', $this->vitalSignTypes->where('name', 'heart_rate')->first()->id)
            ->fill('value_primary', '72')
            ->click('Save Reading')
            ->assertSee('Vital sign recorded successfully');

        // Test tablet view
        visit('/dashboard')
            ->actingAs($this->user)
            ->resize(768, 1024) // iPad dimensions
            ->assertNoJavascriptErrors()
            ->assertSee('Dashboard')
            ->assertSee('1 Total Record');
    });

    it('handles network interruption gracefully', function () {
        visit('/vital-signs/create')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type_id', $this->vitalSignTypes->where('name', 'weight')->first()->id)
            ->fill('value_primary', '70.5')
            ->offline() // Simulate network interruption
            ->click('Save Reading')
            ->assertSee('Network error')
            ->online() // Restore connection
            ->click('Save Reading')
            ->assertSee('Vital sign recorded successfully');
    });

    it('supports keyboard navigation accessibility', function () {
        visit('/vital-signs/create')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->keys('{tab}') // Tab to vital sign type selector
            ->keys('{enter}') // Open dropdown
            ->keys('{down}') // Select first option
            ->keys('{enter}') // Confirm selection
            ->keys('{tab}') // Tab to primary value input
            ->type('72')
            ->keys('{tab}') // Tab to notes
            ->type('Keyboard navigation test')
            ->keys('{tab}') // Tab to save button
            ->keys('{enter}') // Press save
            ->assertSee('Vital sign recorded successfully');
    });

    it('displays loading states during form submission', function () {
        visit('/vital-signs/create')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type_id', $this->vitalSignTypes->where('name', 'heart_rate')->first()->id)
            ->fill('value_primary', '68')
            ->click('Save Reading')
            ->assertSee('Saving...') // Loading state should appear
            ->waitFor('[data-testid="success-message"]') // Wait for success
            ->assertSee('Vital sign recorded successfully');
    });
});
