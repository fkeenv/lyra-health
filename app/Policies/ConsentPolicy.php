<?php

namespace App\Policies;

use App\Models\MedicalProfessional;
use App\Models\PatientProviderConsent;
use App\Models\User;

class ConsentPolicy
{
    /**
     * Determine whether the user can view any consent records.
     */
    public function viewAny(User $user): bool
    {
        // Users can view their own consent records
        // Medical professionals can view consents granted to them
        return true;
    }

    /**
     * Determine whether the user can view the specific consent.
     */
    public function view(User $user, PatientProviderConsent $consent): bool
    {
        // Patients can view consents they've granted
        if ($user instanceof User && $user->id === $consent->patient_id) {
            return true;
        }

        // Medical professionals can view consents granted to them
        if ($user instanceof MedicalProfessional && $user->id === $consent->medical_professional_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create consent records.
     */
    public function create(User $user): bool
    {
        // Only patients can create consent records
        // Medical professionals can request consent but cannot create it directly
        return $user instanceof User;
    }

    /**
     * Determine whether the user can update the consent.
     */
    public function update(User $user, PatientProviderConsent $consent): bool
    {
        // Only patients can modify their own consents
        if ($user instanceof User && $user->id === $consent->patient_id) {
            // Can only modify active or pending consents
            return in_array($consent->status, ['active', 'pending']);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the consent.
     */
    public function delete(User $user, PatientProviderConsent $consent): bool
    {
        // Patients can delete (revoke) their own consents
        if ($user instanceof User && $user->id === $consent->patient_id) {
            return $consent->status === 'active';
        }

        return false;
    }

    /**
     * Determine whether the user can restore a revoked consent.
     */
    public function restore(User $user, PatientProviderConsent $consent): bool
    {
        // Only patients can restore their own revoked consents within 30 days
        if ($user instanceof User && $user->id === $consent->patient_id) {
            if ($consent->status === 'revoked' && $consent->revoked_at) {
                $thirtyDaysAgo = now()->subDays(30);

                return $consent->revoked_at->gte($thirtyDaysAgo);
            }
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the consent.
     */
    public function forceDelete(User $user, PatientProviderConsent $consent): bool
    {
        // Only patients can permanently delete their own consent records
        // And only after they've been revoked for at least 90 days
        if ($user instanceof User && $user->id === $consent->patient_id) {
            if ($consent->status === 'revoked' && $consent->revoked_at) {
                $ninetyDaysAgo = now()->subDays(90);

                return $consent->revoked_at->lte($ninetyDaysAgo);
            }
        }

        return false;
    }

    /**
     * Determine whether the user can revoke a consent.
     */
    public function revoke(User $user, PatientProviderConsent $consent): bool
    {
        // Patients can revoke their active consents
        if ($user instanceof User && $user->id === $consent->patient_id) {
            return $consent->status === 'active';
        }

        return false;
    }

    /**
     * Determine whether the user can grant a pending consent.
     */
    public function grant(User $user, PatientProviderConsent $consent): bool
    {
        // Only patients can grant pending consents
        if ($user instanceof User && $user->id === $consent->patient_id) {
            return $consent->status === 'pending';
        }

        return false;
    }

    /**
     * Determine whether the user can deny a pending consent.
     */
    public function deny(User $user, PatientProviderConsent $consent): bool
    {
        // Only patients can deny pending consents
        if ($user instanceof User && $user->id === $consent->patient_id) {
            return $consent->status === 'pending';
        }

        return false;
    }

    /**
     * Determine whether the user can request consent from a patient.
     */
    public function request(User $user): bool
    {
        // Only verified medical professionals can request consent
        if ($user instanceof MedicalProfessional) {
            return $user->verification_status === 'verified';
        }

        return false;
    }

    /**
     * Determine whether the user can view access logs for a consent.
     */
    public function viewAccessLogs(User $user, PatientProviderConsent $consent): bool
    {
        // Patients can view access logs for their consents
        if ($user instanceof User && $user->id === $consent->patient_id) {
            return true;
        }

        // Medical professionals can view logs for consents granted to them
        if ($user instanceof MedicalProfessional && $user->id === $consent->medical_professional_id) {
            return $consent->status === 'active';
        }

        return false;
    }

    /**
     * Determine whether the user can modify consent scope (data types, purposes).
     */
    public function modifyScope(User $user, PatientProviderConsent $consent): bool
    {
        // Only patients can modify the scope of their active consents
        if ($user instanceof User && $user->id === $consent->patient_id) {
            return $consent->status === 'active';
        }

        return false;
    }

    /**
     * Determine whether the user can extend consent expiration.
     */
    public function extend(User $user, PatientProviderConsent $consent): bool
    {
        // Only patients can extend their active consents
        if ($user instanceof User && $user->id === $consent->patient_id) {
            return $consent->status === 'active' && $consent->expires_at && $consent->expires_at->gt(now());
        }

        return false;
    }

    /**
     * Determine whether the user can view emergency access provisions.
     */
    public function viewEmergencyAccess(User $user, PatientProviderConsent $consent): bool
    {
        // Both patients and medical professionals can view emergency access provisions
        if ($user instanceof User && $user->id === $consent->patient_id) {
            return true;
        }

        if ($user instanceof MedicalProfessional && $user->id === $consent->medical_professional_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can use emergency access to bypass normal consent.
     */
    public function emergencyAccess(User $user, int $patientId): bool
    {
        // Only verified medical professionals can use emergency access
        if ($user instanceof MedicalProfessional && $user->verification_status === 'verified') {
            // Emergency access should be logged and reviewed
            // Additional validation could include checking for existing emergency relationship
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can audit consent activities.
     */
    public function audit(User $user): bool
    {
        // Only system administrators can audit consent activities
        // This would typically check for admin role
        return false; // Implement when admin roles are added
    }
}
