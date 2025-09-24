<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VitalSignType;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->vitalSignType = VitalSignType::factory()->bloodPressure()->create();
});

test('can create vital signs record', function () {
    $payload = [
        'vital_sign_type_id' => $this->vitalSignType->id,
        'value_primary' => 120.5,
        'value_secondary' => 80.0,
        'unit' => 'mmHg',
        'measured_at' => now()->subHours(2)->toISOString(),
        'notes' => 'Morning reading after breakfast',
        'measurement_method' => 'device',
        'device_name' => 'Omron BP Monitor',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/vital-signs', $payload);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'vital_sign_type_id',
                'value_primary',
                'value_secondary',
                'unit',
                'measured_at',
                'notes',
                'measurement_method',
                'device_name',
                'created_at',
                'updated_at',
            ],
        ]);
});

test('validates required fields when creating vital signs record', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/vital-signs', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'vital_sign_type_id',
            'value_primary',
            'unit',
            'measured_at',
        ]);
});

test('validates vital sign type exists', function () {
    $payload = [
        'vital_sign_type_id' => 999999,
        'value_primary' => 120.5,
        'unit' => 'mmHg',
        'measured_at' => '2024-01-15T10:30:00Z',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/vital-signs', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['vital_sign_type_id']);
});

test('validates measurement values are numeric', function () {
    $payload = [
        'vital_sign_type_id' => $this->vitalSignType->id,
        'value_primary' => 'invalid',
        'value_secondary' => 'also_invalid',
        'unit' => 'mmHg',
        'measured_at' => '2024-01-15T10:30:00Z',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/vital-signs', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['value_primary', 'value_secondary']);
});

test('validates measured_at is valid date', function () {
    $payload = [
        'vital_sign_type_id' => $this->vitalSignType->id,
        'value_primary' => 120.5,
        'value_secondary' => 80.0,
        'unit' => 'mmHg',
        'measured_at' => 'invalid-date',
        'measurement_method' => 'manual',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/vital-signs', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['measured_at']);
});

test('validates measurement method enum', function () {
    $payload = [
        'vital_sign_type_id' => $this->vitalSignType->id,
        'value_primary' => 120.5,
        'value_secondary' => 80.0,
        'unit' => 'mmHg',
        'measured_at' => now()->subHour()->toISOString(),
        'measurement_method' => 'invalid_method',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/vital-signs', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['measurement_method']);
});

test('requires authentication to create vital signs record', function () {
    $payload = [
        'vital_sign_type_id' => $this->vitalSignType->id,
        'value_primary' => 120.5,
        'value_secondary' => 80.0,
        'unit' => 'mmHg',
        'measured_at' => now()->subHour()->toISOString(),
        'measurement_method' => 'manual',
    ];

    $response = $this->postJson('/api/vital-signs', $payload);

    $response->assertUnauthorized();
});
