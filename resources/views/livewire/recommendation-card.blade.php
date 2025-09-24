<?php

use App\Models\Recommendation;
use Illuminate\Support\Carbon;
use function Livewire\Volt\{state, mount, computed};

state([
    'recommendation_id' => null,
    'show_actions' => true,
    'show_metadata' => false,
    'compact_mode' => false,
]);

mount(function ($recommendationId, $showActions = true, $compactMode = false) {
    $this->recommendation_id = $recommendationId;
    $this->show_actions = $showActions;
    $this->compact_mode = $compactMode;
});

$recommendation = computed(function () {
    if (!$this->recommendation_id) {
        return null;
    }
    return Recommendation::with(['vitalSignsRecord.vitalSignType'])->find($this->recommendation_id);
});

$markAsRead = function () {
    if (!$this->recommendation) {
        return;
    }

    if (!$this->recommendation->isRead()) {
        $this->recommendation->markAsRead();
        $this->dispatch('recommendation-updated', $this->recommendation_id);
    }
};

$dismiss = function () {
    if (!$this->recommendation) {
        return;
    }

    if (!$this->recommendation->isDismissed()) {
        $this->recommendation->dismissed_at = now();
        $this->recommendation->save();
        $this->dispatch('recommendation-dismissed', $this->recommendation_id);
    }
};

$toggleMetadata = function () {
    $this->show_metadata = !$this->show_metadata;
};

$getSeverityConfig = function ($severity) {
    return match($severity) {
        'low' => [
            'bg_color' => 'bg-blue-50',
            'border_color' => 'border-blue-200',
            'text_color' => 'text-blue-800',
            'icon_color' => 'text-blue-500',
            'icon' => 'info',
        ],
        'medium' => [
            'bg_color' => 'bg-yellow-50',
            'border_color' => 'border-yellow-200',
            'text_color' => 'text-yellow-800',
            'icon_color' => 'text-yellow-500',
            'icon' => 'warning',
        ],
        'high' => [
            'bg_color' => 'bg-red-50',
            'border_color' => 'border-red-200',
            'text_color' => 'text-red-800',
            'icon_color' => 'text-red-500',
            'icon' => 'alert',
        ],
        'critical' => [
            'bg_color' => 'bg-red-100',
            'border_color' => 'border-red-300',
            'text_color' => 'text-red-900',
            'icon_color' => 'text-red-600',
            'icon' => 'alert',
        ],
        default => [
            'bg_color' => 'bg-gray-50',
            'border_color' => 'border-gray-200',
            'text_color' => 'text-gray-800',
            'icon_color' => 'text-gray-500',
            'icon' => 'info',
        ],
    };
};

$getTypeIcon = function ($type) {
    return match($type) {
        'alert' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z',
        'suggestion' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
        'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z',
        'congratulation' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        default => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    };
};

?>

@if($this->recommendation)
    @php
        $rec = $this->recommendation;
        $config = $this->getSeverityConfig($rec->severity);
        $typeIcon = $this->getTypeIcon($rec->recommendation_type);
        $isRead = $rec->isRead();
        $isDismissed = $rec->isDismissed();
        $isExpired = $rec->isExpired();
    @endphp

    <div
        class="relative {{ $compact_mode ? 'p-4' : 'p-6' }} {{ $config['bg_color'] }} {{ $config['border_color'] }} border rounded-lg shadow-sm transition-all duration-200 {{ $isDismissed ? 'opacity-60' : 'hover:shadow-md' }} {{ !$isRead && !$isDismissed ? 'ring-2 ring-offset-2 ring-blue-200' : '' }}"
        wire:key="recommendation-{{ $rec->id }}"
    >
        @if($isDismissed)
            <div class="absolute top-2 right-2">
                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-gray-100 text-gray-600 rounded-full">
                    Dismissed
                </span>
            </div>
        @elseif($isExpired)
            <div class="absolute top-2 right-2">
                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-gray-100 text-gray-600 rounded-full">
                    Expired
                </span>
            </div>
        @elseif(!$isRead)
            <div class="absolute top-2 right-2">
                <div class="w-3 h-3 bg-blue-500 rounded-full animate-pulse"></div>
            </div>
        @endif

        <div class="flex items-start {{ $compact_mode ? 'space-x-3' : 'space-x-4' }}">
            <!-- Icon -->
            <div class="flex-shrink-0">
                <svg class="{{ $compact_mode ? 'h-5 w-5' : 'h-6 w-6' }} {{ $config['icon_color'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $typeIcon }}" />
                </svg>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <!-- Title and Type -->
                        <div class="flex items-center {{ $compact_mode ? 'space-x-2 mb-1' : 'space-x-3 mb-2' }}">
                            <h3 class="{{ $compact_mode ? 'text-sm' : 'text-base' }} font-semibold {{ $config['text_color'] }}">
                                {{ $rec->title }}
                            </h3>
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium {{ $config['bg_color'] }} {{ $config['text_color'] }} border {{ $config['border_color'] }} rounded-full">
                                {{ ucfirst($rec->recommendation_type) }}
                            </span>
                            @if($rec->severity === 'high' || $rec->severity === 'critical')
                                <span class="inline-flex items-center px-2 py-1 text-xs font-bold bg-red-100 text-red-800 border border-red-200 rounded-full">
                                    {{ strtoupper($rec->severity) }}
                                </span>
                            @endif
                        </div>

                        <!-- Message -->
                        <p class="{{ $compact_mode ? 'text-sm' : 'text-base' }} {{ $config['text_color'] }} {{ $compact_mode ? 'mb-2' : 'mb-3' }}">
                            {{ $rec->message }}
                        </p>

                        <!-- Related Vital Sign (if available) -->
                        @if($rec->vitalSignsRecord && $rec->vitalSignsRecord->vitalSignType)
                            <div class="{{ $compact_mode ? 'mb-2' : 'mb-3' }} p-2 bg-white bg-opacity-60 rounded border border-opacity-50 {{ $config['border_color'] }}">
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="h-4 w-4 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    <span class="font-medium">Related to:</span>
                                    <span class="ml-1">{{ $rec->vitalSignsRecord->vitalSignType->display_name }}</span>
                                    <span class="ml-2 font-mono">{{ $rec->vitalSignsRecord->getDisplayValue() }}</span>
                                    <span class="ml-2 text-xs text-gray-500">
                                        {{ Carbon::parse($rec->vitalSignsRecord->measured_at)->format('M j, g:i A') }}
                                    </span>
                                </div>
                            </div>
                        @endif

                        <!-- Metadata (expandable) -->
                        @if($rec->metadata && !empty($rec->metadata))
                            <div class="{{ $compact_mode ? 'mb-2' : 'mb-3' }}">
                                <button
                                    wire:click="toggleMetadata"
                                    class="flex items-center text-xs text-gray-500 hover:text-gray-700 focus:outline-none"
                                >
                                    <svg class="h-3 w-3 mr-1 transform transition-transform {{ $show_metadata ? 'rotate-90' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    Additional Details
                                </button>

                                @if($show_metadata)
                                    <div class="mt-2 p-2 bg-white bg-opacity-60 rounded border border-opacity-50 {{ $config['border_color'] }}">
                                        <dl class="text-xs space-y-1">
                                            @foreach($rec->metadata as $key => $value)
                                                <div class="flex">
                                                    <dt class="font-medium text-gray-600 mr-2">{{ ucfirst(str_replace('_', ' ', $key)) }}:</dt>
                                                    <dd class="text-gray-800">
                                                        @if(is_array($value))
                                                            {{ implode(', ', $value) }}
                                                        @elseif(is_bool($value))
                                                            {{ $value ? 'Yes' : 'No' }}
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Footer Info -->
                        <div class="flex items-center {{ $compact_mode ? 'text-xs' : 'text-sm' }} text-gray-500 space-x-4">
                            <div class="flex items-center">
                                <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ $rec->created_at->diffForHumans() }}
                            </div>

                            @if($rec->expires_at)
                                <div class="flex items-center {{ $isExpired ? 'text-red-500' : '' }}">
                                    <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ $isExpired ? 'Expired' : 'Expires' }} {{ $rec->expires_at->diffForHumans() }}
                                </div>
                            @endif

                            @if($rec->action_required)
                                <div class="flex items-center text-orange-600">
                                    <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                    Action Required
                                </div>
                            @endif

                            @if($isRead)
                                <div class="flex items-center text-green-600">
                                    <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Read {{ $rec->read_at->diffForHumans() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                @if($show_actions && !$isDismissed && !$isExpired)
                    <div class="flex items-center {{ $compact_mode ? 'space-x-2 mt-3' : 'space-x-3 mt-4' }}">
                        @if(!$isRead)
                            <button
                                wire:click="markAsRead"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center px-3 py-1 text-xs font-medium bg-white text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                            >
                                <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span wire:loading.remove wire:target="markAsRead">Mark as Read</span>
                                <span wire:loading wire:target="markAsRead">Marking...</span>
                            </button>
                        @endif

                        <button
                            wire:click="dismiss"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-3 py-1 text-xs font-medium bg-white text-gray-500 border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50"
                        >
                            <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <span wire:loading.remove wire:target="dismiss">Dismiss</span>
                            <span wire:loading wire:target="dismiss">Dismissing...</span>
                        </button>

                        @if($rec->action_required)
                            <button class="inline-flex items-center px-3 py-1 text-xs font-medium bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                Take Action
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@else
    <!-- Error state -->
    <div class="p-6 bg-red-50 border border-red-200 rounded-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Recommendation Not Found</h3>
                <p class="mt-1 text-sm text-red-700">The requested recommendation could not be loaded.</p>
            </div>
        </div>
    </div>
@endif
