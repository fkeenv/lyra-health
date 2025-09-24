<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VitalSignTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vitalSignTypes = [
            [
                'name' => 'blood_pressure',
                'display_name' => 'Blood Pressure',
                'unit_primary' => 'mmHg',
                'unit_secondary' => null,
                'min_value' => 60.00,
                'max_value' => 250.00,
                'normal_range_min' => 90.00,
                'normal_range_max' => 140.00,
                'warning_range_min' => 80.00,
                'warning_range_max' => 160.00,
                'has_secondary_value' => true,
                'input_type' => 'dual',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'oxygen_saturation',
                'display_name' => 'Oxygen Saturation',
                'unit_primary' => '%',
                'unit_secondary' => null,
                'min_value' => 70.00,
                'max_value' => 100.00,
                'normal_range_min' => 95.00,
                'normal_range_max' => 100.00,
                'warning_range_min' => 90.00,
                'warning_range_max' => 100.00,
                'has_secondary_value' => false,
                'input_type' => 'single',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'weight',
                'display_name' => 'Weight',
                'unit_primary' => 'kg',
                'unit_secondary' => 'lbs',
                'min_value' => 30.00,
                'max_value' => 300.00,
                'normal_range_min' => 50.00,
                'normal_range_max' => 100.00,
                'warning_range_min' => 40.00,
                'warning_range_max' => 150.00,
                'has_secondary_value' => false,
                'input_type' => 'single',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'blood_glucose',
                'display_name' => 'Blood Glucose',
                'unit_primary' => 'mg/dL',
                'unit_secondary' => 'mmol/L',
                'min_value' => 40.00,
                'max_value' => 500.00,
                'normal_range_min' => 70.00,
                'normal_range_max' => 100.00,
                'warning_range_min' => 60.00,
                'warning_range_max' => 140.00,
                'has_secondary_value' => false,
                'input_type' => 'single',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'heart_rate',
                'display_name' => 'Heart Rate',
                'unit_primary' => 'bpm',
                'unit_secondary' => null,
                'min_value' => 40.00,
                'max_value' => 200.00,
                'normal_range_min' => 60.00,
                'normal_range_max' => 100.00,
                'warning_range_min' => 50.00,
                'warning_range_max' => 120.00,
                'has_secondary_value' => false,
                'input_type' => 'single',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'body_temperature',
                'display_name' => 'Body Temperature',
                'unit_primary' => '°C',
                'unit_secondary' => '°F',
                'min_value' => 30.00,
                'max_value' => 45.00,
                'normal_range_min' => 36.10,
                'normal_range_max' => 37.20,
                'warning_range_min' => 35.00,
                'warning_range_max' => 39.00,
                'has_secondary_value' => false,
                'input_type' => 'single',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('vital_sign_types')->insert($vitalSignTypes);
    }
}
