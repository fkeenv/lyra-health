<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateVitalSignsRequest;
use App\Http\Requests\UpdateVitalSignsRequest;
use App\Models\VitalSignsRecord;
use App\Services\VitalSignsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VitalSignsController extends Controller
{
    public function __construct(
        protected VitalSignsService $vitalSignsService
    ) {}

    /**
     * Display a listing of the user's vital signs.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'vital_sign_type_id' => 'nullable|integer|exists:vital_sign_types,id',
            'start_date' => 'nullable|date|before_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date|before_or_equal:today',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = Auth::user();

        $vitalSigns = $this->vitalSignsService->getUserVitalSigns(
            user: $user,
            vitalSignTypeId: $request->integer('vital_sign_type_id'),
            startDate: $request->string('start_date'),
            endDate: $request->string('end_date'),
            perPage: $request->integer('per_page', 15)
        );

        return response()->json([
            'data' => $vitalSigns->items(),
            'meta' => [
                'current_page' => $vitalSigns->currentPage(),
                'last_page' => $vitalSigns->lastPage(),
                'per_page' => $vitalSigns->perPage(),
                'total' => $vitalSigns->total(),
            ],
            'links' => [
                'first' => $vitalSigns->url(1),
                'last' => $vitalSigns->url($vitalSigns->lastPage()),
                'prev' => $vitalSigns->previousPageUrl(),
                'next' => $vitalSigns->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Store a newly created vital signs record.
     */
    public function store(CreateVitalSignsRequest $request): JsonResponse
    {
        $user = Auth::user();

        $vitalSign = $this->vitalSignsService->create($user, $request->validated());

        return response()->json([
            'message' => 'Vital signs record created successfully.',
            'data' => $vitalSign,
        ], 201);
    }

    /**
     * Display the specified vital signs record.
     */
    public function show(VitalSignsRecord $vitalSign): JsonResponse
    {
        // Ensure the record belongs to the authenticated user
        if ($vitalSign->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to vital signs record.',
            ], 403);
        }

        $vitalSign->load(['vitalSignType', 'user', 'recommendations']);

        return response()->json([
            'data' => $vitalSign,
        ]);
    }

    /**
     * Update the specified vital signs record.
     */
    public function update(UpdateVitalSignsRequest $request, VitalSignsRecord $vitalSign): JsonResponse
    {
        // Ensure the record belongs to the authenticated user
        if ($vitalSign->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to vital signs record.',
            ], 403);
        }

        $vitalSign = $this->vitalSignsService->update($vitalSign, $request->validated());

        return response()->json([
            'message' => 'Vital signs record updated successfully.',
            'data' => $vitalSign,
        ]);
    }

    /**
     * Remove the specified vital signs record.
     */
    public function destroy(VitalSignsRecord $vitalSign): JsonResponse
    {
        // Ensure the record belongs to the authenticated user
        if ($vitalSign->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to vital signs record.',
            ], 403);
        }

        $this->vitalSignsService->delete($vitalSign);

        return response()->json([
            'message' => 'Vital signs record deleted successfully.',
        ]);
    }

    /**
     * Get recent vital signs for the authenticated user.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = Auth::user();
        $days = $request->integer('days', 7);

        $recentVitalSigns = $this->vitalSignsService->getRecentVitalSigns($user, $days);

        return response()->json([
            'data' => $recentVitalSigns,
            'meta' => [
                'days' => $days,
                'count' => $recentVitalSigns->count(),
            ],
        ]);
    }

    /**
     * Get flagged vital signs records that need attention.
     */
    public function flagged(): JsonResponse
    {
        $user = Auth::user();

        $flaggedRecords = $this->vitalSignsService->getFlaggedRecords($user);

        return response()->json([
            'data' => $flaggedRecords,
            'meta' => [
                'count' => $flaggedRecords->count(),
            ],
        ]);
    }

    /**
     * Get vital signs summary for the authenticated user.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = Auth::user();
        $days = $request->integer('days', 30);

        $summary = $this->vitalSignsService->getUserSummary($user, $days);

        return response()->json([
            'data' => $summary,
            'meta' => [
                'days' => $days,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get vital signs by type for the authenticated user.
     */
    public function byType(Request $request, int $vitalSignTypeId): JsonResponse
    {
        $user = Auth::user();
        $days = $request->integer('days', 30);

        $vitalSigns = $this->vitalSignsService->getVitalSignsByType($user, $vitalSignTypeId, $days);

        return response()->json([
            'data' => $vitalSigns,
            'meta' => [
                'vital_sign_type_id' => $vitalSignTypeId,
                'days' => $days,
                'count' => $vitalSigns->count(),
            ],
        ]);
    }

    /**
     * Bulk import vital signs records.
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'records' => 'required|array|min:1|max:100',
            'records.*.vital_sign_type_id' => 'required|integer|exists:vital_sign_types,id',
            'records.*.value_primary' => 'required|numeric|min:0',
            'records.*.value_secondary' => 'nullable|numeric|min:0',
            'records.*.unit' => 'required|string|max:20',
            'records.*.measured_at' => 'required|date|before_or_equal:now',
            'records.*.measurement_method' => 'required|in:manual,device,estimated',
            'records.*.device_name' => 'nullable|string|max:100',
            'records.*.notes' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        $results = $this->vitalSignsService->bulkImport($user, $request->input('records'));

        $status = $results['failed'] > 0 ? 207 : 201; // 207 Multi-Status if there are failures

        return response()->json([
            'message' => 'Bulk import completed.',
            'data' => $results,
        ], $status);
    }
}
