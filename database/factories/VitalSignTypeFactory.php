<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VitalSignType>
 */
class VitalSignTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            'weight' => [
                'display_name' => 'Weight',
                'unit_primary' => 'kg',
                'unit_secondary' => 'lbs',
                'min_value' => 30,
                'max_value' => 300,
                'normal_range_min' => 50,
                'normal_range_max' => 100,
                'warning_range_min' => 40,
                'warning_range_max' => 150,
                'has_secondary_value' => false,
                'input_type' => 'single',
            ],
            'temperature' => [
                'display_name' => 'Body Temperature',
                'unit_primary' => '°C',
                'unit_secondary' => '°F',
                'min_value' => 30,
                'max_value' => 45,
                'normal_range_min' => 36.1,
                'normal_range_max' => 37.2,
                'warning_range_min' => 35,
                'warning_range_max' => 39,
                'has_secondary_value' => false,
                'input_type' => 'single',
            ],
            'heart_rate' => [
                'display_name' => 'Heart Rate',
                'unit_primary' => 'bpm',
                'unit_secondary' => null,
                'min_value' => 40,
                'max_value' => 200,
                'normal_range_min' => 60,
                'normal_range_max' => 100,
                'warning_range_min' => 50,
                'warning_range_max' => 120,
                'has_secondary_value' => false,
                'input_type' => 'single',
            ],
        ];

        $type = fake()->randomKey($types);
        $config = $types[$type];

        return [
            'name' => $type,
            'display_name' => $config['display_name'],
            'unit_primary' => $config['unit_primary'],
            'unit_secondary' => $config['unit_secondary'],
            'min_value' => $config['min_value'],
            'max_value' => $config['max_value'],
            'normal_range_min' => $config['normal_range_min'],
            'normal_range_max' => $config['normal_range_max'],
            'warning_range_min' => $config['warning_range_min'],
            'warning_range_max' => $config['warning_range_max'],
            'has_secondary_value' => $config['has_secondary_value'],
            'input_type' => $config['input_type'],
            'is_active' => fake()->boolean(95),
        ];
    }

    /**
     * Create blood pressure type (dual input).
     */
    public function bloodPressure(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'blood_pressure',
            'display_name' => 'Blood Pressure',
            'unit_primary' => 'mmHg',
            'unit_secondary' => null,
            'min_value' => 60,
            'max_value' => 250,
            'normal_range_min' => 90,
            'normal_range_max' => 140,
            'warning_range_min' => 80,
            'warning_range_max' => 160,
            'has_secondary_value' => true,
            'input_type' => 'dual',
            'is_active' => true,
        ]);
    }

    /**
     * Create oxygen saturation type.
     */
    public function oxygenSaturation(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'oxygen_saturation',
            'display_name' => 'Oxygen Saturation',
            'unit_primary' => '%',
            'unit_secondary' => null,
            'min_value' => 70,
            'max_value' => 100,
            'normal_range_min' => 95,
            'normal_range_max' => 100,
            'warning_range_min' => 90,
            'warning_range_max' => 100,
            'has_secondary_value' => false,
            'input_type' => 'single',
            'is_active' => true,
        ]);
    }

    /**
     * Create blood glucose type.
     */
    public function bloodGlucose(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'blood_glucose',
            'display_name' => 'Blood Glucose',
            'unit_primary' => 'mg/dL',
            'unit_secondary' => 'mmol/L',
            'min_value' => 40,
            'max_value' => 500,
            'normal_range_min' => 70,
            'normal_range_max' => 100,
            'warning_range_min' => 60,
            'warning_range_max' => 140,
            'has_secondary_value' => false,
            'input_type' => 'single',
            'is_active' => true,
        ]);
    }

    /**
     * Create inactive vital sign type.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
