<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'date_of_birth',
        'gender',
        'height',
        'medical_conditions',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'medical_conditions' => 'array',
            'height' => 'decimal:2',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the user's vital signs records.
     */
    public function vitalSignsRecords(): HasMany
    {
        return $this->hasMany(VitalSignsRecord::class);
    }

    /**
     * Get the user's recommendations.
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    /**
     * Get the medical professionals who have consent to access this user's data.
     */
    public function medicalProfessionals(): BelongsToMany
    {
        return $this->belongsToMany(MedicalProfessional::class, 'patient_provider_consents')
            ->withPivot([
                'consent_given_at', 'consent_expires_at', 'access_level',
                'granted_by_user', 'emergency_access', 'is_active',
                'revoked_at', 'notes',
            ])
            ->withTimestamps();
    }

    /**
     * Get the user's patient provider consents.
     */
    public function patientProviderConsents(): HasMany
    {
        return $this->hasMany(PatientProviderConsent::class);
    }

    /**
     * Get the user's data access logs.
     */
    public function dataAccessLogs(): HasMany
    {
        return $this->hasMany(DataAccessLog::class);
    }

    /**
     * Get the user's age in years.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /**
     * Check if the user has any medical conditions.
     */
    public function hasMedicalConditions(): bool
    {
        return ! empty($this->medical_conditions);
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        // For now, we'll use a simple role field
        // In a real application, you might use a more sophisticated role system
        return $this->role === $role;
    }
}
