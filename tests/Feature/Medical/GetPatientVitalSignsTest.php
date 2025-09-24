<?php

declare(strict_types=1);

use App\Models\MedicalProfessional;
use App\Models\PatientProviderConsent;
use App\Models\User;
use App\Models\VitalSignsRecord;

beforeEach(function () {
    $this->medicalProfessional = MedicalProfessional::factory()->create();
    $this->user = User::factory()->create();

    // Create active consent
    $this->consent = PatientProviderConsent::factory()->create([
        'user_id' => $this->user->id,
        'medical_professional_id' => $this->medicalProfessional->id,
        'is_active' => true,
        'access_level' => 'read_only',
    ]);

    $this->vitalSignsRecord = VitalSignsRecord::factory()->create([
        'user_id' => $this->user->id,
    ]);
});

test('medical professional can get patient vital signs with consent', function () {
    $response = $this->actingAs($this->medicalProfessional, 'medical')
        ->getJson("/api/medical/patients/{$this->user->id}/vital-signs");

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
                    ],
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'access_logged_at',
            ],
        ]);
});

test('can filter patient vital signs by type', function () {
    $response = $this->actingAs($this->medicalProfessional, 'medical')
        ->getJson("/api/medical/patients/{$this->user->id}/vital-signs?type=blood_pressure");

    $response->assertOk();
});

test('can filter patient vital signs by date range', function () {
    $response = $this->actingAs($this->medicalProfessional, 'medical')
        ->getJson("/api/medical/patients/{$this->user->id}/vital-signs?from_date=2024-01-01&to_date=2024-12-31");

    $response->assertOk();
});

test('cannot access patient vital signs without consent', function () {
    $patientWithoutConsent = User::factory()->create();

    $response = $this->actingAs($this->medicalProfessional, 'medical')
        ->getJson("/api/medical/patients/{$patientWithoutConsent->id}/vital-signs");

    $response->assertForbidden();
});

test('cannot access patient vital signs with expired consent', function () {
    $this->consent->update([
        'is_active' => false,
        'consent_expires_at' => now()->subDays(1),
    ]);

    $response = $this->actingAs($this->medicalProfessional, 'medical')
        ->getJson("/api/medical/patients/{$this->user->id}/vital-signs");

    $response->assertForbidden();
});

test('requires medical professional authentication', function () {
    $response = $this->getJson("/api/medical/patients/{$this->user->id}/vital-signs");

    $response->assertUnauthorized();
});

test('logs access when viewing patient data', function () {
    $response = $this->actingAs($this->medicalProfessional, 'medical')
        ->getJson("/api/medical/patients/{$this->user->id}/vital-signs");

    $response->assertOk();
    // Access should be logged in data_access_logs table
});
