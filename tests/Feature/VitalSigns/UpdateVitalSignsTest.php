<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->vitalSignType = VitalSignType::factory()->create();
    $this->vitalSignsRecord = VitalSignsRecord::factory()->create([
        'user_id' => $this->user->id,
        'vital_sign_type_id' => $this->vitalSignType->id,
    ]);
});

test('can update own vital signs record', function () {
    $payload = [
        'value_primary' => 130.0,
        'value_secondary' => 85.0,
        'notes' => 'Updated measurement',
        'measurement_method' => 'manual',
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/api/vital-signs/{$this->vitalSignsRecord->id}", $payload);

    $response->assertOk()
        ->assertJsonStructure([
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
                'is_flagged',
                'flag_reason',
                'created_at',
                'updated_at',
            ],
        ]);
});

test('validates numeric values when updating', function () {
    $payload = [
        'value_primary' => 'invalid',
        'value_secondary' => 'also_invalid',
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/api/vital-signs/{$this->vitalSignsRecord->id}", $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['value_primary', 'value_secondary']);
});

test('cannot update another users vital signs record', function () {
    $otherUser = User::factory()->create();
    $otherRecord = VitalSignsRecord::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/vital-signs/{$otherRecord->id}", ['notes' => 'Updated']);

    $response->assertForbidden();
});

test('returns not found for non-existent record', function () {
    $response = $this->actingAs($this->user)
        ->putJson('/api/vital-signs/non-existent-id', ['notes' => 'Updated']);

    $response->assertNotFound();
});

test('requires authentication to update vital signs record', function () {
    $response = $this->putJson("/api/vital-signs/{$this->vitalSignsRecord->id}", ['notes' => 'Updated']);

    $response->assertUnauthorized();
});
