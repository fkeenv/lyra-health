<?php

namespace App\Http\Controllers;

use App\Models\Recommendation;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecommendationsController extends Controller
{
    public function __construct(
        protected RecommendationService $recommendationService
    ) {}

    /**
     * Get recommendations for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|in:dietary,exercise,medical,lifestyle,medication',
            'status' => 'nullable|in:active,read,dismissed',
            'priority' => 'nullable|in:low,medium,high,critical',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = Auth::user();

        $recommendations = $this->recommendationService->getUserRecommendations(
            user: $user,
            type: $request->string('type'),
            status: $request->string('status'),
            priority: $request->string('priority'),
            perPage: $request->integer('per_page', 15)
        );

        return response()->json([
            'data' => $recommendations->items(),
            'meta' => [
                'current_page' => $recommendations->currentPage(),
                'last_page' => $recommendations->lastPage(),
                'per_page' => $recommendations->perPage(),
                'total' => $recommendations->total(),
                'filters' => [
                    'type' => $request->string('type'),
                    'status' => $request->string('status'),
                    'priority' => $request->string('priority'),
                ],
            ],
            'links' => [
                'first' => $recommendations->url(1),
                'last' => $recommendations->url($recommendations->lastPage()),
                'prev' => $recommendations->previousPageUrl(),
                'next' => $recommendations->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Get a specific recommendation for the authenticated user.
     */
    public function show(Recommendation $recommendation): JsonResponse
    {
        // Ensure the recommendation belongs to the authenticated user
        if ($recommendation->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to recommendation.',
            ], 403);
        }

        $recommendation->load(['user', 'vitalSignsRecord.vitalSignType']);

        return response()->json([
            'data' => $recommendation,
        ]);
    }

    /**
     * Mark a recommendation as read for the authenticated user.
     */
    public function markAsRead(Recommendation $recommendation): JsonResponse
    {
        // Ensure the recommendation belongs to the authenticated user
        if ($recommendation->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to recommendation.',
            ], 403);
        }

        $updatedRecommendation = $this->recommendationService->markAsRead($recommendation);

        return response()->json([
            'message' => 'Recommendation marked as read successfully.',
            'data' => $updatedRecommendation,
        ]);
    }

    /**
     * Dismiss a recommendation for the authenticated user.
     */
    public function dismiss(Recommendation $recommendation): JsonResponse
    {
        // Ensure the recommendation belongs to the authenticated user
        if ($recommendation->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to recommendation.',
            ], 403);
        }

        $updatedRecommendation = $this->recommendationService->dismiss($recommendation);

        return response()->json([
            'message' => 'Recommendation dismissed successfully.',
            'data' => $updatedRecommendation,
        ]);
    }
}
