<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MedicalProfessional extends Model
{
    use HasFactory;

    protected $table = 'medical_professionals';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'license_number',
        'specialty',
        'organization',
        'verified_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the patients who have granted consent to this medical professional.
     */
    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'patient_provider_consents', 'medical_professional_id', 'user_id')
            ->withPivot([
                'consent_given_at', 'consent_expires_at', 'access_level',
                'granted_by_user', 'emergency_access', 'is_active',
                'revoked_at', 'notes',
            ])
            ->withTimestamps();
    }

    /**
     * Get the data access logs for this medical professional.
     */
    public function dataAccessLogs(): HasMany
    {
        return $this->hasMany(DataAccessLog::class);
    }

    /**
     * Check if the medical professional is verified.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Get the patients with active consent for this medical professional.
     */
    public function getPatients()
    {
        return $this->patients()
            ->wherePivot('is_active', true)
            ->wherePivot(function ($query) {
                $query->whereNull('consent_expires_at')
                    ->orWhere('consent_expires_at', '>', now());
            });
    }
}
