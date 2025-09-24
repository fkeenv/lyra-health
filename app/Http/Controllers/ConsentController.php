<?php

namespace App\Http\Controllers;

use App\Http\Requests\GrantConsentRequest;
use App\Models\PatientProviderConsent;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConsentController extends Controller
{
    public function __construct(
        protected ConsentService $consentService
    ) {}

    /**
     * Get consent records for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:active,revoked',
            'medical_professional_id' => 'nullable|integer|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = Auth::user();

        $consents = $this->consentService->getUserConsents(
            user: $user,
            status: $request->string('status'),
            medicalProfessionalId: $request->integer('medical_professional_id'),
            perPage: $request->integer('per_page', 15)
        );

        return response()->json([
            'data' => $consents->items(),
            'meta' => [
                'current_page' => $consents->currentPage(),
                'last_page' => $consents->lastPage(),
                'per_page' => $consents->perPage(),
                'total' => $consents->total(),
                'filters' => [
                    'status' => $request->string('status'),
                    'medical_professional_id' => $request->integer('medical_professional_id'),
                ],
            ],
            'links' => [
                'first' => $consents->url(1),
                'last' => $consents->url($consents->lastPage()),
                'prev' => $consents->previousPageUrl(),
                'next' => $consents->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Grant consent to a medical professional.
     */
    public function store(GrantConsentRequest $request): JsonResponse
    {
        $user = Auth::user();

        $consent = $this->consentService->grantConsent(
            patient: $user,
            medicalProfessionalId: $request->validated('medical_professional_id'),
            accessLevel: $request->validated('access_level'),
            expiresAt: $request->validated('expires_at'),
            purpose: $request->validated('purpose'),
            conditions: $request->validated('conditions')
        );

        return response()->json([
            'message' => 'Consent granted successfully.',
            'data' => $consent,
        ], 201);
    }

    /**
     * Get a specific consent record for the authenticated user.
     */
    public function show(PatientProviderConsent $consent): JsonResponse
    {
        // Ensure the consent belongs to the authenticated user
        if ($consent->patient_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to consent record.',
            ], 403);
        }

        $consent->load(['patient', 'medicalProfessional', 'dataAccessLogs']);

        return response()->json([
            'data' => $consent,
        ]);
    }

    /**
     * Revoke consent for a medical professional.
     */
    public function revoke(PatientProviderConsent $consent): JsonResponse
    {
        // Ensure the consent belongs to the authenticated user
        if ($consent->patient_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to consent record.',
            ], 403);
        }

        // Check if consent is already revoked
        if ($consent->status === 'revoked') {
            return response()->json([
                'message' => 'Consent is already revoked.',
            ], 422);
        }

        $revokedConsent = $this->consentService->revokeConsent($consent);

        return response()->json([
            'message' => 'Consent revoked successfully.',
            'data' => $revokedConsent,
        ]);
    }
}
