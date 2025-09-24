<?php

declare(strict_types=1);

use App\Models\MedicalProfessional;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->medicalProfessional = MedicalProfessional::factory()->create();
});

test('user can grant consent to medical professional', function () {
    $payload = [
        'medical_professional_id' => $this->medicalProfessional->id,
        'access_level' => 'read_only',
        'consent_expires_at' => '2025-12-31T23:59:59Z',
        'emergency_access' => false,
        'notes' => 'Consent for routine checkups',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/consent', $payload);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'medical_professional' => [
                    'id',
                    'name',
                    'license_number',
                    'specialty',
                    'organization',
                ],
                'consent_given_at',
                'consent_expires_at',
                'access_level',
                'granted_by_user',
                'emergency_access',
                'is_active',
                'notes',
                'created_at',
                'updated_at',
            ],
        ]);
});

test('validates required fields when granting consent', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/consent', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'medical_professional_id',
            'access_level',
        ]);
});

test('validates medical professional exists and is verified', function () {
    $payload = [
        'medical_professional_id' => 999999,
        'access_level' => 'read_only',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/consent', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['medical_professional_id']);
});

test('validates access level enum', function () {
    $payload = [
        'medical_professional_id' => $this->medicalProfessional->id,
        'access_level' => 'invalid_level',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/consent', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['access_level']);
});

test('validates expiration date is in future', function () {
    $payload = [
        'medical_professional_id' => $this->medicalProfessional->id,
        'access_level' => 'read_only',
        'consent_expires_at' => '2020-01-01T00:00:00Z',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/consent', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['consent_expires_at']);
});

test('cannot grant duplicate active consent to same medical professional', function () {
    // Grant initial consent
    $this->actingAs($this->user)
        ->postJson('/api/consent', [
            'medical_professional_id' => $this->medicalProfessional->id,
            'access_level' => 'read_only',
        ]);

    // Try to grant another consent to same professional
    $response = $this->actingAs($this->user)
        ->postJson('/api/consent', [
            'medical_professional_id' => $this->medicalProfessional->id,
            'access_level' => 'full_access',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['medical_professional_id']);
});

test('requires authentication to grant consent', function () {
    $payload = [
        'medical_professional_id' => $this->medicalProfessional->id,
        'access_level' => 'read_only',
    ];

    $response = $this->postJson('/api/consent', $payload);

    $response->assertUnauthorized();
});
