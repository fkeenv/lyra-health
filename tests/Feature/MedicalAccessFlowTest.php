<?php

use App\Models\DataAccessLog;
use App\Models\MedicalProfessional;
use App\Models\PatientProviderConsent;
use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Medical Professional Access Flow', function () {
    beforeEach(function () {
        // Create test users and medical professionals
        $this->patient = User::factory()->create([
            'name' => 'John Patient',
            'email' => 'patient@example.com',
            'role' => 'patient',
        ]);

        $this->medicalProfessional = MedicalProfessional::factory()->create([
            'name' => 'Dr. Sarah Wilson',
            'email' => 'dr.wilson@medicenter.com',
            'specialty' => 'Cardiology',
            'license_number' => 'MD12345',
            'facility_name' => 'City Medical Center',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        // Create vital sign types
        $this->vitalSignType = VitalSignType::factory()->create([
            'name' => 'blood_pressure',
            'display_name' => 'Blood Pressure',
            'unit_primary' => 'mmHg',
            'unit_secondary' => 'mmHg',
            'has_secondary_value' => true,
            'normal_range_min' => 90,
            'normal_range_max' => 140,
        ]);

        // Create some vital signs records for the patient
        VitalSignsRecord::factory()->count(5)->create([
            'user_id' => $this->patient->id,
            'vital_sign_type_id' => $this->vitalSignType->id,
            'measured_at' => now()->subDays(rand(1, 30)),
        ]);
    });

    it('allows medical professional authentication', function () {
        $response = $this->post('/medical/login', [
            'email' => $this->medicalProfessional->email,
            'password' => 'password', // Default from factory
        ]);

        $response->assertSuccessful();
        $this->assertAuthenticatedAs($this->medicalProfessional, 'medical');
    });

    it('prevents access without active consent', function () {
        // Medical professional tries to access patient data without consent
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->getJson("/api/medical/patients/{$this->patient->id}/vital-signs");

        $response->assertForbidden();
    });

    it('allows patient data access with active consent', function () {
        // Create active consent
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => now()->addYear(),
            'access_level' => 'full',
            'data_types' => ['vital_signs', 'health_trends'],
            'purposes' => ['monitoring', 'treatment'],
        ]);

        // Medical professional can now access patient data
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->getJson("/api/medical/patients/{$this->patient->id}/vital-signs");

        $response->assertSuccessful();
        $data = $response->json();

        expect($data)->toHaveKey('data');
        expect($data['data'])->toHaveCount(5); // Should return all 5 vital signs records

        // Verify access was logged
        $this->assertDatabaseHas('data_access_logs', [
            'medical_professional_id' => $this->medicalProfessional->id,
            'patient_id' => $this->patient->id,
            'access_type' => 'patient_data_access',
        ]);
    });

    it('respects consent data type restrictions', function () {
        // Create consent with limited data types
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => now()->addYear(),
            'access_level' => 'limited',
            'data_types' => ['vital_signs'], // No health_trends
            'purposes' => ['monitoring'],
        ]);

        // Can access vital signs
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->getJson("/api/medical/patients/{$this->patient->id}/vital-signs");

        $response->assertSuccessful();

        // Cannot access trends (different data type)
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->getJson("/api/medical/patients/{$this->patient->id}/trends");

        $response->assertForbidden();
    });

    it('prevents access with expired consent', function () {
        // Create expired consent
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'expired',
            'granted_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
            'access_level' => 'full',
            'data_types' => ['vital_signs'],
        ]);

        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->getJson("/api/medical/patients/{$this->patient->id}/vital-signs");

        $response->assertForbidden();
    });

    it('prevents access with revoked consent', function () {
        // Create revoked consent
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'revoked',
            'granted_at' => now()->subMonth(),
            'revoked_at' => now()->subDay(),
            'access_level' => 'full',
            'data_types' => ['vital_signs'],
        ]);

        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->getJson("/api/medical/patients/{$this->patient->id}/vital-signs");

        $response->assertForbidden();
    });

    it('displays patient list with consent information', function () {
        // Create multiple patients with different consent statuses
        $activePatient = User::factory()->create(['name' => 'Active Patient']);
        $pendingPatient = User::factory()->create(['name' => 'Pending Patient']);

        PatientProviderConsent::factory()->create([
            'patient_id' => $activePatient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
        ]);

        PatientProviderConsent::factory()->create([
            'patient_id' => $pendingPatient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'pending',
        ]);

        // Access patients list page
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->get('/medical/patients');

        $response->assertSuccessful();
        $response->assertInertia(function ($page) {
            $page->component('Medical/Patients')
                ->has('patients')
                ->has('pagination');
        });

        // Test API endpoint
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->getJson('/api/medical/patients');

        $response->assertSuccessful();
        $data = $response->json();

        expect($data)->toHaveKey('data');
        expect(count($data['data']))->toBeGreaterThanOrEqual(2);
    });

    it('allows medical professional to flag abnormal readings', function () {
        // Create consent
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'data_types' => ['vital_signs'],
        ]);

        $vitalSignsRecord = VitalSignsRecord::factory()->create([
            'user_id' => $this->patient->id,
            'vital_sign_type_id' => $this->vitalSignType->id,
            'is_flagged' => false,
        ]);

        // Medical professional flags the reading
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->postJson("/api/medical/vital-signs/{$vitalSignsRecord->id}/flag", [
                'reason' => 'Abnormally high reading requiring follow-up',
            ]);

        $response->assertSuccessful();

        $vitalSignsRecord->refresh();
        expect($vitalSignsRecord->is_flagged)->toBeTrue();

        // Verify action was logged
        $this->assertDatabaseHas('data_access_logs', [
            'medical_professional_id' => $this->medicalProfessional->id,
            'patient_id' => $this->patient->id,
            'access_type' => 'vital_signs_flagged',
        ]);
    });

    it('allows medical professional to add clinical notes', function () {
        // Create consent
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'data_types' => ['vital_signs'],
        ]);

        $vitalSignsRecord = VitalSignsRecord::factory()->create([
            'user_id' => $this->patient->id,
            'vital_sign_type_id' => $this->vitalSignType->id,
            'notes' => 'Patient self-reported',
        ]);

        // Add clinical note
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->postJson("/api/medical/vital-signs/{$vitalSignsRecord->id}/notes", [
                'clinical_notes' => 'Reading appears consistent with patient\'s medication adjustment. Continue monitoring.',
            ]);

        $response->assertSuccessful();

        $vitalSignsRecord->refresh();
        expect($vitalSignsRecord->clinical_notes)->toBe('Reading appears consistent with patient\'s medication adjustment. Continue monitoring.');
    });

    it('tracks all medical professional access with audit logs', function () {
        // Create consent
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'data_types' => ['vital_signs', 'health_trends'],
        ]);

        // Perform multiple access operations
        $this->actingAs($this->medicalProfessional, 'medical')
            ->getJson("/api/medical/patients/{$this->patient->id}/vital-signs");

        $this->actingAs($this->medicalProfessional, 'medical')
            ->getJson("/api/medical/patients/{$this->patient->id}/trends");

        // Check audit logs
        $logs = DataAccessLog::where('medical_professional_id', $this->medicalProfessional->id)
            ->where('patient_id', $this->patient->id)
            ->get();

        expect($logs->count())->toBeGreaterThanOrEqual(2);

        // Verify log details
        $vitalSignsLog = $logs->firstWhere('access_type', 'patient_data_access');
        expect($vitalSignsLog)->not->toBeNull();
        expect($vitalSignsLog->ip_address)->not->toBeNull();
        expect($vitalSignsLog->user_agent)->not->toBeNull();
    });

    it('supports emergency access for verified medical professionals', function () {
        // No consent exists, but emergency access is needed
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->postJson("/api/medical/emergency-access/{$this->patient->id}", [
                'emergency_reason' => 'Patient unconscious in ER, need vital history',
                'hospital_case_number' => 'ER-2024-001234',
            ]);

        $response->assertSuccessful();

        // Can now access vital signs under emergency provision
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->getJson("/api/medical/patients/{$this->patient->id}/vital-signs?emergency=true");

        $response->assertSuccessful();

        // Verify emergency access was logged with special flags
        $this->assertDatabaseHas('data_access_logs', [
            'medical_professional_id' => $this->medicalProfessional->id,
            'patient_id' => $this->patient->id,
            'access_type' => 'emergency',
        ]);
    });

    it('prevents emergency access for unverified medical professionals', function () {
        // Create unverified medical professional
        $unverifiedMedicalProfessional = MedicalProfessional::factory()->create([
            'verification_status' => 'pending',
            'verified_at' => null,
        ]);

        $response = $this->actingAs($unverifiedMedicalProfessional, 'medical')
            ->postJson("/api/medical/emergency-access/{$this->patient->id}", [
                'emergency_reason' => 'Patient emergency',
            ]);

        $response->assertForbidden();
    });

    it('allows medical professional to view consent history and access logs', function () {
        // Create consent
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
        ]);

        // Create some access logs
        DataAccessLog::factory()->count(3)->create([
            'medical_professional_id' => $this->medicalProfessional->id,
            'patient_id' => $this->patient->id,
        ]);

        // View consent details with access logs
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->getJson("/api/medical/consents/{$consent->id}");

        $response->assertSuccessful();
        $data = $response->json();

        expect($data)->toHaveKey('consent');
        expect($data)->toHaveKey('access_logs');
        expect(count($data['access_logs']))->toBeGreaterThanOrEqual(3);
    });

    it('enforces role-based access control', function () {
        // Regular user should not access medical endpoints
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)
            ->getJson('/api/medical/patients');

        $response->assertForbidden();

        // Medical professional without proper authentication
        $response = $this->getJson('/api/medical/patients');

        $response->assertUnauthorized();
    });

    it('handles medical professional account verification flow', function () {
        // Create unverified medical professional
        $unverifiedProfessional = MedicalProfessional::factory()->create([
            'verification_status' => 'pending',
            'verified_at' => null,
        ]);

        // Should not be able to access patient data
        $response = $this->actingAs($unverifiedProfessional, 'medical')
            ->getJson('/api/medical/patients');

        $response->assertForbidden();

        // After verification (this would be done by admin)
        $unverifiedProfessional->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        // Now can access
        $response = $this->actingAs($unverifiedProfessional, 'medical')
            ->getJson('/api/medical/patients');

        $response->assertSuccessful();
    });

    it('supports bulk data export for authorized medical professionals', function () {
        // Create consent
        $consent = PatientProviderConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'medical_professional_id' => $this->medicalProfessional->id,
            'status' => 'active',
            'data_types' => ['vital_signs', 'health_trends'],
            'purposes' => ['treatment', 'research'],
        ]);

        // Create multiple vital signs for export
        VitalSignsRecord::factory()->count(10)->create([
            'user_id' => $this->patient->id,
            'vital_sign_type_id' => $this->vitalSignType->id,
        ]);

        // Request data export
        $response = $this->actingAs($this->medicalProfessional, 'medical')
            ->postJson("/api/medical/patients/{$this->patient->id}/export", [
                'data_types' => ['vital_signs'],
                'date_range' => [
                    'start' => now()->subMonth()->toDateString(),
                    'end' => now()->toDateString(),
                ],
                'purpose' => 'treatment',
            ]);

        $response->assertSuccessful();

        // Verify export was logged
        $this->assertDatabaseHas('data_access_logs', [
            'medical_professional_id' => $this->medicalProfessional->id,
            'patient_id' => $this->patient->id,
            'access_type' => 'data_export',
        ]);
    });
});
