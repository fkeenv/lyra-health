<?php

namespace App\Services;

use App\Models\DataAccessLog;
use App\Models\MedicalProfessional;
use App\Models\PatientProviderConsent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConsentService
{
    /**
     * Access levels for patient-provider consent
     */
    public const ACCESS_LEVEL_VIEW_ONLY = 'view_only';

    public const ACCESS_LEVEL_LIMITED = 'limited';

    public const ACCESS_LEVEL_FULL_ACCESS = 'full_access';

    /**
     * Emergency access duration in hours
     */
    public const EMERGENCY_ACCESS_DURATION = 24;

    /**
     * Get active consents for a user.
     *
     * @param  User  $user  The patient to get consents for
     * @return Collection Collection of active consent records
     */
    public function getActiveConsents(User $user): Collection
    {
        return $user->patientProviderConsents()
            ->with(['medicalProfessional'])
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('consent_expires_at')
                    ->orWhere('consent_expires_at', '>', now());
            })
            ->orderBy('consent_given_at', 'desc')
            ->get();
    }

    /**
     * Check if a medical professional has consent to access a user's data.
     *
     * @param  User  $user  The patient
     * @param  MedicalProfessional  $professional  The medical professional
     * @return PatientProviderConsent|null Active consent record or null
     */
    public function checkConsentAccess(User $user, MedicalProfessional $professional): ?PatientProviderConsent
    {
        return $user->patientProviderConsents()
            ->where('medical_professional_id', $professional->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('consent_expires_at')
                    ->orWhere('consent_expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Grant emergency access to a medical professional.
     *
     * @param  User  $user  The patient
     * @param  MedicalProfessional  $professional  The medical professional
     * @param  array  $emergencyData  Emergency access data including justification
     * @return PatientProviderConsent The emergency consent record
     *
     * @throws InvalidArgumentException If emergency data is invalid
     */
    public function grantEmergencyAccess(User $user, MedicalProfessional $professional, array $emergencyData): PatientProviderConsent
    {
        $this->validateEmergencyData($emergencyData);
        $this->validateMedicalProfessional($professional);

        return DB::transaction(function () use ($user, $professional, $emergencyData) {
            // Check if emergency access already exists
            $existingEmergencyAccess = $this->checkEmergencyAccess($user, $professional);
            if ($existingEmergencyAccess) {
                return $existingEmergencyAccess;
            }

            // Create emergency consent
            $consent = new PatientProviderConsent([
                'user_id' => $user->id,
                'medical_professional_id' => $professional->id,
                'consent_given_at' => now(),
                'consent_expires_at' => now()->addHours(self::EMERGENCY_ACCESS_DURATION),
                'access_level' => self::ACCESS_LEVEL_FULL_ACCESS,
                'granted_by_user' => false, // Emergency access not granted by user
                'emergency_access' => true,
                'is_active' => true,
                'notes' => 'Emergency access: '.($emergencyData['justification'] ?? 'No justification provided'),
            ]);

            $consent->save();

            // Log the emergency access grant
            $this->logConsentAction($user, $professional, 'emergency_access_granted', [
                'consent_id' => $consent->id,
                'justification' => $emergencyData['justification'] ?? null,
                'emergency_contact' => $emergencyData['emergency_contact'] ?? null,
                'facility' => $emergencyData['facility'] ?? null,
            ]);

            return $consent->fresh(['user', 'medicalProfessional']);
        });
    }

    /**
     * Check for existing emergency access.
     *
     * @param  User  $user  The patient
     * @param  MedicalProfessional  $professional  The medical professional
     * @return PatientProviderConsent|null Active emergency consent or null
     */
    public function checkEmergencyAccess(User $user, MedicalProfessional $professional): ?PatientProviderConsent
    {
        return $user->patientProviderConsents()
            ->where('medical_professional_id', $professional->id)
            ->where('is_active', true)
            ->where('emergency_access', true)
            ->where('consent_expires_at', '>', now())
            ->first();
    }

    /**
     * Clean up expired consents.
     *
     * @return int Number of consents cleaned up
     */
    public function cleanupExpiredConsents(): int
    {
        return DB::transaction(function () {
            // Mark expired consents as inactive
            $expiredCount = PatientProviderConsent::where('is_active', true)
                ->where('consent_expires_at', '<', now())
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            // Log cleanup action for auditing
            if ($expiredCount > 0) {
                DB::table('data_access_logs')->insert([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => null,
                    'medical_professional_id' => null,
                    'access_type' => 'consent_cleanup',
                    'data_scope' => json_encode(['system_maintenance']),
                    'accessed_at' => now(),
                    'ip_address' => request()->ip(),
                    'user_agent' => 'System',
                    'notes' => "Cleaned up {$expiredCount} expired consents",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $expiredCount;
        });
    }

    /**
     * Get consent history for a user.
     *
     * @param  User  $user  The patient
     * @param  int  $limit  Number of records to return
     * @return Collection Collection of consent records including revoked ones
     */
    public function getConsentHistory(User $user, int $limit = 50): Collection
    {
        return $user->patientProviderConsents()
            ->with(['medicalProfessional'])
            ->orderBy('consent_given_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get patients for a medical professional.
     *
     * @param  MedicalProfessional  $professional  The medical professional
     * @return Collection Collection of users who have granted consent
     */
    public function getPatientsForProfessional(MedicalProfessional $professional): Collection
    {
        return $professional->patients()
            ->wherePivot('is_active', true)
            ->wherePivot(function ($query) {
                $query->whereNull('consent_expires_at')
                    ->orWhere('consent_expires_at', '>', now());
            })
            ->get();
    }

    /**
     * Update consent access level.
     *
     * @param  PatientProviderConsent  $consent  The consent to update
     * @param  string  $newAccessLevel  New access level
     * @return bool True if successfully updated
     *
     * @throws InvalidArgumentException If access level is invalid
     */
    public function updateAccessLevel(PatientProviderConsent $consent, string $newAccessLevel): bool
    {
        if (! $this->isValidAccessLevel($newAccessLevel)) {
            throw new InvalidArgumentException("Invalid access level: {$newAccessLevel}");
        }

        if (! $consent->isActive()) {
            throw new InvalidArgumentException('Cannot update access level for inactive consent');
        }

        $oldAccessLevel = $consent->access_level;
        $consent->access_level = $newAccessLevel;
        $saved = $consent->save();

        if ($saved) {
            // Log the access level change
            $this->logConsentAction(
                $consent->user,
                $consent->medicalProfessional,
                'access_level_updated',
                [
                    'consent_id' => $consent->id,
                    'old_access_level' => $oldAccessLevel,
                    'new_access_level' => $newAccessLevel,
                ]
            );
        }

        return $saved;
    }

    /**
     * Extend consent expiration date.
     *
     * @param  PatientProviderConsent  $consent  The consent to extend
     * @param  Carbon  $newExpirationDate  New expiration date
     * @return bool True if successfully extended
     */
    public function extendConsent(PatientProviderConsent $consent, Carbon $newExpirationDate): bool
    {
        if (! $consent->isActive()) {
            throw new InvalidArgumentException('Cannot extend inactive consent');
        }

        if ($newExpirationDate->isPast()) {
            throw new InvalidArgumentException('New expiration date cannot be in the past');
        }

        $oldExpirationDate = $consent->consent_expires_at;
        $consent->consent_expires_at = $newExpirationDate;
        $saved = $consent->save();

        if ($saved) {
            // Log the consent extension
            $this->logConsentAction(
                $consent->user,
                $consent->medicalProfessional,
                'consent_extended',
                [
                    'consent_id' => $consent->id,
                    'old_expiration' => $oldExpirationDate?->toISOString(),
                    'new_expiration' => $newExpirationDate->toISOString(),
                ]
            );
        }

        return $saved;
    }

    /**
     * Log data access by a medical professional.
     *
     * @param  User  $user  The patient whose data was accessed
     * @param  MedicalProfessional  $professional  The medical professional who accessed the data
     * @param  string  $accessType  The type of access performed
     * @param  array  $dataScope  Scope of data accessed
     * @param  array  $additionalData  Additional data including IP, user agent, etc.
     * @return DataAccessLog The created log entry
     */
    public function logDataAccess(
        User $user,
        MedicalProfessional $professional,
        string $accessType,
        array $dataScope = [],
        array $additionalData = []
    ): DataAccessLog {
        return DataAccessLog::create([
            'user_id' => $user->id,
            'medical_professional_id' => $professional->id,
            'access_type' => $accessType,
            'data_scope' => $dataScope,
            'accessed_at' => now(),
            'ip_address' => $additionalData['ip_address'] ?? request()->ip(),
            'user_agent' => $additionalData['user_agent'] ?? request()->userAgent(),
            'session_duration' => $additionalData['session_duration'] ?? null,
            'notes' => $additionalData['notes'] ?? null,
        ]);
    }

    /**
     * Get data access logs for a user.
     *
     * @param  User  $user  The patient
     * @param  int  $limit  Number of records to return
     * @return Collection Collection of access log records
     */
    public function getDataAccessLogs(User $user, int $limit = 100): Collection
    {
        return DataAccessLog::with(['medicalProfessional'])
            ->where('user_id', $user->id)
            ->orderBy('accessed_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Validate consent data.
     *
     * @param  array  $data  Consent data to validate
     *
     * @throws InvalidArgumentException If data is invalid
     */
    protected function validateConsentData(array $data): void
    {
        $accessLevel = $data['access_level'] ?? self::ACCESS_LEVEL_LIMITED;

        if (! $this->isValidAccessLevel($accessLevel)) {
            throw new InvalidArgumentException("Invalid access level: {$accessLevel}");
        }

        if (isset($data['expires_at']) && $data['expires_at'] !== null) {
            $expirationDate = $this->parseExpirationDate($data['expires_at']);
            if ($expirationDate && $expirationDate->isPast()) {
                throw new InvalidArgumentException('Expiration date cannot be in the past');
            }
        }
    }

    /**
     * Validate emergency access data.
     *
     * @param  array  $emergencyData  Emergency data to validate
     *
     * @throws InvalidArgumentException If data is invalid
     */
    protected function validateEmergencyData(array $emergencyData): void
    {
        if (empty($emergencyData['justification'])) {
            throw new InvalidArgumentException('Emergency access requires justification');
        }

        if (strlen($emergencyData['justification']) < 10) {
            throw new InvalidArgumentException('Emergency justification must be at least 10 characters');
        }
    }

    /**
     * Validate medical professional.
     *
     * @param  MedicalProfessional  $professional  Medical professional to validate
     *
     * @throws InvalidArgumentException If professional is invalid
     */
    protected function validateMedicalProfessional(MedicalProfessional $professional): void
    {
        if (! $professional->isVerified()) {
            throw new InvalidArgumentException('Medical professional must be verified');
        }

        if (! $professional->is_active) {
            throw new InvalidArgumentException('Medical professional account is not active');
        }
    }

    /**
     * Check if access level is valid.
     *
     * @param  string  $accessLevel  Access level to check
     * @return bool True if valid
     */
    protected function isValidAccessLevel(string $accessLevel): bool
    {
        return in_array($accessLevel, [
            self::ACCESS_LEVEL_VIEW_ONLY,
            self::ACCESS_LEVEL_LIMITED,
            self::ACCESS_LEVEL_FULL_ACCESS,
        ]);
    }

    /**
     * Parse expiration date from various formats.
     *
     * @param  mixed  $expirationDate  Date to parse
     * @return Carbon|null Parsed date or null
     */
    protected function parseExpirationDate($expirationDate): ?Carbon
    {
        if ($expirationDate === null) {
            return null;
        }

        if ($expirationDate instanceof Carbon) {
            return $expirationDate;
        }

        if (is_string($expirationDate)) {
            try {
                return Carbon::parse($expirationDate);
            } catch (\Exception $e) {
                throw new InvalidArgumentException("Invalid expiration date format: {$expirationDate}");
            }
        }

        throw new InvalidArgumentException('Expiration date must be a string, Carbon instance, or null');
    }

    /**
     * Revoke existing active consent between user and professional.
     *
     * @param  User  $user  The patient
     * @param  MedicalProfessional  $professional  The medical professional
     */
    protected function revokeExistingConsent(User $user, MedicalProfessional $professional): void
    {
        $existingConsent = $this->checkConsentAccess($user, $professional);
        if ($existingConsent) {
            $this->revokeConsent($existingConsent);
        }
    }

    /**
     * Log consent-related actions.
     *
     * @param  User  $user  The patient
     * @param  MedicalProfessional  $professional  The medical professional
     * @param  string  $action  Action performed
     * @param  array  $metadata  Additional metadata
     */
    protected function logConsentAction(User $user, MedicalProfessional $professional, string $action, array $metadata = []): void
    {
        DataAccessLog::create([
            'user_id' => $user->id,
            'medical_professional_id' => $professional->id,
            'access_type' => $action,
            'data_scope' => ['consent_management'],
            'accessed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'notes' => json_encode($metadata),
        ]);
    }

    /**
     * Get paginated consent records for a user.
     *
     * @param  User  $user  The patient
     * @param  string|null  $status  Status filter ('active' or 'revoked')
     * @param  int|null  $medicalProfessionalId  Filter by medical professional
     * @param  int  $perPage  Number of records per page
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getUserConsents(User $user, ?string $status = null, ?int $medicalProfessionalId = null, int $perPage = 15)
    {
        $query = $user->patientProviderConsents()
            ->with(['medicalProfessional'])
            ->orderBy('consent_given_at', 'desc');

        if ($status === 'active') {
            $query->where('is_active', true)
                ->whereNull('revoked_at')
                ->where(function ($q) {
                    $q->whereNull('consent_expires_at')
                        ->orWhere('consent_expires_at', '>', now());
                });
        } elseif ($status === 'revoked') {
            $query->where(function ($q) {
                $q->where('is_active', false)
                    ->orWhereNotNull('revoked_at')
                    ->orWhere('consent_expires_at', '<=', now());
            });
        }

        if ($medicalProfessionalId) {
            $query->where('medical_professional_id', $medicalProfessionalId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Check if a medical professional has active consent to access a user's data.
     *
     * @param  User  $patient  The patient
     * @param  User  $medicalProfessional  The medical professional (User with medical role)
     * @return bool True if active consent exists
     */
    public function hasActiveConsent(User $patient, User $medicalProfessional): bool
    {
        return $patient->patientProviderConsents()
            ->where('medical_professional_id', $medicalProfessional->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('consent_expires_at')
                    ->orWhere('consent_expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Get paginated list of patients that have granted consent to a medical professional.
     *
     * @param  User  $medicalProfessional  The medical professional
     * @param  string  $status  Status filter ('active' or 'revoked')
     * @param  int  $perPage  Number of records per page
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getAuthorizedPatients(User $medicalProfessional, string $status = 'active', int $perPage = 15)
    {
        $query = PatientProviderConsent::query()
            ->with(['patient'])
            ->where('medical_professional_id', $medicalProfessional->id)
            ->orderBy('consent_given_at', 'desc');

        if ($status === 'active') {
            $query->where('is_active', true)
                ->whereNull('revoked_at')
                ->where(function ($q) {
                    $q->whereNull('consent_expires_at')
                        ->orWhere('consent_expires_at', '>', now());
                });
        } elseif ($status === 'revoked') {
            $query->where(function ($q) {
                $q->where('is_active', false)
                    ->orWhereNotNull('revoked_at')
                    ->orWhere('consent_expires_at', '<=', now());
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Log access to patient data by medical professional.
     *
     * @param  User  $medicalProfessional  The medical professional
     * @param  string  $action  Action performed
     * @param  int|null  $patientId  Patient ID (if applicable)
     * @param  array  $details  Additional details
     */
    public function logAccess(User $medicalProfessional, string $action, ?int $patientId = null, array $details = []): void
    {
        DataAccessLog::create([
            'user_id' => $patientId,
            'medical_professional_id' => $medicalProfessional->id,
            'access_type' => $action,
            'data_scope' => $details['data_scope'] ?? ['general_access'],
            'accessed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'notes' => json_encode($details),
        ]);
    }

    /**
     * Grant consent using simplified parameters for controller use.
     *
     * @param  User  $patient  The patient granting consent
     * @param  int  $medicalProfessionalId  Medical professional ID
     * @param  string  $accessLevel  Access level
     * @param  string|null  $expiresAt  Expiration date
     * @param  string|null  $purpose  Purpose of consent
     * @param  array|null  $conditions  Additional conditions
     */
    public function grantConsent(User $patient, int $medicalProfessionalId, string $accessLevel, ?string $expiresAt = null, ?string $purpose = null, ?array $conditions = null): PatientProviderConsent
    {
        // Find or create medical professional record
        $medicalProfessional = User::findOrFail($medicalProfessionalId);

        if (! $medicalProfessional->hasRole('medical_professional')) {
            throw new InvalidArgumentException('User is not a medical professional');
        }

        // Create consent record directly for simplified API
        return DB::transaction(function () use ($patient, $medicalProfessional, $accessLevel, $expiresAt, $purpose, $conditions) {
            // Revoke any existing active consent between these parties
            PatientProviderConsent::where('patient_id', $patient->id)
                ->where('medical_professional_id', $medicalProfessional->id)
                ->where('status', 'active')
                ->update(['status' => 'revoked', 'revoked_at' => now()]);

            // Create new consent record
            $consent = PatientProviderConsent::create([
                'patient_id' => $patient->id,
                'medical_professional_id' => $medicalProfessional->id,
                'access_level' => $accessLevel,
                'expires_at' => $expiresAt ? Carbon::parse($expiresAt) : null,
                'purpose' => $purpose,
                'conditions' => $conditions,
                'status' => 'active',
                'granted_at' => now(),
            ]);

            // Log the consent grant action
            $this->logAccess($medicalProfessional, 'consent_granted', $patient->id, [
                'consent_id' => $consent->id,
                'access_level' => $accessLevel,
                'expires_at' => $expiresAt,
            ]);

            return $consent->fresh(['patient', 'medicalProfessional']);
        });
    }

    /**
     * Revoke consent with simplified interface for controller use.
     *
     * @param  PatientProviderConsent  $consent  The consent to revoke
     */
    public function revokeConsent(PatientProviderConsent $consent): PatientProviderConsent
    {
        if ($consent->status === 'revoked') {
            return $consent;
        }

        return DB::transaction(function () use ($consent) {
            $consent->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);

            // Log the consent revocation
            $this->logAccess(
                User::find($consent->medical_professional_id),
                'consent_revoked',
                $consent->patient_id,
                ['consent_id' => $consent->id]
            );

            return $consent->fresh();
        });
    }
}
