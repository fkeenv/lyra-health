<?php

namespace App\Policies;

use App\Models\MedicalProfessional;
use App\Models\User;
use App\Models\VitalSignsRecord;

class VitalSignsPolicy
{
    /**
     * Determine whether the user can view any vital signs records.
     */
    public function viewAny(User $user): bool
    {
        // Users can always view their own vital signs
        // Medical professionals need specific consent
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VitalSignsRecord $vitalSignsRecord): bool
    {
        // Users can view their own vital signs
        if ($user instanceof User && $user->id === $vitalSignsRecord->user_id) {
            return true;
        }

        // Medical professionals can view if they have active consent
        if ($user instanceof MedicalProfessional) {
            return $this->medicalProfessionalHasAccess($user, $vitalSignsRecord->user_id, 'vital_signs');
        }

        return false;
    }

    /**
     * Determine whether the user can create vital signs records.
     */
    public function create(User $user): bool
    {
        // Only regular users can create their own vital signs
        // Medical professionals cannot directly create records
        return $user instanceof User;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VitalSignsRecord $vitalSignsRecord): bool
    {
        // Users can only update their own vital signs within 24 hours of creation
        if ($user instanceof User && $user->id === $vitalSignsRecord->user_id) {
            $twentyFourHoursAgo = now()->subHours(24);

            return $vitalSignsRecord->created_at->gte($twentyFourHoursAgo);
        }

        // Medical professionals cannot update vital signs records
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VitalSignsRecord $vitalSignsRecord): bool
    {
        // Users can only delete their own vital signs within 1 hour of creation
        if ($user instanceof User && $user->id === $vitalSignsRecord->user_id) {
            $oneHourAgo = now()->subHour();

            return $vitalSignsRecord->created_at->gte($oneHourAgo);
        }

        // Medical professionals cannot delete vital signs records
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VitalSignsRecord $vitalSignsRecord): bool
    {
        // Only users can restore their own records within 7 days
        if ($user instanceof User && $user->id === $vitalSignsRecord->user_id) {
            $sevenDaysAgo = now()->subWeek();

            return $vitalSignsRecord->deleted_at?->gte($sevenDaysAgo) ?? false;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VitalSignsRecord $vitalSignsRecord): bool
    {
        // Only users can permanently delete their own records
        return $user instanceof User && $user->id === $vitalSignsRecord->user_id;
    }

    /**
     * Determine whether the user can export vital signs data.
     */
    public function export(User $user): bool
    {
        // Users can export their own data
        return $user instanceof User;
    }

    /**
     * Determine whether the user can view trends and analytics.
     */
    public function viewTrends(User $user, ?int $patientId = null): bool
    {
        // Users can view their own trends
        if ($user instanceof User && ($patientId === null || $user->id === $patientId)) {
            return true;
        }

        // Medical professionals can view patient trends with consent
        if ($user instanceof MedicalProfessional && $patientId) {
            return $this->medicalProfessionalHasAccess($user, $patientId, 'health_trends');
        }

        return false;
    }

    /**
     * Determine whether a medical professional has access to a patient's data.
     */
    protected function medicalProfessionalHasAccess(MedicalProfessional $medicalProfessional, int $patientId, string $dataType): bool
    {
        // Check if there's an active consent for this medical professional and patient
        $consent = $medicalProfessional->patientConsents()
            ->where('patient_id', $patientId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereNull('revoked_at')
            ->first();

        if (! $consent) {
            return false;
        }

        // Check if the consent includes the requested data type
        $dataTypes = is_string($consent->data_types)
            ? json_decode($consent->data_types, true)
            : $consent->data_types;

        return in_array($dataType, $dataTypes ?? []);
    }

    /**
     * Determine whether the user can flag a vital sign as abnormal.
     */
    public function flag(User $user, VitalSignsRecord $vitalSignsRecord): bool
    {
        // Only medical professionals with consent can flag readings
        if ($user instanceof MedicalProfessional) {
            return $this->medicalProfessionalHasAccess($user, $vitalSignsRecord->user_id, 'vital_signs');
        }

        return false;
    }

    /**
     * Determine whether the user can add notes to a vital sign.
     */
    public function addNotes(User $user, VitalSignsRecord $vitalSignsRecord): bool
    {
        // Users can add notes to their own records
        if ($user instanceof User && $user->id === $vitalSignsRecord->user_id) {
            return true;
        }

        // Medical professionals with consent can add clinical notes
        if ($user instanceof MedicalProfessional) {
            return $this->medicalProfessionalHasAccess($user, $vitalSignsRecord->user_id, 'vital_signs');
        }

        return false;
    }
}
