<?php

namespace App\Http\Controllers;

use App\Services\TrendAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrendsController extends Controller
{
    public function __construct(
        protected TrendAnalysisService $trendAnalysisService
    ) {}

    /**
     * Get trends for all vital sign types for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date|before_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date|before_or_equal:today',
            'period' => 'nullable|in:weekly,monthly,quarterly,yearly',
            'comparison_period' => 'nullable|in:previous_period,previous_year,none',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        $user = Auth::user();

        $trends = $this->trendAnalysisService->getUserTrends(
            user: $user,
            startDate: $request->string('start_date'),
            endDate: $request->string('end_date'),
            period: $request->string('period', 'monthly'),
            comparisonPeriod: $request->string('comparison_period', 'previous_period'),
            groupBy: $request->string('group_by', 'week')
        );

        return response()->json([
            'data' => $trends,
            'meta' => [
                'start_date' => $request->string('start_date'),
                'end_date' => $request->string('end_date'),
                'period' => $request->string('period', 'monthly'),
                'comparison_period' => $request->string('comparison_period', 'previous_period'),
                'group_by' => $request->string('group_by', 'week'),
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get trends for a specific vital sign type for the authenticated user.
     */
    public function show(Request $request, int $vitalSignTypeId): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date|before_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date|before_or_equal:today',
            'period' => 'nullable|in:weekly,monthly,quarterly,yearly',
            'comparison_period' => 'nullable|in:previous_period,previous_year,none',
            'group_by' => 'nullable|in:day,week,month',
            'include_averages' => 'nullable|boolean',
            'include_min_max' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        $trends = $this->trendAnalysisService->getVitalSignTypeTrends(
            user: $user,
            vitalSignTypeId: $vitalSignTypeId,
            startDate: $request->string('start_date'),
            endDate: $request->string('end_date'),
            period: $request->string('period', 'monthly'),
            comparisonPeriod: $request->string('comparison_period', 'previous_period'),
            groupBy: $request->string('group_by', 'week'),
            includeAverages: $request->boolean('include_averages', true),
            includeMinMax: $request->boolean('include_min_max', false)
        );

        return response()->json([
            'data' => $trends,
            'meta' => [
                'vital_sign_type_id' => $vitalSignTypeId,
                'start_date' => $request->string('start_date'),
                'end_date' => $request->string('end_date'),
                'period' => $request->string('period', 'monthly'),
                'comparison_period' => $request->string('comparison_period', 'previous_period'),
                'group_by' => $request->string('group_by', 'week'),
                'include_averages' => $request->boolean('include_averages', true),
                'include_min_max' => $request->boolean('include_min_max', false),
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }
}
