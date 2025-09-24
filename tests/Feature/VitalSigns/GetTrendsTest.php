<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('can get vital signs trends', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/vital-signs/trends');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'vital_sign_type' => [
                        'id',
                        'name',
                        'display_name',
                        'unit_primary',
                    ],
                    'trend_data' => [
                        'period',
                        'values' => [
                            '*' => [
                                'date',
                                'value_primary',
                                'value_secondary',
                            ],
                        ],
                        'statistics' => [
                            'average',
                            'min',
                            'max',
                            'trend_direction',
                            'trend_percentage',
                        ],
                    ],
                ],
            ],
            'meta' => [
                'period',
                'from_date',
                'to_date',
                'total_records',
            ],
        ]);
});

test('can filter trends by vital sign type', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/vital-signs/trends?type=blood_pressure');

    $response->assertOk();
});

test('can specify trends period', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/vital-signs/trends?period=week');

    $response->assertOk();
});

test('can specify custom date range for trends', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/vital-signs/trends?from_date=2024-01-01&to_date=2024-01-31');

    $response->assertOk();
});

test('validates period parameter', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/vital-signs/trends?period=invalid');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['period']);
});

test('requires authentication to get trends', function () {
    $response = $this->getJson('/api/vital-signs/trends');

    $response->assertUnauthorized();
});
