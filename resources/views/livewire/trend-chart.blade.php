<?php

use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use function Livewire\Volt\{state, computed, mount, on};

state([
    'vital_sign_type_id' => null,
    'period' => 7, // days
    'chart_height' => 240,
    'chart_width' => 600,
    'show_normal_range' => true,
    'show_flagged_only' => false,
]);

mount(function ($vitalSignTypeId = null, $period = 7, $height = 240, $width = 600) {
    $this->vital_sign_type_id = $vitalSignTypeId;
    $this->period = $period;
    $this->chart_height = $height;
    $this->chart_width = $width;
});

$vitalSignTypes = computed(fn () => VitalSignType::where('is_active', true)->orderBy('display_name')->get());

$selectedType = computed(function () {
    if (!$this->vital_sign_type_id) {
        return null;
    }
    return VitalSignType::find($this->vital_sign_type_id);
});

$chartData = computed(function () {
    if (!$this->vital_sign_type_id || !Auth::check()) {
        return collect([]);
    }

    $query = VitalSignsRecord::where('user_id', Auth::id())
        ->where('vital_sign_type_id', $this->vital_sign_type_id)
        ->where('measured_at', '>=', now()->subDays($this->period))
        ->orderBy('measured_at');

    if ($this->show_flagged_only) {
        $query->where('is_flagged', true);
    }

    return $query->get();
});

$chartStats = computed(function () {
    $data = $this->chartData;

    if ($data->isEmpty()) {
        return [
            'count' => 0,
            'avg' => 0,
            'min' => 0,
            'max' => 0,
            'trend' => 'stable',
            'flagged_count' => 0,
        ];
    }

    $values = $data->pluck('value_primary')->map(fn($v) => floatval($v));
    $flaggedCount = $data->where('is_flagged', true)->count();

    // Calculate trend (simple linear regression)
    $n = $values->count();
    if ($n < 2) {
        $trend = 'stable';
    } else {
        $x_values = range(1, $n);
        $x_avg = array_sum($x_values) / $n;
        $y_avg = $values->avg();

        $numerator = array_sum(array_map(function($i) use ($x_values, $values, $x_avg, $y_avg) {
            return ($x_values[$i] - $x_avg) * ($values[$i] - $y_avg);
        }, range(0, $n - 1)));

        $denominator = array_sum(array_map(function($i) use ($x_values, $x_avg) {
            return pow($x_values[$i] - $x_avg, 2);
        }, range(0, $n - 1)));

        if ($denominator == 0) {
            $trend = 'stable';
        } else {
            $slope = $numerator / $denominator;
            if (abs($slope) < 0.1) {
                $trend = 'stable';
            } elseif ($slope > 0) {
                $trend = 'increasing';
            } else {
                $trend = 'decreasing';
            }
        }
    }

    return [
        'count' => $n,
        'avg' => round($values->avg(), 2),
        'min' => $values->min(),
        'max' => $values->max(),
        'trend' => $trend,
        'flagged_count' => $flaggedCount,
    ];
});

$svgChart = computed(function () {
    $data = $this->chartData;
    $type = $this->selectedType;

    if ($data->isEmpty() || !$type) {
        return null;
    }

    $padding = 40;
    $chartWidth = $this->chart_width - (2 * $padding);
    $chartHeight = $this->chart_height - (2 * $padding);

    // Prepare data points
    $values = $data->pluck('value_primary')->map(fn($v) => floatval($v))->toArray();
    $dates = $data->pluck('measured_at')->map(fn($d) => Carbon::parse($d))->toArray();
    $flagged = $data->pluck('is_flagged')->toArray();

    // Calculate ranges
    $minValue = min($values);
    $maxValue = max($values);
    $valueRange = $maxValue - $minValue;

    // Add padding to value range
    if ($valueRange == 0) {
        $valueRange = 1;
        $minValue -= 0.5;
        $maxValue += 0.5;
    } else {
        $valuePadding = $valueRange * 0.1;
        $minValue -= $valuePadding;
        $maxValue += $valuePadding;
        $valueRange = $maxValue - $minValue;
    }

    // Time range
    $minDate = min($dates)->timestamp;
    $maxDate = max($dates)->timestamp;
    $dateRange = $maxDate - $minDate;
    if ($dateRange == 0) $dateRange = 86400; // 1 day default

    // Generate SVG points
    $points = [];
    $circles = [];
    foreach ($data as $index => $record) {
        $x = $padding + ($chartWidth * (Carbon::parse($record->measured_at)->timestamp - $minDate)) / $dateRange;
        $y = $padding + $chartHeight - (($record->value_primary - $minValue) / $valueRange) * $chartHeight;

        $points[] = "$x,$y";
        $circles[] = [
            'x' => $x,
            'y' => $y,
            'value' => $record->value_primary,
            'date' => Carbon::parse($record->measured_at)->format('M j, Y H:i'),
            'flagged' => $record->is_flagged,
            'notes' => $record->notes,
        ];
    }

    $pathData = 'M ' . implode(' L ', $points);

    // Generate normal range band if available
    $normalRangePath = null;
    if ($this->show_normal_range && $type->normal_range_min && $type->normal_range_max) {
        $normalMin = $type->normal_range_min;
        $normalMax = $type->normal_range_max;

        if ($normalMin >= $minValue && $normalMax <= $maxValue) {
            $yMin = $padding + $chartHeight - (($normalMin - $minValue) / $valueRange) * $chartHeight;
            $yMax = $padding + $chartHeight - (($normalMax - $minValue) / $valueRange) * $chartHeight;

            $normalRangePath = "M $padding,$yMax L " . ($padding + $chartWidth) . ",$yMax L " .
                              ($padding + $chartWidth) . ",$yMin L $padding,$yMin Z";
        }
    }

    // Generate Y-axis labels
    $yLabels = [];
    for ($i = 0; $i <= 4; $i++) {
        $value = $minValue + ($valueRange * $i / 4);
        $y = $padding + $chartHeight - (($value - $minValue) / $valueRange) * $chartHeight;
        $yLabels[] = [
            'y' => $y,
            'value' => round($value, 1),
        ];
    }

    // Generate X-axis labels
    $xLabels = [];
    for ($i = 0; $i <= 4; $i++) {
        $timestamp = $minDate + ($dateRange * $i / 4);
        $x = $padding + ($chartWidth * $i / 4);
        $xLabels[] = [
            'x' => $x,
            'date' => Carbon::createFromTimestamp($timestamp)->format('M j'),
        ];
    }

    return [
        'svg_width' => $this->chart_width,
        'svg_height' => $this->chart_height,
        'path_data' => $pathData,
        'normal_range_path' => $normalRangePath,
        'circles' => $circles,
        'y_labels' => $yLabels,
        'x_labels' => $xLabels,
        'chart_area' => [
            'x' => $padding,
            'y' => $padding,
            'width' => $chartWidth,
            'height' => $chartHeight,
        ],
    ];
});

on(['vital-signs-recorded' => function () {
    // Refresh chart data when new vital signs are recorded
    unset($this->chartData);
    unset($this->chartStats);
    unset($this->svgChart);
}]);

$updatePeriod = function ($days) {
    $this->period = $days;
};

$toggleNormalRange = function () {
    $this->show_normal_range = !$this->show_normal_range;
};

$toggleFlaggedOnly = function () {
    $this->show_flagged_only = !$this->show_flagged_only;
};

?>

<div class="bg-white rounded-lg shadow-sm border p-6">
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Vital Signs Trends</h3>
                <p class="text-sm text-gray-600">
                    @if($this->selectedType)
                        {{ $this->selectedType->display_name }} over the last {{ $period }} days
                    @else
                        Select a vital sign type to view trends
                    @endif
                </p>
            </div>

            <!-- Controls -->
            <div class="flex items-center space-x-4">
                <!-- Period selector -->
                <div class="flex rounded-md shadow-sm">
                    <button
                        wire:click="updatePeriod(7)"
                        class="px-3 py-1 text-sm font-medium {{ $period === 7 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }} border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        7D
                    </button>
                    <button
                        wire:click="updatePeriod(30)"
                        class="px-3 py-1 text-sm font-medium {{ $period === 30 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }} border-t border-b border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        30D
                    </button>
                    <button
                        wire:click="updatePeriod(90)"
                        class="px-3 py-1 text-sm font-medium {{ $period === 90 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }} border border-gray-300 rounded-r-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        90D
                    </button>
                </div>

                <!-- Toggle buttons -->
                @if($this->selectedType)
                    <div class="flex space-x-2">
                        <button
                            wire:click="toggleNormalRange"
                            class="px-3 py-1 text-xs font-medium {{ $show_normal_range ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }} rounded-md hover:bg-green-200 focus:outline-none"
                        >
                            Normal Range
                        </button>
                        <button
                            wire:click="toggleFlaggedOnly"
                            class="px-3 py-1 text-xs font-medium {{ $show_flagged_only ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600' }} rounded-md hover:bg-red-200 focus:outline-none"
                        >
                            Flagged Only
                        </button>
                    </div>
                @endif
            </div>
        </div>

        @if(!$this->vital_sign_type_id)
            <!-- Type selector -->
            <div class="mb-4">
                <label for="vital_sign_type_select" class="block text-sm font-medium text-gray-700 mb-2">
                    Select Vital Sign Type:
                </label>
                <select
                    wire:model.live="vital_sign_type_id"
                    id="vital_sign_type_select"
                    class="w-full max-w-md px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="">Choose a measurement type...</option>
                    @foreach($this->vitalSignTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->display_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    @if($this->chartData->isNotEmpty() && $this->svgChart)
        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-900">{{ $this->chartStats['count'] }}</div>
                <div class="text-xs text-gray-500">Readings</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $this->chartStats['avg'] }}</div>
                <div class="text-xs text-gray-500">Average</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600">{{ $this->chartStats['min'] }}</div>
                <div class="text-xs text-gray-500">Minimum</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-red-600">{{ $this->chartStats['max'] }}</div>
                <div class="text-xs text-gray-500">Maximum</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold {{ $this->chartStats['trend'] === 'increasing' ? 'text-orange-600' : ($this->chartStats['trend'] === 'decreasing' ? 'text-blue-600' : 'text-gray-600') }}">
                    @if($this->chartStats['trend'] === 'increasing')
                        ↗
                    @elseif($this->chartStats['trend'] === 'decreasing')
                        ↘
                    @else
                        →
                    @endif
                </div>
                <div class="text-xs text-gray-500">{{ ucfirst($this->chartStats['trend']) }}</div>
            </div>
        </div>

        <!-- Chart -->
        <div class="relative bg-gray-50 rounded-lg p-4 overflow-x-auto">
            <svg width="{{ $this->svgChart['svg_width'] }}" height="{{ $this->svgChart['svg_height'] }}" class="w-full">
                <!-- Grid lines -->
                <defs>
                    <pattern id="grid" width="50" height="50" patternUnits="userSpaceOnUse">
                        <path d="M 50 0 L 0 0 0 50" fill="none" stroke="#e5e7eb" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect x="{{ $this->svgChart['chart_area']['x'] }}" y="{{ $this->svgChart['chart_area']['y'] }}"
                      width="{{ $this->svgChart['chart_area']['width'] }}" height="{{ $this->svgChart['chart_area']['height'] }}"
                      fill="url(#grid)" opacity="0.5"/>

                <!-- Normal range band -->
                @if($this->svgChart['normal_range_path'] && $show_normal_range)
                    <path d="{{ $this->svgChart['normal_range_path'] }}" fill="#dcfce7" opacity="0.6" />
                @endif

                <!-- Trend line -->
                <path d="{{ $this->svgChart['path_data'] }}" fill="none" stroke="#3b82f6" stroke-width="3" />

                <!-- Data points -->
                @foreach($this->svgChart['circles'] as $circle)
                    <circle
                        cx="{{ $circle['x'] }}"
                        cy="{{ $circle['y'] }}"
                        r="{{ $circle['flagged'] ? '6' : '4' }}"
                        fill="{{ $circle['flagged'] ? '#ef4444' : '#3b82f6' }}"
                        stroke="white"
                        stroke-width="2"
                        class="hover:r-8 cursor-pointer"
                        title="{{ $circle['date'] }}: {{ $circle['value'] }} {{ $this->selectedType->unit_primary }}{{ $circle['notes'] ? ' - ' . $circle['notes'] : '' }}"
                    >
                        <title>{{ $circle['date'] }}: {{ $circle['value'] }} {{ $this->selectedType->unit_primary }}{{ $circle['notes'] ? "\n" . $circle['notes'] : '' }}</title>
                    </circle>
                @endforeach

                <!-- Y-axis labels -->
                @foreach($this->svgChart['y_labels'] as $label)
                    <text x="35" y="{{ $label['y'] + 4 }}" text-anchor="end" font-size="12" fill="#6b7280">
                        {{ $label['value'] }}
                    </text>
                    <line x1="38" y1="{{ $label['y'] }}" x2="{{ $this->svgChart['chart_area']['x'] }}" y2="{{ $label['y'] }}" stroke="#d1d5db" stroke-width="1" />
                @endforeach

                <!-- X-axis labels -->
                @foreach($this->svgChart['x_labels'] as $label)
                    <text x="{{ $label['x'] }}" y="{{ $this->svgChart['svg_height'] - 10 }}" text-anchor="middle" font-size="12" fill="#6b7280">
                        {{ $label['date'] }}
                    </text>
                    <line x1="{{ $label['x'] }}" y1="{{ $this->svgChart['chart_area']['y'] + $this->svgChart['chart_area']['height'] }}"
                          x2="{{ $label['x'] }}" y2="{{ $this->svgChart['chart_area']['y'] + $this->svgChart['chart_area']['height'] + 5 }}"
                          stroke="#d1d5db" stroke-width="1" />
                @endforeach

                <!-- Axis lines -->
                <line x1="{{ $this->svgChart['chart_area']['x'] }}" y1="{{ $this->svgChart['chart_area']['y'] }}"
                      x2="{{ $this->svgChart['chart_area']['x'] }}" y2="{{ $this->svgChart['chart_area']['y'] + $this->svgChart['chart_area']['height'] }}"
                      stroke="#374151" stroke-width="2" />
                <line x1="{{ $this->svgChart['chart_area']['x'] }}" y1="{{ $this->svgChart['chart_area']['y'] + $this->svgChart['chart_area']['height'] }}"
                      x2="{{ $this->svgChart['chart_area']['x'] + $this->svgChart['chart_area']['width'] }}" y2="{{ $this->svgChart['chart_area']['y'] + $this->svgChart['chart_area']['height'] }}"
                      stroke="#374151" stroke-width="2" />
            </svg>

            <!-- Legend -->
            <div class="mt-4 flex flex-wrap items-center justify-center gap-4 text-sm">
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                    <span>Normal Reading</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                    <span>Flagged Reading</span>
                </div>
                @if($show_normal_range && $this->selectedType && $this->selectedType->normal_range_min)
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-200 mr-2"></div>
                        <span>Normal Range</span>
                    </div>
                @endif
            </div>
        </div>

        @if($this->chartStats['flagged_count'] > 0)
            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-yellow-800">
                            {{ $this->chartStats['flagged_count'] }} of {{ $this->chartStats['count'] }} readings are outside normal ranges and may require attention.
                        </p>
                    </div>
                </div>
            </div>
        @endif

    @elseif($this->vital_sign_type_id)
        <!-- No data state -->
        <div class="text-center py-12 bg-gray-50 rounded-lg">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No data available</h3>
            <p class="mt-1 text-sm text-gray-500">
                No {{ $this->selectedType->display_name }} readings found for the last {{ $period }} days.
            </p>
            <p class="mt-2 text-sm text-gray-500">
                Start recording measurements to see your trends here.
            </p>
        </div>
    @else
        <!-- Initial state -->
        <div class="text-center py-12 bg-gray-50 rounded-lg">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Select a measurement type</h3>
            <p class="mt-1 text-sm text-gray-500">Choose a vital sign type above to view your health trends.</p>
        </div>
    @endif
</div>
