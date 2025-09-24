<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VitalSignType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VitalSignsRecord>
 */
class VitalSignsRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $measurementMethod = fake()->randomElement(['manual', 'device', 'estimated']);
        $deviceName = $measurementMethod === 'device' ? fake()->randomElement([
            'Omron BP742', 'ReliOn BP200', 'Withings Body+', 'Oximeter Pro',
            'Accu-Chek Guide', 'OneTouch Ultra', 'FitBit Aria'
        ]) : null;

        return [
            'id' => Str::uuid(),
            'user_id' => User::factory(),
            'vital_sign_type_id' => VitalSignType::factory(),
            'value_primary' => fake()->randomFloat(2, 50, 200),
            'value_secondary' => fake()->optional(0.3)->randomFloat(2, 30, 150),
            'unit' => fake()->randomElement(['mmHg', '%', 'kg', 'mg/dL', '°C', 'bpm']),
            'measured_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'notes' => fake()->optional(0.4)->sentence(),
            'measurement_method' => $measurementMethod,
            'device_name' => $deviceName,
            'is_flagged' => fake()->boolean(5),
            'flag_reason' => fake()->optional(0.05)->randomElement([
                'Outside normal range', 'Unusual spike', 'Measurement error possible'
            ]),
        ];
    }

    /**
     * Create a blood pressure reading.
     */
    public function bloodPressure(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_primary' => fake()->numberBetween(90, 160), // Systolic
            'value_secondary' => fake()->numberBetween(60, 100), // Diastolic
            'unit' => 'mmHg',
        ]);
    }

    /**
     * Create an oxygen saturation reading.
     */
    public function oxygenSaturation(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_primary' => fake()->numberBetween(90, 100),
            'value_secondary' => null,
            'unit' => '%',
        ]);
    }

    /**
     * Create a weight measurement.
     */
    public function weight(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_primary' => fake()->randomFloat(2, 50, 150),
            'value_secondary' => null,
            'unit' => 'kg',
        ]);
    }

    /**
     * Create a blood glucose reading.
     */
    public function bloodGlucose(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_primary' => fake()->numberBetween(70, 140),
            'value_secondary' => null,
            'unit' => 'mg/dL',
        ]);
    }

    /**
     * Create a flagged reading (outside normal range).
     */
    public function flagged(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_flagged' => true,
            'flag_reason' => fake()->randomElement([
                'Outside normal range',
                'Unusual spike detected',
                'Measurement error possible',
                'Requires medical attention'
            ]),
        ]);
    }

    /**
     * Create a recent reading (within last 24 hours).
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'measured_at' => fake()->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * Create a reading with device measurement.
     */
    public function fromDevice(): static
    {
        return $this->state(fn (array $attributes) => [
            'measurement_method' => 'device',
            'device_name' => fake()->randomElement([
                'Omron BP742N',
                'ReliOn BP200',
                'Withings Body+ Scale',
                'Pulse Oximeter Pro',
                'Accu-Chek Guide',
                'OneTouch Ultra Plus'
            ]),
        ]);
    }

    /**
     * Create a reading with notes.
     */
    public function withNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => fake()->randomElement([
                'Feeling stressed today',
                'After morning exercise',
                'Before medication',
                'Post-meal reading',
                'Fasting measurement',
                'After work',
                'Morning routine check'
            ]),
        ]);
    }
}
