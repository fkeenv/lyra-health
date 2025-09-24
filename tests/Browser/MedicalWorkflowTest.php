<?php

use App\Models\User;
use App\Models\MedicalProfessional;
use App\Models\PatientProviderConsent;
use App\Models\VitalSignType;
use App\Models\VitalSignsRecord;
use App\Models\DataAccessLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs};

uses(RefreshDatabase::class);

describe('Medical Professional Workflow Browser Flow', function () {
    beforeEach(function () {
        // Create test patients
        $this->patients = collect([
            User::factory()->create([
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'date_of_birth' => '1980-05-15',
                'gender' => 'female',
            ]),
            User::factory()->create([
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'date_of_birth' => '1975-09-22',
                'gender' => 'male',
            ]),
            User::factory()->create([
                'name' => 'Carol Williams',
                'email' => 'carol@example.com',
                'date_of_birth' => '1990-12-03',
                'gender' => 'female',
            ]),
        ]);

        // Create medical professional
        $this->medicalProfessional = MedicalProfessional::factory()->create([
            'name' => 'Dr. Emily Rodriguez',
            'email' => 'dr.rodriguez@medicenter.com',
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

        // Create consent relationships and vital signs data
        $this->setupPatientData();
    });

    private function setupPatientData(): void
    {
        foreach ($this->patients as $index => $patient) {
            // Create consent with different statuses
            $status = match($index) {
                0 => 'active',
                1 => 'pending',
                2 => 'revoked',
            };

            PatientProviderConsent::factory()->create([
                'patient_id' => $patient->id,
                'medical_professional_id' => $this->medicalProfessional->id,
                'status' => $status,
                'granted_at' => $status === 'active' ? now()->subMonth() : null,
                'access_level' => 'full',
                'data_types' => ['vital_signs', 'health_trends'],
            ]);

            // Create vital signs for active patients only
            if ($status === 'active') {
                VitalSignsRecord::factory()->count(5)->create([
                    'user_id' => $patient->id,
                    'vital_sign_type_id' => $this->vitalSignType->id,
                    'measured_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }
    }

    it('allows medical professional to login and view dashboard', function () {
        visit('/medical/login')
            ->assertNoJavascriptErrors()
            ->assertSee('Medical Professional Login')
            ->assertSee('Email')
            ->assertSee('Password')
            ->fill('email', $this->medicalProfessional->email)
            ->fill('password', 'password') // Default from factory
            ->click('Sign In')
            ->assertSee('Medical Dashboard')
            ->assertSee('Dr. Emily Rodriguez')
            ->assertSee('Cardiology')
            ->assertSee('Active Patients')
            ->assertSee('Pending Consents')
            ->assertUrlContains('/medical/dashboard');
    });

    it('displays patient list with consent status indicators', function () {
        visit('/medical/patients')
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->assertSee('Patient Management')
            ->assertSee('Alice Johnson') // Active consent
            ->assertSee('Bob Smith') // Pending consent
            ->assertSee('Carol Williams') // Revoked consent
            ->assertElementExists('[data-status="active"]')
            ->assertElementExists('[data-status="pending"]')
            ->assertElementExists('[data-status="revoked"]')
            ->assertSee('Active')
            ->assertSee('Pending')
            ->assertSee('Revoked');
    });

    it('allows access to patient with active consent', function () {
        $activePatient = $this->patients->first(); // Alice Johnson

        visit('/medical/patients')
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->click("View Patient") // Click on Alice's view button
            ->assertSee('Patient Overview')
            ->assertSee('Alice Johnson')
            ->assertSee('Female, 44 years old')
            ->assertSee('Vital Signs History')
            ->assertSee('Blood Pressure')
            ->assertElementExists('[data-testid="vital-signs-chart"]')
            ->assertSee('5 readings')
            ->assertElementExists('[data-testid="flag-reading-button"]')
            ->assertElementExists('[data-testid="add-notes-button"]');

        // Verify access was logged
        expect(DataAccessLog::where('medical_professional_id', $this->medicalProfessional->id)
            ->where('patient_id', $activePatient->id)
            ->where('access_type', 'patient_data_access')
            ->exists())->toBeTrue();
    });

    it('prevents access to patient without active consent', function () {
        visit('/medical/patients')
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->click('[data-patient="Bob Smith"] .view-patient') // Pending consent patient
            ->assertSee('Access Restricted')
            ->assertSee('This patient has not granted you access')
            ->assertSee('Consent Status: Pending')
            ->assertSee('Request Access')
            ->assertDontSee('Vital Signs History');
    });

    it('allows medical professional to flag abnormal readings', function () {
        $activePatient = $this->patients->first();
        $vitalSignsRecord = VitalSignsRecord::where('user_id', $activePatient->id)->first();

        visit("/medical/patients/{$activePatient->id}")
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->assertSee('Vital Signs History')
            ->click("[data-record-id='{$vitalSignsRecord->id}'] .flag-button")
            ->assertSee('Flag Reading')
            ->fill('flag_reason', 'Reading appears abnormally high for patient age group')
            ->select('severity', 'medium')
            ->click('Flag Reading')
            ->assertSee('Reading flagged successfully')
            ->assertElementExists("[data-record-id='{$vitalSignsRecord->id}'] .flagged-indicator");

        // Verify the flag was applied
        $vitalSignsRecord->refresh();
        expect($vitalSignsRecord->is_flagged)->toBeTrue();
    });

    it('allows medical professional to add clinical notes', function () {
        $activePatient = $this->patients->first();
        $vitalSignsRecord = VitalSignsRecord::where('user_id', $activePatient->id)->first();

        visit("/medical/patients/{$activePatient->id}")
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->click("[data-record-id='{$vitalSignsRecord->id}'] .add-notes-button")
            ->assertSee('Add Clinical Notes')
            ->fill('clinical_notes', 'Patient reports feeling dizzy during this measurement. Recommend follow-up.')
            ->click('Save Notes')
            ->assertSee('Clinical notes added successfully')
            ->assertSee('Patient reports feeling dizzy');

        // Verify the notes were saved
        $vitalSignsRecord->refresh();
        expect($vitalSignsRecord->clinical_notes)->toBe('Patient reports feeling dizzy during this measurement. Recommend follow-up.');
    });

    it('displays consent details and access history', function () {
        $activePatient = $this->patients->first();
        $consent = PatientProviderConsent::where('patient_id', $activePatient->id)->first();

        // Create some access logs
        DataAccessLog::factory()->count(3)->create([
            'medical_professional_id' => $this->medicalProfessional->id,
            'patient_id' => $activePatient->id,
            'access_type' => 'patient_data_access',
            'accessed_at' => now()->subDays(rand(1, 7)),
        ]);

        visit("/medical/consents/{$consent->id}")
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->assertSee('Consent Details')
            ->assertSee('Alice Johnson')
            ->assertSee('Status: Active')
            ->assertSee('Access Level: Full')
            ->assertSee('Data Types: vital_signs, health_trends')
            ->assertSee('Access History')
            ->assertSee('3 access events')
            ->assertElementExists('[data-testid="access-log-entry"]')
            ->assertSee('patient_data_access');
    });

    it('supports emergency access override', function () {
        // Test with a patient who has no consent
        $emergencyPatient = User::factory()->create([
            'name' => 'Emergency Patient',
            'email' => 'emergency@example.com',
        ]);

        // Create some vital signs for emergency access
        VitalSignsRecord::factory()->count(2)->create([
            'user_id' => $emergencyPatient->id,
            'vital_sign_type_id' => $this->vitalSignType->id,
        ]);

        visit('/medical/emergency-access')
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->assertSee('Emergency Access')
            ->fill('patient_identifier', $emergencyPatient->email)
            ->fill('emergency_reason', 'Patient unconscious in ER, need immediate vital history')
            ->fill('hospital_case_number', 'ER-2024-001234')
            ->click('Request Emergency Access')
            ->assertSee('Emergency access granted')
            ->assertSee($emergencyPatient->name)
            ->assertSee('Emergency Access Active')
            ->assertSee('Vital Signs History')
            ->assertElementExists('[data-testid="emergency-banner"]');

        // Verify emergency access was logged
        expect(DataAccessLog::where('medical_professional_id', $this->medicalProfessional->id)
            ->where('patient_id', $emergencyPatient->id)
            ->where('access_type', 'emergency')
            ->exists())->toBeTrue();
    });

    it('allows bulk data export for authorized patients', function () {
        $activePatient = $this->patients->first();

        visit("/medical/patients/{$activePatient->id}")
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->click('Export Data')
            ->assertSee('Export Patient Data')
            ->check('vital_signs')
            ->select('date_range', '30_days')
            ->select('format', 'csv')
            ->fill('purpose', 'Treatment planning and analysis')
            ->click('Generate Export')
            ->waitFor('[data-testid="export-ready"]')
            ->assertSee('Export completed successfully')
            ->click('Download Export')
            ->assertSee('Download started');

        // Verify export was logged
        expect(DataAccessLog::where('medical_professional_id', $this->medicalProfessional->id)
            ->where('patient_id', $activePatient->id)
            ->where('access_type', 'data_export')
            ->exists())->toBeTrue();
    });

    it('handles patient search and filtering', function () {
        visit('/medical/patients')
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->fill('search', 'Alice')
            ->waitFor('[data-testid="search-results"]')
            ->assertSee('Alice Johnson')
            ->assertDontSee('Bob Smith')
            ->assertDontSee('Carol Williams')
            ->clear('search')
            ->select('consent_status', 'active')
            ->waitFor('[data-testid="filtered-results"]')
            ->assertSee('Alice Johnson')
            ->assertDontSee('Bob Smith'); // Pending consent should be filtered out
    });

    it('displays patient timeline and activity feed', function () {
        $activePatient = $this->patients->first();

        // Create some recent activity
        VitalSignsRecord::factory()->create([
            'user_id' => $activePatient->id,
            'vital_sign_type_id' => $this->vitalSignType->id,
            'is_flagged' => true,
            'measured_at' => now()->subHours(2),
        ]);

        visit("/medical/patients/{$activePatient->id}")
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->click('Timeline')
            ->assertSee('Patient Timeline')
            ->assertSee('Recent Activity')
            ->assertSee('2 hours ago')
            ->assertSee('New vital sign recorded')
            ->assertSee('Flagged reading')
            ->assertElementExists('[data-testid="timeline-entry"]')
            ->assertElementExists('[data-testid="flagged-activity"]');
    });

    it('supports secure messaging with patients', function () {
        $activePatient = $this->patients->first();

        visit("/medical/patients/{$activePatient->id}")
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->click('Send Message')
            ->assertSee('Send Message to Alice Johnson')
            ->fill('subject', 'Follow-up on recent blood pressure readings')
            ->fill('message', 'I noticed your recent readings have been elevated. Please schedule a follow-up appointment.')
            ->select('priority', 'normal')
            ->click('Send Message')
            ->assertSee('Message sent successfully')
            ->assertSee('Message will appear in patient portal');
    });

    it('handles mobile responsive design for medical interface', function () {
        visit('/medical/patients')
            ->actingAs($this->medicalProfessional, 'medical')
            ->resize(375, 812) // iPhone X
            ->assertNoJavascriptErrors()
            ->assertSee('Patients') // Mobile header
            ->assertElementExists('[data-testid="mobile-nav"]')
            ->click('[data-testid="hamburger-menu"]')
            ->assertSee('Dashboard')
            ->assertSee('Emergency Access')
            ->assertSee('Profile');

        // Test patient view on mobile
        visit("/medical/patients/{$this->patients->first()->id}")
            ->actingAs($this->medicalProfessional, 'medical')
            ->resize(375, 812)
            ->assertNoJavascriptErrors()
            ->assertElementExists('[data-testid="mobile-patient-view"]')
            ->swipeLeft()
            ->assertSee('Vital Signs')
            ->swipeLeft()
            ->assertSee('Timeline');
    });

    it('enforces verification status requirements', function () {
        // Create unverified medical professional
        $unverifiedProfessional = MedicalProfessional::factory()->create([
            'verification_status' => 'pending',
            'verified_at' => null,
        ]);

        visit('/medical/patients')
            ->actingAs($unverifiedProfessional, 'medical')
            ->assertNoJavascriptErrors()
            ->assertSee('Account Verification Required')
            ->assertSee('Your account is pending verification')
            ->assertSee('Contact Administrator')
            ->assertDontSee('Patient Management');
    });

    it('tracks comprehensive audit trail', function () {
        $activePatient = $this->patients->first();

        // Perform multiple actions
        visit("/medical/patients/{$activePatient->id}")
            ->actingAs($this->medicalProfessional, 'medical')
            ->assertNoJavascriptErrors();

        // View patient data
        $this->page->waitFor('[data-testid="vital-signs-chart"]');

        // Flag a reading
        $vitalSignsRecord = VitalSignsRecord::where('user_id', $activePatient->id)->first();
        $this->page->click("[data-record-id='{$vitalSignsRecord->id}'] .flag-button")
            ->fill('flag_reason', 'Audit trail test')
            ->click('Flag Reading');

        // Export data
        $this->page->click('Export Data')
            ->check('vital_signs')
            ->click('Generate Export');

        // Verify comprehensive audit trail
        $logs = DataAccessLog::where('medical_professional_id', $this->medicalProfessional->id)
            ->where('patient_id', $activePatient->id)
            ->get();

        expect($logs->where('access_type', 'patient_data_access')->count())->toBeGreaterThan(0);
        expect($logs->where('access_type', 'vital_signs_flagged')->count())->toBe(1);
        expect($logs->where('access_type', 'data_export')->count())->toBe(1);

        // Each log should have required metadata
        foreach ($logs as $log) {
            expect($log->ip_address)->not->toBeNull();
            expect($log->user_agent)->not->toBeNull();
            expect($log->accessed_at)->not->toBeNull();
        }
    });
});