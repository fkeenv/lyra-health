<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientProviderConsent extends Model
{
    use HasFactory;

    protected $table = 'patient_provider_consents';

    protected $fillable = [
        'user_id',
        'medical_professional_id',
        'consent_given_at',
        'consent_expires_at',
        'access_level',
        'granted_by_user',
        'emergency_access',
        'is_active',
        'revoked_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'consent_given_at' => 'datetime',
            'consent_expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'granted_by_user' => 'boolean',
            'emergency_access' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user (patient) associated with this consent.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the medical professional associated with this consent.
     */
    public function medicalProfessional(): BelongsTo
    {
        return $this->belongsTo(MedicalProfessional::class);
    }

    /**
     * Check if the consent is currently active.
     */
    public function isActive(): bool
    {
        if (! $this->is_active || $this->revoked_at !== null) {
            return false;
        }

        if ($this->consent_expires_at !== null && $this->consent_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the consent has expired.
     */
    public function isExpired(): bool
    {
        return $this->consent_expires_at !== null && $this->consent_expires_at->isPast();
    }

    /**
     * Check if the consent grants full access.
     */
    public function hasFullAccess(): bool
    {
        return $this->access_level === 'full_access';
    }
}
