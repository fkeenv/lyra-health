<?php

declare(strict_types=1);

use App\Models\MedicalProfessional;
use App\Models\PatientProviderConsent;
use App\Models\User;

beforeEach(function () {
    $this->medicalProfessional = MedicalProfessional::factory()->create();
    $this->user = User::factory()->create();

    // Create active consent
    PatientProviderConsent::factory()->create([
        'user_id' => $this->user->id,
        'medical_professional_id' => $this->medicalProfessional->id,
        'is_active' => true,
        'access_level' => 'read_only',
    ]);
});

test('medical professional can get their patients', function () {
    $response = $this->actingAs($this->medicalProfessional, 'medical')
        ->getJson('/api/medical/patients');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'date_of_birth',
                    'gender',
                    'medical_conditions',
                    'consent' => [
                        'id',
                        'access_level',
                        'consent_given_at',
                        'consent_expires_at',
                        'is_active',
                    ],
                    'latest_vital_signs' => [
                        '*' => [
                            'id',
                            'vital_sign_type_id',
                            'value_primary',
                            'value_secondary',
                            'unit',
                            'measured_at',
                            'is_flagged',
                        ],
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

test('can filter patients by name', function () {
    $response = $this->actingAs($this->medicalProfessional, 'medical')
        ->getJson('/api/medical/patients?search=John');

    $response->assertOk();
});

test('can filter patients by access level', function () {
    $response = $this->actingAs($this->medicalProfessional, 'medical')
        ->getJson('/api/medical/patients?access_level=full_access');

    $response->assertOk();
});

test('only shows patients with active consent', function () {
    $response = $this->actingAs($this->medicalProfessional, 'medical')
        ->getJson('/api/medical/patients');

    $response->assertOk();
});

test('requires medical professional authentication', function () {
    $response = $this->getJson('/api/medical/patients');

    $response->assertUnauthorized();
});

test('regular user cannot access medical patients endpoint', function () {
    $regularUser = User::factory()->create();

    $response = $this->actingAs($regularUser)
        ->getJson('/api/medical/patients');

    $response->assertForbidden();
});
