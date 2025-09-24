<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-13 years')->format('Y-m-d'),
            'gender' => fake()->optional(0.8)->randomElement(['male', 'female', 'other', 'prefer_not_to_say']),
            'height' => fake()->optional(0.7)->randomFloat(2, 50, 250),
            'medical_conditions' => fake()->optional(0.3)->randomElements([
                'Hypertension', 'Diabetes Type 2', 'Asthma', 'High Cholesterol',
                'Arthritis', 'Heart Disease', 'Obesity', 'Depression',
            ], fake()->numberBetween(1, 3)),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a user with specific medical conditions.
     */
    public function withMedicalConditions(array $conditions): static
    {
        return $this->state(fn (array $attributes) => [
            'medical_conditions' => $conditions,
        ]);
    }

    /**
     * Create an elderly user (65+ years old).
     */
    public function elderly(): static
    {
        return $this->state(fn (array $attributes) => [
            'date_of_birth' => fake()->dateTimeBetween('-90 years', '-65 years')->format('Y-m-d'),
            'medical_conditions' => fake()->optional(0.8)->randomElements([
                'Hypertension', 'Diabetes Type 2', 'Heart Disease', 'Arthritis',
                'High Cholesterol', 'Osteoporosis',
            ], fake()->numberBetween(1, 4)),
        ]);
    }

    /**
     * Create a young adult user (18-35 years old).
     */
    public function youngAdult(): static
    {
        return $this->state(fn (array $attributes) => [
            'date_of_birth' => fake()->dateTimeBetween('-35 years', '-18 years')->format('Y-m-d'),
            'medical_conditions' => fake()->optional(0.1)->randomElements([
                'Asthma', 'Allergies', 'Depression', 'Anxiety',
            ], fake()->numberBetween(1, 2)),
        ]);
    }
}
