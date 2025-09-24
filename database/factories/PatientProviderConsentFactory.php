<?php

namespace Database\Factories;

use App\Models\MedicalProfessional;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientProviderConsent>
 */
class PatientProviderConsentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $consentGivenAt = fake()->dateTimeBetween('-1 year', 'now');
        $hasExpiration = fake()->boolean(70);
        $expiresAt = $hasExpiration ?
            fake()->dateTimeBetween($consentGivenAt, '+2 years') : null;

        return [
            'user_id' => User::factory(),
            'medical_professional_id' => MedicalProfessional::factory(),
            'consent_given_at' => $consentGivenAt,
            'consent_expires_at' => $expiresAt,
            'access_level' => fake()->randomElement(['read_only', 'full_access']),
            'granted_by_user' => fake()->boolean(95),
            'emergency_access' => fake()->boolean(20),
            'is_active' => fake()->boolean(85),
            'revoked_at' => fake()->boolean(15) ? fake()->dateTimeBetween($consentGivenAt, 'now') : null,
            'notes' => fake()->optional(0.4)->sentence(),
        ];
    }

    /**
     * Create an active consent.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'revoked_at' => null,
            'consent_expires_at' => fake()->dateTimeBetween('now', '+2 years'),
        ]);
    }

    /**
     * Create a revoked consent.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'revoked_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'notes' => fake()->randomElement([
                'Patient requested revocation',
                'Consent period ended',
                'No longer under care',
                'Privacy concerns',
            ]),
        ]);
    }

    /**
     * Create an expired consent.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'consent_expires_at' => fake()->dateTimeBetween('-6 months', '-1 day'),
            'is_active' => false,
        ]);
    }

    /**
     * Create emergency access consent.
     */
    public function emergencyAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'emergency_access' => true,
            'access_level' => 'full_access',
            'granted_by_user' => false,
            'notes' => 'Emergency access granted for critical care',
        ]);
    }

    /**
     * Create full access consent.
     */
    public function fullAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => 'full_access',
            'is_active' => true,
        ]);
    }

    /**
     * Create read-only access consent.
     */
    public function readOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => 'read_only',
            'is_active' => true,
        ]);
    }

    /**
     * Create a recent consent (within last 30 days).
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'consent_given_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'is_active' => true,
        ]);
    }
}
