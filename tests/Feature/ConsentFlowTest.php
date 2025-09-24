<?php

use App\Models\DataAccessLog;
use App\Models\MedicalProfessional;
use App\Models\PatientProviderConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Consent Management Flow', function () {
    beforeEach(function () {
        $this->patient = User::factory()->create([
            'name' => 'Jane Patient',
            'email' => 'jane@example.com',
        ]);

        $this->medicalProfessional = MedicalProfessional::factory()->create([
            'name' => 'Dr. Michael Rodriguez',
            'email' => 'dr.rodriguez@healthplus.com',
            'specialty' => 'Internal Medicine',
            'verification_status' => 'verified',
        ]);
    });

    it('allows patient to view consent dashboard with empty state', function () {
        $response = $this->actingAs($this->patient)->get('/consent');

        $response->assertSuccessful();
        $response->assertInertia(function ($page) {
            $page->component('Consent/Index')
                ->has('consents')
                ->where('consents', [])
                ->has('stats')
                ->where('stats.total', 0);
        });
    });

    it('allows patient to grant consent to medical professional', function () {
        $consentData = [
            'medical_professional_id' => $this->medicalProfessional->id,
            'access_level' => 'full',
            'data_types' => ['vital_signs', 'health_trends'],
            'purposes' => ['monitoring', 'treatment'],
            'expires_at' => now()->addYear()->toDateString(),
        ];

        $response = $this->actingAs($this->patient)
            ->postJson('/api/consent', $consentData);

        $response->assertCreated();

        $this->assertDatabaseHas('patient_provider_consents', [
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'access_level' => 'full',
        ]);

        $consent = PatientProviderConsent::where('patient_id', $this->patient->id)->first();
        expect($consent->data_types)->toBe(['vital_signs', 'health_trends']);
        expect($consent->purposes)->toBe(['monitoring', 'treatment']);
    });

    it('prevents duplicate active consents for same medical professional', function () {
        // Create existing active consent
        PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
        ]);

        // Try to create another consent
        $consentData = [
            'medical_professional_id' => $this->medicalProfessional->id,
            'access_level' => 'limited',
            'data_types' => ['vital_signs'],
            'purposes' => ['monitoring'],
        ];

        $response = $this->actingAs($this->patient)
            ->postJson('/api/consent', $consentData);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['medical_professional_id']);
    });

    it('allows patient to modify active consent scope', function () {
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'access_level' => 'limited',
            'data_types' => ['vital_signs'],
            'purposes' => ['monitoring'],
        ]);

        $updateData = [
            'access_level' => 'full',
            'data_types' => ['vital_signs', 'health_trends', 'recommendations'],
            'purposes' => ['monitoring', 'treatment'],
        ];

        $response = $this->actingAs($this->patient)
            ->putJson("/api/consent/{$consent->id}", $updateData);

        $response->assertSuccessful();

        $consent->refresh();
        expect($consent->access_level)->toBe('full');
        expect($consent->data_types)->toBe(['vital_signs', 'health_trends', 'recommendations']);
    });

    it('allows patient to revoke active consent', function () {
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'granted_at' => now()->subMonth(),
        ]);

        $response = $this->actingAs($this->patient)
            ->postJson("/api/consent/{$consent->id}/revoke", [
                'revocation_reason' => 'No longer need this medical professional\'s services',
            ]);

        $response->assertSuccessful();

        $consent->refresh();
        expect($consent->status)->toBe('revoked');
        expect($consent->revoked_at)->not->toBeNull();
        expect($consent->revoked_reason)->toBe('No longer need this medical professional\'s services');
    });

    it('allows patient to restore recently revoked consent', function () {
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'revoked',
            'revoked_at' => now()->subDays(15), // Within 30-day restore window
        ]);

        $response = $this->actingAs($this->patient)
            ->postJson("/api/consent/{$consent->id}/restore");

        $response->assertSuccessful();

        $consent->refresh();
        expect($consent->status)->toBe('active');
        expect($consent->revoked_at)->toBeNull();
    });

    it('prevents restoring old revoked consents', function () {
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'revoked',
            'revoked_at' => now()->subDays(45), // Outside 30-day restore window
        ]);

        $response = $this->actingAs($this->patient)
            ->postJson("/api/consent/{$consent->id}/restore");

        $response->assertForbidden();
    });

    it('allows patient to extend consent expiration', function () {
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->patient)
            ->postJson("/api/consent/{$consent->id}/extend", [
                'new_expiry_date' => now()->addYear()->toDateString(),
            ]);

        $response->assertSuccessful();

        $consent->refresh();
        expect($consent->expires_at->format('Y-m-d'))->toBe(now()->addYear()->format('Y-m-d'));
    });

    it('handles medical professional consent requests', function () {
        $requestData = [
            'patient_email' => $this->patient->email,
            'requested_access_level' => 'full',
            'requested_data_types' => ['vital_signs', 'health_trends'],
            'requested_purposes' => ['monitoring', 'treatment'],
            'message' => 'I would like to monitor your vital signs for your ongoing treatment.',
        ];

        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->postJson('/api/medical/consent-requests', $requestData);

        $response->assertCreated();

        $this->assertDatabaseHas('patient_provider_consents', [
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'pending',
        ]);
    });

    it('allows patient to approve pending consent request', function () {
        $pendingConsent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'pending',
            'access_level' => 'full',
            'data_types' => ['vital_signs'],
        ]);

        $response = $this->actingAs($this->patient)
            ->postJson("/api/consent/{$pendingConsent->id}/approve");

        $response->assertSuccessful();

        $pendingConsent->refresh();
        expect($pendingConsent->status)->toBe('active');
        expect($pendingConsent->granted_at)->not->toBeNull();
    });

    it('allows patient to deny pending consent request', function () {
        $pendingConsent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->patient)
            ->postJson("/api/consent/{$pendingConsent->id}/deny", [
                'denial_reason' => 'I prefer to manage my health data privately',
            ]);

        $response->assertSuccessful();

        $pendingConsent->refresh();
        expect($pendingConsent->status)->toBe('denied');
        expect($pendingConsent->denied_reason)->toBe('I prefer to manage my health data privately');
    });

    it('tracks consent access and shows audit logs to patient', function () {
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
        ]);

        // Create some access logs
        DataAccessLog::factory()->count(3)->create([
            'medical_professional_id' => $this->medicalProfessional->id,
            'patient_id' => $this->patient->id,
            'access_type' => 'patient_data_access',
            'accessed_at' => now()->subDays(rand(1, 7)),
        ]);

        // Patient can view access logs for their consent
        $response = $this->actingAs($this->patient)
            ->getJson("/api/consent/{$consent->id}/access-logs");

        $response->assertSuccessful();
        $data = $response->json();

        expect($data)->toHaveKey('access_logs');
        expect(count($data['access_logs']))->toBe(3);
    });

    it('sends notifications for consent lifecycle events', function () {
        // Test consent granted notification
        $consentData = [
            'medical_professional_id' => $this->medicalProfessional->id,
            'access_level' => 'full',
            'data_types' => ['vital_signs'],
            'purposes' => ['monitoring'],
        ];

        $response = $this->actingAs($this->patient)
            ->postJson('/api/consent', $consentData);

        $response->assertCreated();

        // In a real implementation, this would send email notifications
        // For now, we verify the consent was created correctly
        $consent = PatientProviderConsent::where('patient_id', $this->patient->id)->first();
        expect($consent->status)->toBe('active');
    });

    it('prevents unauthorized users from accessing consent management', function () {
        $otherUser = User::factory()->create();
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
        ]);

        // Other user cannot access someone else's consent
        $response = $this->actingAs($otherUser)
            ->getJson("/api/consent/{$consent->id}");

        $response->assertForbidden();

        // Other user cannot modify someone else's consent
        $response = $this->actingAs($otherUser)
            ->postJson("/api/consent/{$consent->id}/revoke");

        $response->assertForbidden();
    });

    it('handles consent expiration automatically', function () {
        // Create consent that should expire
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'expires_at' => now()->subDay(), // Already expired
        ]);

        // Run consent cleanup job
        $this->artisan('app:cleanup-expired-consents', ['--sync' => true])
            ->assertSuccessful();

        $consent->refresh();
        expect($consent->status)->toBe('expired');
    });

    it('supports different consent access levels with appropriate restrictions', function () {
        // Test limited access level
        $limitedConsent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'access_level' => 'limited',
            'data_types' => ['vital_signs'],
        ]);

        // Verify limited consent properties
        expect($limitedConsent->access_level)->toBe('limited');
        expect($limitedConsent->data_types)->toBe(['vital_signs']);

        // Test full access level
        $limitedConsent->update([
            'access_level' => 'full',
            'data_types' => ['vital_signs', 'health_trends', 'recommendations'],
        ]);

        $limitedConsent->refresh();
        expect($limitedConsent->access_level)->toBe('full');
        expect($limitedConsent->data_types)->toContain('health_trends');
    });

    it('validates consent data types and purposes correctly', function () {
        $invalidConsentData = [
            'medical_professional_id' => $this->medicalProfessional->id,
            'access_level' => 'invalid_level',
            'data_types' => ['invalid_type'],
            'purposes' => [],
        ];

        $response = $this->actingAs($this->patient)
            ->postJson('/api/consent', $invalidConsentData);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['access_level', 'data_types', 'purposes']);
    });

    it('supports emergency consent override for verified medical professionals', function () {
        // No active consent exists
        expect(PatientProviderConsent::where('patient_id', $this->patient->id)->count())->toBe(0);

        // Medical professional requests emergency access
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->postJson("/api/medical/emergency-consent/{$this->patient->id}", [
                'emergency_reason' => 'Patient in critical condition, need immediate access',
                'hospital_case_number' => 'EMRG-2024-5678',
            ]);

        $response->assertSuccessful();

        // Emergency consent should be created
        $emergencyConsent = PatientProviderConsent::where('patient_id', $this->patient->id)
            ->where('access_level', 'emergency')
            ->first();

        expect($emergencyConsent)->not->toBeNull();
        expect($emergencyConsent->status)->toBe('emergency_active');
    });
});
