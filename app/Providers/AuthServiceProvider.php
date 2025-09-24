<?php

namespace App\Providers;

use App\Models\PatientProviderConsent;
use App\Models\VitalSignsRecord;
use App\Policies\ConsentPolicy;
use App\Policies\VitalSignsPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     */
    protected $policies = [
        VitalSignsRecord::class => VitalSignsPolicy::class,
        PatientProviderConsent::class => ConsentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define additional gates for specific healthcare operations
        Gate::define('view-patient-data', function ($user, $patientId, $dataType = null) {
            // Allow users to view their own data
            if ($user instanceof \App\Models\User && $user->id === $patientId) {
                return true;
            }

            // Allow medical professionals with active consent
            if ($user instanceof \App\Models\MedicalProfessional) {
                return $this->medicalProfessionalHasAccess($user, $patientId, $dataType);
            }

            return false;
        });

        Gate::define('emergency-access', function ($user, $patientId) {
            // Only verified medical professionals can use emergency access
            if ($user instanceof \App\Models\MedicalProfessional && $user->verification_status === 'verified') {
                // Log emergency access for audit purposes
                \App\Models\DataAccessLog::create([
                    'medical_professional_id' => $user->id,
                    'patient_id' => $patientId,
                    'access_type' => 'emergency',
                    'data_accessed' => ['vital_signs', 'emergency_contact'],
                    'accessed_at' => now(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->header('User-Agent'),
                    'notes' => 'Emergency access used',
                ]);

                return true;
            }

            return false;
        });

        Gate::define('manage-consent', function ($user, $consentId = null) {
            // Users can manage their own consents
            if ($user instanceof \App\Models\User) {
                if ($consentId) {
                    $consent = \App\Models\PatientProviderConsent::find($consentId);

                    return $consent && $consent->patient_id === $user->id;
                }

                return true;
            }

            return false;
        });

        Gate::define('request-patient-consent', function ($user, $patientId) {
            // Only verified medical professionals can request patient consent
            if ($user instanceof \App\Models\MedicalProfessional && $user->verification_status === 'verified') {
                // Check if there's already an active or pending consent
                $existingConsent = \App\Models\PatientProviderConsent::where('medical_professional_id', $user->id)
                    ->where('patient_id', $patientId)
                    ->whereIn('status', ['active', 'pending'])
                    ->exists();

                return ! $existingConsent;
            }

            return false;
        });

        Gate::define('view-audit-logs', function ($user) {
            // Only system administrators can view audit logs
            // In a real application, this would check for admin role
            return false; // Implement when admin roles are added
        });

        Gate::define('manage-medical-professionals', function ($user) {
            // Only system administrators can manage medical professional verification
            return false; // Implement when admin roles are added
        });
    }

    /**
     * Check if a medical professional has access to patient data.
     */
    private function medicalProfessionalHasAccess(\App\Models\MedicalProfessional $medicalProfessional, int $patientId, ?string $dataType = null): bool
    {
        $consent = $medicalProfessional->patientConsents()
            ->where('patient_id', $patientId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereNull('revoked_at')
            ->first();

        if (! $consent) {
            return false;
        }

        // If no specific data type is requested, allow access
        if (! $dataType) {
            return true;
        }

        // Check if the consent includes the requested data type
        $dataTypes = is_string($consent->data_types)
            ? json_decode($consent->data_types, true)
            : $consent->data_types;

        return in_array($dataType, $dataTypes ?? []);
    }
}
