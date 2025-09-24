<?php

namespace App\Http\Requests;

use App\Models\VitalSignType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVitalSignsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vital_sign_type_id' => [
                'sometimes',
                'integer',
                Rule::exists('vital_sign_types', 'id')->where('is_active', true),
            ],
            'value_primary' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'value_secondary' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'unit' => [
                'sometimes',
                'string',
                'max:20',
            ],
            'measured_at' => [
                'sometimes',
                'date',
                'before_or_equal:now',
                'after:'.now()->subDays(30)->format('Y-m-d'),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'measurement_method' => [
                'sometimes',
                Rule::in(['manual', 'device', 'estimated']),
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:100',
                'required_if:measurement_method,device',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vital_sign_type_id.exists' => 'The selected vital sign type is invalid or inactive.',
            'value_primary.numeric' => 'The measurement value must be a number.',
            'value_primary.min' => 'The measurement value cannot be negative.',
            'value_secondary.numeric' => 'The secondary value must be a number.',
            'value_secondary.min' => 'The secondary value cannot be negative.',
            'unit.string' => 'The unit must be a valid text value.',
            'unit.max' => 'The unit cannot exceed 20 characters.',
            'measured_at.date' => 'Please provide a valid measurement date.',
            'measured_at.before_or_equal' => 'The measurement date cannot be in the future.',
            'measured_at.after' => 'The measurement date cannot be more than 30 days ago.',
            'measurement_method.in' => 'Invalid measurement method selected.',
            'device_name.required_if' => 'Device name is required when measurement method is "device".',
            'device_name.max' => 'Device name cannot exceed 100 characters.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vital_sign_type_id' => 'vital sign type',
            'value_primary' => 'measurement value',
            'value_secondary' => 'secondary value',
            'measured_at' => 'measurement date',
            'measurement_method' => 'measurement method',
            'device_name' => 'device name',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Only validate vital sign type constraints if vital_sign_type_id is provided
            $vitalSignTypeId = $this->vital_sign_type_id ?? $this->route('vitalSignsRecord')?->vital_sign_type_id;

            if ($vitalSignTypeId) {
                $vitalSignType = VitalSignType::find($vitalSignTypeId);

                if ($vitalSignType) {
                    // Validate primary value against type limits (only if value_primary is being updated)
                    if ($this->has('value_primary') && $this->value_primary !== null) {
                        if ($vitalSignType->min_value !== null && $this->value_primary < $vitalSignType->min_value) {
                            $validator->errors()->add(
                                'value_primary',
                                "The measurement value must be at least {$vitalSignType->min_value} {$vitalSignType->unit_primary}."
                            );
                        }

                        if ($vitalSignType->max_value !== null && $this->value_primary > $vitalSignType->max_value) {
                            $validator->errors()->add(
                                'value_primary',
                                "The measurement value cannot exceed {$vitalSignType->max_value} {$vitalSignType->unit_primary}."
                            );
                        }
                    }

                    // Validate secondary value requirements (only if value_secondary is being updated)
                    if ($this->has('value_secondary')) {
                        if ($vitalSignType->has_secondary_value && $this->value_secondary === null) {
                            $validator->errors()->add(
                                'value_secondary',
                                'A secondary value is required for this type of measurement.'
                            );
                        }

                        if (! $vitalSignType->has_secondary_value && $this->value_secondary !== null) {
                            $validator->errors()->add(
                                'value_secondary',
                                'Secondary value is not applicable for this type of measurement.'
                            );
                        }
                    }

                    // Validate unit matches expected unit (only if unit is being updated)
                    if ($this->has('unit') && $this->unit) {
                        if ($this->unit !== $vitalSignType->unit_primary && $this->unit !== $vitalSignType->unit_secondary) {
                            $expectedUnits = array_filter([$vitalSignType->unit_primary, $vitalSignType->unit_secondary]);
                            $validator->errors()->add(
                                'unit',
                                'Unit must be one of: '.implode(', ', $expectedUnits)
                            );
                        }
                    }
                }
            }
        });
    }
}
