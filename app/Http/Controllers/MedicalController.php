<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ConsentService;
use App\Services\VitalSignsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MedicalController extends Controller
{
    public function __construct(
        protected ConsentService $consentService,
        protected VitalSignsService $vitalSignsService
    ) {}

    /**
     * Get patients that have granted consent to the authenticated medical professional.
     */
    public function patients(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:active,revoked',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $medicalProfessional = Auth::user();

        // Ensure the authenticated user is a medical professional
        if (! $medicalProfessional->hasRole('medical_professional')) {
            return response()->json([
                'message' => 'Unauthorized. Only medical professionals can access patient data.',
            ], 403);
        }

        $patients = $this->consentService->getAuthorizedPatients(
            medicalProfessional: $medicalProfessional,
            status: $request->string('status', 'active'),
            perPage: $request->integer('per_page', 15)
        );

        // Log access for audit trail
        $this->consentService->logAccess(
            medicalProfessional: $medicalProfessional,
            action: 'patients_list_accessed',
            patientId: null,
            details: [
                'filters' => [
                    'status' => $request->string('status', 'active'),
                ],
                'result_count' => $patients->total(),
            ]
        );

        return response()->json([
            'data' => $patients->items(),
            'meta' => [
                'current_page' => $patients->currentPage(),
                'last_page' => $patients->lastPage(),
                'per_page' => $patients->perPage(),
                'total' => $patients->total(),
                'filters' => [
                    'status' => $request->string('status', 'active'),
                ],
            ],
            'links' => [
                'first' => $patients->url(1),
                'last' => $patients->url($patients->lastPage()),
                'prev' => $patients->previousPageUrl(),
                'next' => $patients->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Get vital signs for a specific patient (with consent validation).
     */
    public function patientVitalSigns(Request $request, User $patient): JsonResponse
    {
        $request->validate([
            'vital_sign_type_id' => 'nullable|integer|exists:vital_sign_types,id',
            'start_date' => 'nullable|date|before_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date|before_or_equal:today',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $medicalProfessional = Auth::user();

        // Ensure the authenticated user is a medical professional
        if (! $medicalProfessional->hasRole('medical_professional')) {
            return response()->json([
                'message' => 'Unauthorized. Only medical professionals can access patient data.',
            ], 403);
        }

        // Validate consent
        $hasConsent = $this->consentService->hasActiveConsent(
            patient: $patient,
            medicalProfessional: $medicalProfessional
        );

        if (! $hasConsent) {
            return response()->json([
                'message' => 'Access denied. Patient has not granted consent or consent has been revoked.',
            ], 403);
        }

        $vitalSigns = $this->vitalSignsService->getUserVitalSigns(
            user: $patient,
            vitalSignTypeId: $request->integer('vital_sign_type_id'),
            startDate: $request->string('start_date'),
            endDate: $request->string('end_date'),
            perPage: $request->integer('per_page', 15)
        );

        // Log access for audit trail
        $this->consentService->logAccess(
            medicalProfessional: $medicalProfessional,
            action: 'patient_vital_signs_accessed',
            patientId: $patient->id,
            details: [
                'vital_sign_type_id' => $request->integer('vital_sign_type_id'),
                'start_date' => $request->string('start_date'),
                'end_date' => $request->string('end_date'),
                'result_count' => $vitalSigns->total(),
            ]
        );

        return response()->json([
            'data' => $vitalSigns->items(),
            'meta' => [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'current_page' => $vitalSigns->currentPage(),
                'last_page' => $vitalSigns->lastPage(),
                'per_page' => $vitalSigns->perPage(),
                'total' => $vitalSigns->total(),
                'filters' => [
                    'vital_sign_type_id' => $request->integer('vital_sign_type_id'),
                    'start_date' => $request->string('start_date'),
                    'end_date' => $request->string('end_date'),
                ],
            ],
            'links' => [
                'first' => $vitalSigns->url(1),
                'last' => $vitalSigns->url($vitalSigns->lastPage()),
                'prev' => $vitalSigns->previousPageUrl(),
                'next' => $vitalSigns->nextPageUrl(),
            ],
        ]);
    }
}
