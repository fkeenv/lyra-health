<?php

declare(strict_types=1);

use App\Models\MedicalProfessional;
use App\Models\PatientProviderConsent;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->medicalProfessional = MedicalProfessional::factory()->create();

    $this->consent = PatientProviderConsent::factory()->create([
        'user_id' => $this->user->id,
        'medical_professional_id' => $this->medicalProfessional->id,
        'is_active' => true,
    ]);
});

test('user can get their consent records', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/consent');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'medical_professional' => [
                        'id',
                        'name',
                        'license_number',
                        'specialty',
                        'organization',
                        'verified_at',
                    ],
                    'consent_given_at',
                    'consent_expires_at',
                    'access_level',
                    'granted_by_user',
                    'emergency_access',
                    'is_active',
                    'revoked_at',
                    'notes',
                    'created_at',
                    'updated_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'active_count',
                'expired_count',
                'revoked_count',
            ],
        ]);
});

test('can filter consent by status', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/consent?status=active');

    $response->assertOk();
});

test('can filter consent by access level', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/consent?access_level=full_access');

    $response->assertOk();
});

test('can search consent by medical professional name', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/consent?search=Dr+Smith');

    $response->assertOk();
});

test('validates filter parameters', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/consent?status=invalid&access_level=invalid');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'access_level']);
});

test('requires authentication to get consent records', function () {
    $response = $this->getJson('/api/consent');

    $response->assertUnauthorized();
});
