<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MedicalProfessional>
 */
class MedicalProfessionalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $specialties = [
            'Cardiology', 'Endocrinology', 'Family Medicine', 'Internal Medicine',
            'Geriatrics', 'Pulmonology', 'Nephrology', 'Neurology',
            'Emergency Medicine', 'Primary Care', 'Pediatrics', 'Oncology',
        ];

        $organizations = [
            'Metropolitan Medical Center', 'City General Hospital', 'Regional Health System',
            'University Medical Center', 'Community Health Clinic', 'Heart & Vascular Institute',
            'Diabetes Care Center', 'Primary Care Associates', 'Specialty Medical Group',
        ];

        return [
            'id' => Str::uuid(),
            'name' => 'Dr. '.fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'license_number' => strtoupper(fake()->lexify('??')).fake()->numerify('######'),
            'specialty' => fake()->randomElement($specialties),
            'organization' => fake()->randomElement($organizations),
            'verified_at' => fake()->boolean(85) ? fake()->dateTimeBetween('-2 years', 'now') : null,
            'is_active' => fake()->boolean(90),
        ];
    }

    /**
     * Create a verified medical professional.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'is_active' => true,
        ]);
    }

    /**
     * Create an unverified medical professional.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => null,
        ]);
    }

    /**
     * Create an inactive medical professional.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a cardiologist.
     */
    public function cardiologist(): static
    {
        return $this->state(fn (array $attributes) => [
            'specialty' => 'Cardiology',
            'organization' => fake()->randomElement([
                'Heart & Vascular Institute',
                'Cardiac Care Center',
                'Metropolitan Cardiology Group',
            ]),
        ]);
    }

    /**
     * Create an endocrinologist (diabetes specialist).
     */
    public function endocrinologist(): static
    {
        return $this->state(fn (array $attributes) => [
            'specialty' => 'Endocrinology',
            'organization' => fake()->randomElement([
                'Diabetes Care Center',
                'Endocrine Associates',
                'Metabolic Health Clinic',
            ]),
        ]);
    }

    /**
     * Create a primary care physician.
     */
    public function primaryCare(): static
    {
        return $this->state(fn (array $attributes) => [
            'specialty' => fake()->randomElement(['Family Medicine', 'Internal Medicine', 'Primary Care']),
            'organization' => fake()->randomElement([
                'Primary Care Associates',
                'Family Health Center',
                'Community Medical Group',
            ]),
        ]);
    }
}
