<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Routes (auth middleware removed for testing)
Route::group([], function () {
    // Dashboard
    Route::get('/dashboard', function () {
        // For now, we'll use hardcoded test data
        // In a real app, this would fetch from the VitalSignsService
        $user = \App\Models\User::first(); // Get first user for demo

        if (! $user) {
            // Create a test user if none exists
            $user = \App\Models\User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // Get actual data
        $recentVitalSigns = $user->vitalSignsRecords()
            ->with('vitalSignType')
            ->latest('measured_at')
            ->limit(5)
            ->get();

        $totalRecords = $user->vitalSignsRecords()->count();
        $flaggedRecords = $user->vitalSignsRecords()->where('is_flagged', true)->count();

        $recommendations = $user->recommendations()
            ->where('is_active', true)
            ->latest()
            ->limit(3)
            ->get();

        $summary = [
            'total_records' => $totalRecords,
            'flagged_records' => $flaggedRecords,
        ];

        return Inertia::render('Dashboard', [
            'summary' => $summary,
            'recentVitalSigns' => $recentVitalSigns,
            'flaggedRecords' => [],
            'recommendations' => $recommendations,
        ]);
    })->name('dashboard');

    // Vital Signs
    Route::get('/vital-signs/create', function () {
        $vitalSignTypes = \App\Models\VitalSignType::where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name', 'unit_primary', 'unit_secondary', 'has_secondary_value', 'input_type', 'normal_range_min', 'normal_range_max']);

        return Inertia::render('VitalSigns/Create', [
            'vitalSignTypes' => $vitalSignTypes,
        ]);
    })->name('vital-signs.create');

    Route::get('/vital-signs', function () {
        $vitalSignTypes = \App\Models\VitalSignType::where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name', 'unit_primary', 'unit_secondary', 'has_secondary_value', 'input_type']);

        return Inertia::render('VitalSigns/Index', [
            'vitalSignTypes' => $vitalSignTypes,
        ]);
    })->name('vital-signs.index');

    Route::get('/vital-signs/trends', function () {
        $vitalSignTypes = \App\Models\VitalSignType::where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name', 'unit_primary', 'unit_secondary', 'has_secondary_value', 'input_type', 'normal_range_min', 'normal_range_max', 'warning_range_min', 'warning_range_max']);

        return Inertia::render('VitalSigns/Trends', [
            'vitalSignTypes' => $vitalSignTypes,
        ]);
    })->name('vital-signs.trends');

    // Recommendations
    Route::get('/recommendations', function () {
        return Inertia::render('Recommendations/Index');
    })->name('recommendations.index');

    // Medical Professional Routes
    Route::prefix('medical')->name('medical.')->group(function () {
        Route::get('/patients', function () {
            // For testing, we'll create some sample data
            // In production, this would use the MedicalController with proper auth
            $patients = collect([
                [
                    'id' => 1,
                    'name' => 'John Doe',
                    'email' => 'john.doe@example.com',
                    'phone' => '+1-555-0101',
                    'date_of_birth' => '1985-03-15',
                    'gender' => 'male',
                    'last_reading_at' => '2024-09-23T10:30:00Z',
                    'total_readings' => 45,
                    'flagged_readings' => 3,
                    'consent_status' => 'active',
                    'consent_granted_at' => '2024-01-15T09:00:00Z',
                    'medical_professional_id' => 1,
                    'status' => 'active',
                ],
                [
                    'id' => 2,
                    'name' => 'Jane Smith',
                    'email' => 'jane.smith@example.com',
                    'phone' => '+1-555-0102',
                    'date_of_birth' => '1978-07-22',
                    'gender' => 'female',
                    'last_reading_at' => '2024-09-24T08:15:00Z',
                    'total_readings' => 62,
                    'flagged_readings' => 8,
                    'consent_status' => 'active',
                    'consent_granted_at' => '2024-02-01T14:30:00Z',
                    'medical_professional_id' => 1,
                    'status' => 'active',
                ],
                [
                    'id' => 3,
                    'name' => 'Michael Johnson',
                    'email' => 'michael.j@example.com',
                    'phone' => '+1-555-0103',
                    'date_of_birth' => '1990-11-08',
                    'gender' => 'male',
                    'last_reading_at' => '2024-09-20T16:45:00Z',
                    'total_readings' => 28,
                    'flagged_readings' => 1,
                    'consent_status' => 'pending',
                    'consent_granted_at' => null,
                    'medical_professional_id' => null,
                    'status' => 'pending_consent',
                ],
            ]);

            return Inertia::render('Medical/Patients', [
                'patients' => $patients,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => $patients->count(),
                ],
                'filters' => [
                    'search' => request('search', ''),
                    'status' => request('status', 'all'),
                ],
            ]);
        })->name('patients');

        Route::get('/patients/{id}/vital-signs', function ($id) {
            // For testing, return sample vital signs data for a patient
            $patient = [
                'id' => $id,
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
            ];

            $vitalSigns = collect([
                [
                    'id' => 1,
                    'vital_sign_type_id' => 1,
                    'value_primary' => '120',
                    'value_secondary' => '80',
                    'measured_at' => '2024-09-24T08:30:00Z',
                    'is_flagged' => false,
                    'notes' => null,
                    'vital_sign_type' => [
                        'id' => 1,
                        'name' => 'blood_pressure',
                        'display_name' => 'Blood Pressure',
                        'unit_primary' => 'mmHg',
                        'unit_secondary' => 'mmHg',
                    ],
                ],
                [
                    'id' => 2,
                    'vital_sign_type_id' => 2,
                    'value_primary' => '98.6',
                    'value_secondary' => null,
                    'measured_at' => '2024-09-24T08:30:00Z',
                    'is_flagged' => false,
                    'notes' => 'Normal temperature',
                    'vital_sign_type' => [
                        'id' => 2,
                        'name' => 'body_temperature',
                        'display_name' => 'Body Temperature',
                        'unit_primary' => '°F',
                        'unit_secondary' => null,
                    ],
                ],
                [
                    'id' => 3,
                    'vital_sign_type_id' => 3,
                    'value_primary' => '72',
                    'value_secondary' => null,
                    'measured_at' => '2024-09-24T08:30:00Z',
                    'is_flagged' => false,
                    'notes' => null,
                    'vital_sign_type' => [
                        'id' => 3,
                        'name' => 'heart_rate',
                        'display_name' => 'Heart Rate',
                        'unit_primary' => 'bpm',
                        'unit_secondary' => null,
                    ],
                ],
            ]);

            return Inertia::render('Medical/PatientVitalSigns', [
                'patient' => $patient,
                'vitalSigns' => $vitalSigns,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 50,
                    'total' => $vitalSigns->count(),
                ],
                'filters' => [
                    'type_id' => request('type_id', 'all'),
                    'flagged_only' => request('flagged_only', false),
                    'period' => request('period', 30),
                ],
            ]);
        })->name('patients.vital-signs');
    });

    // Consent Management Routes
    Route::prefix('consent')->name('consent.')->group(function () {
        Route::get('/', function () {
            // For testing, provide sample consent data
            // In production, this would use the ConsentController with proper auth
            $consents = collect([
                [
                    'id' => 1,
                    'medical_professional_id' => 1,
                    'patient_id' => 1,
                    'status' => 'active',
                    'granted_at' => '2024-01-15T09:00:00Z',
                    'expires_at' => '2025-01-15T09:00:00Z',
                    'revoked_at' => null,
                    'access_level' => 'full',
                    'purposes' => ['monitoring', 'treatment'],
                    'data_types' => ['vital_signs', 'health_trends'],
                    'medical_professional' => [
                        'id' => 1,
                        'name' => 'Dr. Sarah Wilson',
                        'email' => 'dr.wilson@medicenter.com',
                        'phone' => '+1-555-0201',
                        'specialty' => 'Cardiology',
                        'license_number' => 'MD12345',
                        'facility_name' => 'City Medical Center',
                        'facility_address' => '123 Healthcare Ave, Medical City, MC 12345',
                        'verification_status' => 'verified',
                        'verified_at' => '2024-01-01T00:00:00Z',
                    ],
                    'patient' => [
                        'id' => 1,
                        'name' => 'John Doe',
                        'email' => 'john.doe@example.com',
                    ],
                    'access_logs_count' => 15,
                    'last_access_at' => '2024-09-23T14:30:00Z',
                    'created_at' => '2024-01-15T09:00:00Z',
                    'updated_at' => '2024-01-15T09:00:00Z',
                ],
                [
                    'id' => 2,
                    'medical_professional_id' => 2,
                    'patient_id' => 1,
                    'status' => 'pending',
                    'granted_at' => null,
                    'expires_at' => null,
                    'revoked_at' => null,
                    'access_level' => 'limited',
                    'purposes' => ['monitoring'],
                    'data_types' => ['vital_signs'],
                    'medical_professional' => [
                        'id' => 2,
                        'name' => 'Dr. Michael Rodriguez',
                        'email' => 'dr.rodriguez@healthplus.com',
                        'phone' => '+1-555-0202',
                        'specialty' => 'Internal Medicine',
                        'license_number' => 'MD67890',
                        'facility_name' => 'HealthPlus Clinic',
                        'facility_address' => '456 Wellness Blvd, Health Town, HT 67890',
                        'verification_status' => 'verified',
                        'verified_at' => '2024-02-01T00:00:00Z',
                    ],
                    'patient' => [
                        'id' => 1,
                        'name' => 'John Doe',
                        'email' => 'john.doe@example.com',
                    ],
                    'access_logs_count' => 0,
                    'last_access_at' => null,
                    'created_at' => '2024-09-20T10:15:00Z',
                    'updated_at' => '2024-09-20T10:15:00Z',
                ],
                [
                    'id' => 3,
                    'medical_professional_id' => 1,
                    'patient_id' => 2,
                    'status' => 'revoked',
                    'granted_at' => '2024-02-01T14:30:00Z',
                    'expires_at' => '2025-02-01T14:30:00Z',
                    'revoked_at' => '2024-09-15T11:00:00Z',
                    'access_level' => 'full',
                    'purposes' => ['monitoring', 'treatment', 'research'],
                    'data_types' => ['vital_signs', 'health_trends', 'recommendations'],
                    'medical_professional' => [
                        'id' => 1,
                        'name' => 'Dr. Sarah Wilson',
                        'email' => 'dr.wilson@medicenter.com',
                        'phone' => '+1-555-0201',
                        'specialty' => 'Cardiology',
                        'license_number' => 'MD12345',
                        'facility_name' => 'City Medical Center',
                        'facility_address' => '123 Healthcare Ave, Medical City, MC 12345',
                        'verification_status' => 'verified',
                        'verified_at' => '2024-01-01T00:00:00Z',
                    ],
                    'patient' => [
                        'id' => 2,
                        'name' => 'Jane Smith',
                        'email' => 'jane.smith@example.com',
                    ],
                    'access_logs_count' => 28,
                    'last_access_at' => '2024-09-14T16:45:00Z',
                    'revoked_reason' => 'Patient requested revocation',
                    'created_at' => '2024-02-01T14:30:00Z',
                    'updated_at' => '2024-09-15T11:00:00Z',
                ],
            ]);

            // Filter by status if requested
            if (request('status') && request('status') !== 'all') {
                $consents = $consents->where('status', request('status'));
            }

            // Filter by search term if provided
            if (request('search')) {
                $searchTerm = strtolower(request('search'));
                $consents = $consents->filter(function ($consent) use ($searchTerm) {
                    return str_contains(strtolower($consent['medical_professional']['name']), $searchTerm) ||
                           str_contains(strtolower($consent['medical_professional']['facility_name']), $searchTerm) ||
                           str_contains(strtolower($consent['patient']['name']), $searchTerm);
                });
            }

            return Inertia::render('Consent/Index', [
                'consents' => $consents->values(),
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => $consents->count(),
                ],
                'filters' => [
                    'search' => request('search', ''),
                    'status' => request('status', 'all'),
                ],
                'stats' => [
                    'total' => $consents->count(),
                    'active' => $consents->where('status', 'active')->count(),
                    'pending' => $consents->where('status', 'pending')->count(),
                    'revoked' => $consents->where('status', 'revoked')->count(),
                    'expired' => 0, // Would be calculated from expires_at in real implementation
                ],
            ]);
        })->name('index');

        Route::get('/create', function () {
            // For testing, provide sample medical professionals for consent granting
            $medicalProfessionals = collect([
                [
                    'id' => 1,
                    'name' => 'Dr. Sarah Wilson',
                    'email' => 'dr.wilson@medicenter.com',
                    'phone' => '+1-555-0201',
                    'specialty' => 'Cardiology',
                    'license_number' => 'MD12345',
                    'facility_name' => 'City Medical Center',
                    'facility_address' => '123 Healthcare Ave, Medical City, MC 12345',
                    'verification_status' => 'verified',
                ],
                [
                    'id' => 2,
                    'name' => 'Dr. Michael Rodriguez',
                    'email' => 'dr.rodriguez@healthplus.com',
                    'phone' => '+1-555-0202',
                    'specialty' => 'Internal Medicine',
                    'license_number' => 'MD67890',
                    'facility_name' => 'HealthPlus Clinic',
                    'facility_address' => '456 Wellness Blvd, Health Town, HT 67890',
                    'verification_status' => 'verified',
                ],
                [
                    'id' => 3,
                    'name' => 'Dr. Emily Chen',
                    'email' => 'dr.chen@familycare.com',
                    'phone' => '+1-555-0203',
                    'specialty' => 'Family Medicine',
                    'license_number' => 'MD11111',
                    'facility_name' => 'Family Care Clinic',
                    'facility_address' => '789 Community St, Hometown, HT 11111',
                    'verification_status' => 'verified',
                ],
            ]);

            return Inertia::render('Consent/Create', [
                'medicalProfessionals' => $medicalProfessionals,
                'accessLevels' => [
                    ['value' => 'limited', 'label' => 'Limited Access', 'description' => 'Basic vital signs viewing only'],
                    ['value' => 'full', 'label' => 'Full Access', 'description' => 'Complete health data and trends'],
                    ['value' => 'research', 'label' => 'Research Access', 'description' => 'Anonymous data for medical research'],
                ],
                'purposes' => [
                    ['value' => 'monitoring', 'label' => 'Health Monitoring'],
                    ['value' => 'treatment', 'label' => 'Medical Treatment'],
                    ['value' => 'consultation', 'label' => 'Medical Consultation'],
                    ['value' => 'research', 'label' => 'Medical Research'],
                    ['value' => 'emergency', 'label' => 'Emergency Care'],
                ],
                'dataTypes' => [
                    ['value' => 'vital_signs', 'label' => 'Vital Signs'],
                    ['value' => 'health_trends', 'label' => 'Health Trends'],
                    ['value' => 'recommendations', 'label' => 'Health Recommendations'],
                    ['value' => 'medical_notes', 'label' => 'Medical Notes'],
                    ['value' => 'emergency_contacts', 'label' => 'Emergency Contacts'],
                ],
            ]);
        })->name('create');

        Route::get('/{id}', function ($id) {
            // For testing, return detailed consent information
            $consent = [
                'id' => $id,
                'medical_professional_id' => 1,
                'patient_id' => 1,
                'status' => 'active',
                'granted_at' => '2024-01-15T09:00:00Z',
                'expires_at' => '2025-01-15T09:00:00Z',
                'revoked_at' => null,
                'access_level' => 'full',
                'purposes' => ['monitoring', 'treatment'],
                'data_types' => ['vital_signs', 'health_trends'],
                'medical_professional' => [
                    'id' => 1,
                    'name' => 'Dr. Sarah Wilson',
                    'email' => 'dr.wilson@medicenter.com',
                    'phone' => '+1-555-0201',
                    'specialty' => 'Cardiology',
                    'license_number' => 'MD12345',
                    'facility_name' => 'City Medical Center',
                    'facility_address' => '123 Healthcare Ave, Medical City, MC 12345',
                    'verification_status' => 'verified',
                    'verified_at' => '2024-01-01T00:00:00Z',
                ],
                'patient' => [
                    'id' => 1,
                    'name' => 'John Doe',
                    'email' => 'john.doe@example.com',
                    'date_of_birth' => '1985-03-15',
                    'phone' => '+1-555-0101',
                ],
                'access_logs' => collect([
                    [
                        'id' => 1,
                        'accessed_at' => '2024-09-23T14:30:00Z',
                        'action' => 'viewed_vital_signs',
                        'data_accessed' => ['vital_signs'],
                        'ip_address' => '192.168.1.100',
                        'user_agent' => 'Mozilla/5.0...',
                    ],
                    [
                        'id' => 2,
                        'accessed_at' => '2024-09-22T10:15:00Z',
                        'action' => 'viewed_trends',
                        'data_accessed' => ['health_trends'],
                        'ip_address' => '192.168.1.100',
                        'user_agent' => 'Mozilla/5.0...',
                    ],
                ]),
                'created_at' => '2024-01-15T09:00:00Z',
                'updated_at' => '2024-01-15T09:00:00Z',
            ];

            return Inertia::render('Consent/Show', [
                'consent' => $consent,
                'canRevoke' => true,
                'canModify' => true,
            ]);
        })->name('show');
    });
});
