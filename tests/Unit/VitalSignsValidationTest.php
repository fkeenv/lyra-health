<?php

use App\Http\Requests\CreateVitalSignsRequest;
use App\Http\Requests\UpdateVitalSignsRequest;
use App\Models\VitalSignType;
use App\Services\VitalSignsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

describe('Vital Signs Validation Logic', function () {
    beforeEach(function () {
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
                'validation_rules' => json_encode([
                    'value_primary' => ['required', 'numeric', 'min:50', 'max:300'],
                    'value_secondary' => ['required', 'numeric', 'min:30', 'max:200'],
                ]),
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
                'validation_rules' => json_encode([
                    'value_primary' => ['required', 'numeric', 'min:30', 'max:250'],
                ]),
            ]),
            VitalSignType::factory()->create([
                'name' => 'weight',
                'display_name' => 'Weight',
                'unit_primary' => 'kg',
                'has_secondary_value' => false,
                'input_type' => 'decimal',
                'normal_range_min' => 30,
                'normal_range_max' => 200,
                'validation_rules' => json_encode([
                    'value_primary' => ['required', 'numeric', 'min:10', 'max:500'],
                ]),
            ]),
        ]);

        $this->vitalSignsService = new VitalSignsService;
    });

    describe('CreateVitalSignsRequest Validation', function () {
        it('passes validation for valid blood pressure data', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            $data = [
                'vital_sign_type_id' => $bloodPressureType->id,
                'value_primary' => '120',
                'value_secondary' => '80',
                'measured_at' => now()->toISOString(),
                'notes' => 'Morning reading',
            ];

            $request = new CreateVitalSignsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        it('passes validation for valid heart rate data', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            $data = [
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '72',
                'measured_at' => now()->toISOString(),
            ];

            $request = new CreateVitalSignsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        it('fails validation for missing required fields', function () {
            $data = [];

            $request = new CreateVitalSignsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('vital_sign_type_id'))->toBeTrue();
            expect($validator->errors()->has('value_primary'))->toBeTrue();
            expect($validator->errors()->has('measured_at'))->toBeTrue();
        });

        it('fails validation for invalid vital sign type ID', function () {
            $data = [
                'vital_sign_type_id' => 9999, // Non-existent ID
                'value_primary' => '120',
                'measured_at' => now()->toISOString(),
            ];

            $request = new CreateVitalSignsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('vital_sign_type_id'))->toBeTrue();
        });

        it('fails validation for non-numeric values', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            $data = [
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => 'not_a_number',
                'measured_at' => now()->toISOString(),
            ];

            $request = new CreateVitalSignsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('value_primary'))->toBeTrue();
        });

        it('fails validation for invalid date format', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            $data = [
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '72',
                'measured_at' => 'invalid-date',
            ];

            $request = new CreateVitalSignsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('measured_at'))->toBeTrue();
        });

        it('fails validation for future measurement dates', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            $data = [
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '72',
                'measured_at' => now()->addDay()->toISOString(),
            ];

            $request = new CreateVitalSignsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('measured_at'))->toBeTrue();
        });

        it('validates notes field length', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            $data = [
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => '72',
                'measured_at' => now()->toISOString(),
                'notes' => str_repeat('a', 1001), // Exceeds max length
            ];

            $request = new CreateVitalSignsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('notes'))->toBeTrue();
        });
    });

    describe('UpdateVitalSignsRequest Validation', function () {
        it('passes validation for partial updates', function () {
            $data = [
                'value_primary' => '125',
                'notes' => 'Updated reading',
            ];

            $request = new UpdateVitalSignsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        it('fails validation for invalid numeric updates', function () {
            $data = [
                'value_primary' => 'invalid_number',
            ];

            $request = new UpdateVitalSignsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('value_primary'))->toBeTrue();
        });

        it('prevents updating measurement date to future', function () {
            $data = [
                'measured_at' => now()->addDay()->toISOString(),
            ];

            $request = new UpdateVitalSignsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('measured_at'))->toBeTrue();
        });
    });

    describe('VitalSignsService Validation Logic', function () {
        it('correctly identifies normal readings', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            $result = $this->vitalSignsService->validateReading(
                $bloodPressureType,
                '120', // Normal systolic
                '80'   // Normal diastolic
            );

            expect($result['is_normal'])->toBeTrue();
            expect($result['is_flagged'])->toBeFalse();
            expect($result['warning_level'])->toBe('normal');
        });

        it('correctly identifies high readings within warning range', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            $result = $this->vitalSignsService->validateReading(
                $heartRateType,
                '110' // Above normal but within warning range
            );

            expect($result['is_normal'])->toBeFalse();
            expect($result['is_flagged'])->toBeFalse();
            expect($result['warning_level'])->toBe('elevated');
        });

        it('correctly flags readings outside warning range', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            $result = $this->vitalSignsService->validateReading(
                $heartRateType,
                '130' // Above warning range (120)
            );

            expect($result['is_normal'])->toBeFalse();
            expect($result['is_flagged'])->toBeTrue();
            expect($result['warning_level'])->toBe('high');
        });

        it('correctly identifies low readings', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            $result = $this->vitalSignsService->validateReading(
                $heartRateType,
                '35' // Below warning range (40)
            );

            expect($result['is_normal'])->toBeFalse();
            expect($result['is_flagged'])->toBeTrue();
            expect($result['warning_level'])->toBe('low');
        });

        it('handles dual value validation for blood pressure', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            // High systolic, normal diastolic - should flag
            $result = $this->vitalSignsService->validateReading(
                $bloodPressureType,
                '170', // High systolic
                '80'   // Normal diastolic
            );

            expect($result['is_flagged'])->toBeTrue();
            expect($result['warning_level'])->toBe('high');

            // Normal systolic, high diastolic - should flag
            $result = $this->vitalSignsService->validateReading(
                $bloodPressureType,
                '120', // Normal systolic
                '95'   // High diastolic
            );

            expect($result['is_flagged'])->toBeTrue();
            expect($result['warning_level'])->toBe('high');
        });

        it('validates weight readings with decimal precision', function () {
            $weightType = $this->vitalSignTypes->where('name', 'weight')->first();

            $result = $this->vitalSignsService->validateReading(
                $weightType,
                '70.5' // Valid decimal weight
            );

            expect($result['is_normal'])->toBeTrue();
            expect($result['value_primary'])->toBe('70.5');
        });

        it('applies age-based validation adjustments', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Simulate elderly patient (age adjustment)
            $result = $this->vitalSignsService->validateReadingWithAge(
                $heartRateType,
                '55', // Lower HR for elderly
                75    // Age 75
            );

            expect($result['is_normal'])->toBeTrue(); // Should be normal for elderly
            expect($result['age_adjusted'])->toBeTrue();
        });

        it('validates measurement consistency over time', function () {
            $weightType = $this->vitalSignTypes->where('name', 'weight')->first();

            $previousReadings = [
                ['value_primary' => '70.0', 'measured_at' => now()->subDays(1)],
                ['value_primary' => '70.2', 'measured_at' => now()->subDays(2)],
                ['value_primary' => '69.8', 'measured_at' => now()->subDays(3)],
            ];

            // Sudden large change should be flagged for review
            $result = $this->vitalSignsService->validateConsistency(
                $weightType,
                '75.0', // Sudden 5kg increase
                $previousReadings
            );

            expect($result['consistency_warning'])->toBeTrue();
            expect($result['change_magnitude'])->toBeGreaterThan(4);
        });

        it('handles edge cases in validation ranges', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Test exact boundary values
            $resultLowerNormal = $this->vitalSignsService->validateReading(
                $heartRateType,
                '60' // Exact lower normal boundary
            );
            expect($resultLowerNormal['is_normal'])->toBeTrue();

            $resultUpperNormal = $this->vitalSignsService->validateReading(
                $heartRateType,
                '100' // Exact upper normal boundary
            );
            expect($resultUpperNormal['is_normal'])->toBeTrue();

            $resultLowerWarning = $this->vitalSignsService->validateReading(
                $heartRateType,
                '40' // Exact lower warning boundary
            );
            expect($resultLowerWarning['is_flagged'])->toBeFalse();
            expect($resultLowerWarning['warning_level'])->toBe('low');

            $resultUpperWarning = $this->vitalSignsService->validateReading(
                $heartRateType,
                '120' // Exact upper warning boundary
            );
            expect($resultUpperWarning['is_flagged'])->toBeFalse();
            expect($resultUpperWarning['warning_level'])->toBe('elevated');
        });
    });

    describe('Custom Validation Rules', function () {
        it('validates measurement time constraints', function () {
            // Cannot record measurements more than 7 days in the past
            $data = [
                'measured_at' => now()->subDays(8)->toISOString(),
            ];

            $rules = ['measured_at' => ['date', 'after_or_equal:'.now()->subDays(7)->toDateString()]];
            $validator = Validator::make($data, $rules);

            expect($validator->fails())->toBeTrue();
        });

        it('validates physiologically impossible values', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Test extremely high value
            $result = $this->vitalSignsService->validatePhysiological(
                $heartRateType,
                '300' // Physiologically impossible
            );

            expect($result['physiologically_possible'])->toBeFalse();
            expect($result['validation_error'])->toContain('exceeds physiological limits');

            // Test extremely low value
            $result = $this->vitalSignsService->validatePhysiological(
                $heartRateType,
                '10' // Physiologically impossible for conscious person
            );

            expect($result['physiologically_possible'])->toBeFalse();
        });

        it('validates blood pressure ratio consistency', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            // Diastolic higher than systolic (impossible)
            $result = $this->vitalSignsService->validateBloodPressureRatio(
                '80',  // Systolic
                '120'  // Diastolic (higher than systolic)
            );

            expect($result['ratio_valid'])->toBeFalse();
            expect($result['validation_error'])->toContain('Diastolic pressure cannot exceed systolic');

            // Valid ratio
            $result = $this->vitalSignsService->validateBloodPressureRatio(
                '120', // Systolic
                '80'   // Diastolic
            );

            expect($result['ratio_valid'])->toBeTrue();
        });

        it('applies medication-aware validation', function () {
            $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

            // Patient on beta-blockers might have lower normal HR
            $result = $this->vitalSignsService->validateWithMedications(
                $heartRateType,
                '50', // Low HR
                ['beta_blockers'] // Medication list
            );

            expect($result['medication_adjusted'])->toBeTrue();
            expect($result['is_normal'])->toBeTrue(); // Should be normal with beta-blockers
        });
    });

    describe('Error Message Customization', function () {
        it('provides specific error messages for different validation failures', function () {
            $request = new CreateVitalSignsRequest;
            $messages = $request->messages();

            expect($messages['value_primary.required'])->toBe('The primary value is required.');
            expect($messages['value_primary.numeric'])->toBe('The primary value must be a number.');
            expect($messages['measured_at.before_or_equal'])->toBe('The measurement date cannot be in the future.');
            expect($messages['notes.max'])->toBe('Notes cannot exceed 1000 characters.');
        });

        it('provides contextual validation messages based on vital sign type', function () {
            $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();

            $messages = $this->vitalSignsService->getValidationMessages($bloodPressureType);

            expect($messages['value_primary'])->toContain('systolic');
            expect($messages['value_secondary'])->toContain('diastolic');
            expect($messages['range'])->toContain('mmHg');
        });
    });
});
