<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantConsentRequest extends FormRequest
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
            'medical_professional_id' => [
                'required',
                'integer',
                Rule::exists('medical_professionals', 'id')->where('is_active', true),
            ],
            'access_level' => [
                'required',
                Rule::in(['read_only', 'full_access']),
            ],
            'consent_expires_at' => [
                'nullable',
                'date',
                'after:today',
            ],
            'emergency_access' => [
                'boolean',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
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
            'medical_professional_id.required' => 'Please select a medical professional.',
            'medical_professional_id.integer' => 'The medical professional selection is invalid.',
            'medical_professional_id.exists' => 'The selected medical professional is invalid or not currently active.',
            'access_level.required' => 'Please specify the access level for this consent.',
            'access_level.in' => 'Access level must be either "read only" or "full access".',
            'consent_expires_at.date' => 'Please provide a valid expiration date.',
            'consent_expires_at.after' => 'The consent expiration date must be in the future.',
            'emergency_access.boolean' => 'Emergency access setting must be true or false.',
            'notes.string' => 'Notes must be a valid text value.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
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
            'medical_professional_id' => 'medical professional',
            'access_level' => 'access level',
            'consent_expires_at' => 'expiration date',
            'emergency_access' => 'emergency access',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate emergency access with expiration date
            if ($this->emergency_access && $this->consent_expires_at) {
                // Emergency access should typically not have expiration dates
                // or should have longer expiration periods
                $expirationDate = \Carbon\Carbon::parse($this->consent_expires_at);
                $minimumEmergencyDuration = now()->addDays(30);

                if ($expirationDate->lt($minimumEmergencyDuration)) {
                    $validator->errors()->add(
                        'consent_expires_at',
                        'Emergency access consent should be valid for at least 30 days or left without expiration.'
                    );
                }
            }

            // Validate that access level is appropriate for medical professionals
            if ($this->access_level === 'full_access' && ! $this->emergency_access) {
                // Add a warning note that full access is being granted
                // This is informational rather than a validation error
                if (! $this->notes || ! str_contains(strtolower($this->notes), 'full access')) {
                    // This is just a validation hint, not an error
                    // The application may want to add a confirmation step for full access
                }
            }

            // Validate notes content for sensitive information warnings
            if ($this->notes) {
                $sensitiveKeywords = ['password', 'ssn', 'social security', 'credit card', 'bank account'];
                $notesLower = strtolower($this->notes);

                foreach ($sensitiveKeywords as $keyword) {
                    if (str_contains($notesLower, $keyword)) {
                        $validator->errors()->add(
                            'notes',
                            'Notes should not contain sensitive information such as passwords, SSN, or financial details.'
                        );
                        break;
                    }
                }
            }
        });
    }
}
