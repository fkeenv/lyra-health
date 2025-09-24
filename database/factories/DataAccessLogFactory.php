<?php

namespace Database\Factories;

use App\Models\MedicalProfessional;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DataAccessLog>
 */
class DataAccessLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $accessType = fake()->randomElement(['view', 'export', 'print']);
        $sessionDuration = fake()->optional(0.8)->numberBetween(30, 3600); // 30 seconds to 1 hour

        $dataScopeOptions = [
            ['vital_signs' => ['blood_pressure', 'weight'], 'date_range' => '30_days'],
            ['vital_signs' => ['all'], 'date_range' => '90_days'],
            ['vital_signs' => ['blood_glucose'], 'date_range' => '7_days'],
            ['vital_signs' => ['oxygen_saturation', 'heart_rate'], 'date_range' => '14_days'],
            ['recommendations' => true, 'vital_signs' => ['blood_pressure'], 'date_range' => '60_days'],
        ];

        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
        ];

        return [
            'id' => Str::uuid(),
            'medical_professional_id' => MedicalProfessional::factory(),
            'user_id' => User::factory(),
            'accessed_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'access_type' => $accessType,
            'data_scope' => fake()->randomElement($dataScopeOptions),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->randomElement($userAgents),
            'session_duration' => $sessionDuration,
            'notes' => fake()->optional(0.3)->randomElement([
                'Routine patient review',
                'Emergency consultation',
                'Follow-up assessment',
                'Second opinion requested',
                'Pre-appointment review',
                'Medical history verification',
                'Treatment planning session',
            ]),
        ];
    }

    /**
     * Create a view access log.
     */
    public function viewAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_type' => 'view',
            'session_duration' => fake()->numberBetween(120, 1800), // 2-30 minutes
        ]);
    }

    /**
     * Create an export access log.
     */
    public function exportAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_type' => 'export',
            'session_duration' => fake()->numberBetween(30, 300), // 30 seconds to 5 minutes
            'notes' => fake()->randomElement([
                'Exported for medical records',
                'Data export for referral',
                'Report generation for insurance',
                'Export for patient request',
            ]),
        ]);
    }

    /**
     * Create a print access log.
     */
    public function printAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_type' => 'print',
            'session_duration' => fake()->numberBetween(60, 600), // 1-10 minutes
            'notes' => fake()->randomElement([
                'Printed for patient consultation',
                'Hard copy for medical file',
                'Printed report for referral',
                'Physical copy requested',
            ]),
        ]);
    }

    /**
     * Create a recent access log (within last 24 hours).
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'accessed_at' => fake()->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * Create an emergency access log.
     */
    public function emergencyAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_type' => 'view',
            'data_scope' => ['vital_signs' => ['all'], 'emergency_access' => true, 'date_range' => 'all'],
            'session_duration' => fake()->numberBetween(300, 2400), // 5-40 minutes
            'notes' => 'Emergency access - critical patient care',
        ]);
    }

    /**
     * Create a comprehensive data access log.
     */
    public function comprehensiveAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_scope' => [
                'vital_signs' => ['all'],
                'recommendations' => true,
                'medical_history' => true,
                'date_range' => 'all',
            ],
            'session_duration' => fake()->numberBetween(600, 3600), // 10-60 minutes
            'notes' => 'Comprehensive patient review',
        ]);
    }

    /**
     * Create a limited scope access log.
     */
    public function limitedScope(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_scope' => [
                'vital_signs' => [fake()->randomElement(['blood_pressure', 'weight', 'blood_glucose'])],
                'date_range' => '7_days',
            ],
            'session_duration' => fake()->numberBetween(60, 300), // 1-5 minutes
        ]);
    }

    /**
     * Create a mobile device access log.
     */
    public function mobileAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_agent' => fake()->randomElement([
                'Mozilla/5.0 (iPad; CPU OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
            ]),
            'session_duration' => fake()->numberBetween(30, 600), // Shorter sessions on mobile
        ]);
    }
}
