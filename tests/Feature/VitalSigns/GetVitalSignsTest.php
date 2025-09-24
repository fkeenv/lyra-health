<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('can get authenticated user vital signs records', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/vital-signs');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'vital_sign_type_id',
                    'value_primary',
                    'value_secondary',
                    'unit',
                    'measured_at',
                    'notes',
                    'measurement_method',
                    'device_name',
                    'is_flagged',
                    'flag_reason',
                    'created_at',
                    'updated_at',
                    'vital_sign_type' => [
                        'id',
                        'name',
                        'display_name',
                        'unit_primary',
                        'unit_secondary',
                        'has_secondary_value',
                        'input_type',
                    ],
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
});

test('can filter vital signs by type', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/vital-signs?type=blood_pressure');

    $response->assertOk();
});

test('can filter vital signs by date range', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/vital-signs?from_date=2024-01-01&to_date=2024-12-31');

    $response->assertOk();
});

test('can limit vital signs results', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/vital-signs?per_page=10');

    $response->assertOk();
});

test('requires authentication to get vital signs', function () {
    $response = $this->getJson('/api/vital-signs');

    $response->assertUnauthorized();
});

test('validates per_page parameter', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/vital-signs?per_page=200');

    $response->assertUnprocessable();
});
