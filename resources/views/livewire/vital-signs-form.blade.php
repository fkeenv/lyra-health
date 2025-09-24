<?php

use App\Models\VitalSignType;
use App\Models\VitalSignsRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{state, computed, mount, rules, messages, with};

state([
    'vital_sign_type_id' => '',
    'value_primary' => '',
    'value_secondary' => '',
    'unit' => '',
    'measured_at' => '',
    'notes' => '',
    'measurement_method' => 'manual',
    'device_name' => '',
    'success_message' => '',
    'error_message' => '',
    'show_secondary' => false,
]);

mount(function () {
    $this->measured_at = now()->format('Y-m-d\TH:i');
});

$vitalSignTypes = computed(fn () => VitalSignType::where('is_active', true)->orderBy('display_name')->get());

$selectedType = computed(function () {
    if (!$this->vital_sign_type_id) {
        return null;
    }
    return VitalSignType::find($this->vital_sign_type_id);
});

rules([
    'vital_sign_type_id' => ['required', 'integer', function ($attribute, $value, $fail) {
        if (!VitalSignType::where('id', $value)->where('is_active', true)->exists()) {
            $fail('The selected vital sign type is invalid or inactive.');
        }
    }],
    'value_primary' => ['required', 'numeric', 'min:0', 'max:999999.99'],
    'value_secondary' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
    'unit' => ['required', 'string', 'max:20'],
    'measured_at' => ['required', 'date', 'before_or_equal:now'],
    'notes' => ['nullable', 'string', 'max:1000'],
    'measurement_method' => ['required', Rule::in(['manual', 'device', 'estimated'])],
    'device_name' => ['nullable', 'string', 'max:100'],
]);

messages([
    'vital_sign_type_id.required' => 'Please select a vital sign type.',
    'value_primary.required' => 'Please enter a measurement value.',
    'value_primary.numeric' => 'The measurement value must be a number.',
    'value_primary.min' => 'The measurement value cannot be negative.',
    'unit.required' => 'Please specify the unit of measurement.',
    'measured_at.required' => 'Please specify when the measurement was taken.',
    'measured_at.before_or_equal' => 'The measurement date cannot be in the future.',
    'measurement_method.required' => 'Please specify how the measurement was taken.',
]);

$updateVitalSignType = function () {
    if ($this->vital_sign_type_id) {
        $type = VitalSignType::find($this->vital_sign_type_id);
        if ($type) {
            $this->unit = $type->unit_primary;
            $this->show_secondary = $type->has_secondary_value;

            // Clear secondary value if not needed
            if (!$type->has_secondary_value) {
                $this->value_secondary = '';
            }
        }
    } else {
        $this->unit = '';
        $this->show_secondary = false;
        $this->value_secondary = '';
    }
};

$updateMeasurementMethod = function () {
    if ($this->measurement_method !== 'device') {
        $this->device_name = '';
    }
};

$validateValue = function () {
    if ($this->vital_sign_type_id && $this->value_primary) {
        $type = VitalSignType::find($this->vital_sign_type_id);
        if ($type) {
            $value = floatval($this->value_primary);

            // Check ranges and provide feedback
            if ($type->isValueCritical($value)) {
                $this->error_message = 'Warning: This value is outside the safe range!';
            } elseif ($type->isValueInWarningRange($value)) {
                $this->error_message = 'Caution: This value is in the warning range.';
            } elseif (!$type->isValueNormal($value)) {
                $this->error_message = 'Note: This value is outside the normal range.';
            } else {
                $this->error_message = '';
            }
        }
    }
};

$save = function () {
    $this->validate();

    $type = VitalSignType::find($this->vital_sign_type_id);
    if (!$type) {
        $this->error_message = 'Invalid vital sign type selected.';
        return;
    }

    // Additional validation for secondary value
    if ($type->has_secondary_value && !$this->value_secondary) {
        $this->addError('value_secondary', 'A secondary value is required for this type of measurement.');
        return;
    }

    if (!$type->has_secondary_value && $this->value_secondary) {
        $this->addError('value_secondary', 'Secondary value is not applicable for this type of measurement.');
        return;
    }

    // Validate device name for device measurements
    if ($this->measurement_method === 'device' && !$this->device_name) {
        $this->addError('device_name', 'Device name is required when measurement method is "device".');
        return;
    }

    try {
        // Determine if value should be flagged
        $value = floatval($this->value_primary);
        $is_flagged = false;
        $flag_reason = null;

        if ($type->isValueCritical($value)) {
            $is_flagged = true;
            $flag_reason = 'Value outside safe range';
        } elseif ($type->isValueInWarningRange($value)) {
            $is_flagged = true;
            $flag_reason = 'Value in warning range';
        } elseif (!$type->isValueNormal($value)) {
            $is_flagged = true;
            $flag_reason = 'Value outside normal range';
        }

        VitalSignsRecord::create([
            'user_id' => Auth::id(),
            'vital_sign_type_id' => $this->vital_sign_type_id,
            'value_primary' => $this->value_primary,
            'value_secondary' => $this->value_secondary ?: null,
            'unit' => $this->unit,
            'measured_at' => $this->measured_at,
            'notes' => $this->notes ?: null,
            'measurement_method' => $this->measurement_method,
            'device_name' => $this->device_name ?: null,
            'is_flagged' => $is_flagged,
            'flag_reason' => $flag_reason,
        ]);

        $this->success_message = 'Vital signs recorded successfully!';
        $this->error_message = '';

        // Reset form
        $this->reset([
            'vital_sign_type_id', 'value_primary', 'value_secondary',
            'notes', 'device_name', 'show_secondary'
        ]);
        $this->unit = '';
        $this->measurement_method = 'manual';
        $this->measured_at = now()->format('Y-m-d\TH:i');

        // Dispatch event for parent components
        $this->dispatch('vital-signs-recorded');

    } catch (\Exception $e) {
        $this->error_message = 'Failed to save vital signs. Please try again.';
    }
};

?>

<div class="bg-white rounded-lg shadow-sm border p-6">
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Record Vital Signs</h3>
        <p class="text-sm text-gray-600">Enter your health measurements below</p>
    </div>

    @if($success_message)
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ $success_message }}</p>
                </div>
            </div>
        </div>
    @endif

    @if($error_message)
        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-yellow-800">{{ $error_message }}</p>
                </div>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Vital Sign Type -->
            <div>
                <label for="vital_sign_type_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Measurement Type *
                </label>
                <select
                    wire:model.live="vital_sign_type_id"
                    wire:change="updateVitalSignType"
                    id="vital_sign_type_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('vital_sign_type_id') border-red-500 @enderror"
                >
                    <option value="">Select measurement type...</option>
                    @foreach($this->vitalSignTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->display_name }}</option>
                    @endforeach
                </select>
                @error('vital_sign_type_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Measurement Method -->
            <div>
                <label for="measurement_method" class="block text-sm font-medium text-gray-700 mb-1">
                    How was this measured? *
                </label>
                <select
                    wire:model.live="measurement_method"
                    wire:change="updateMeasurementMethod"
                    id="measurement_method"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="manual">Manual Entry</option>
                    <option value="device">Medical Device</option>
                    <option value="estimated">Estimated</option>
                </select>
                @error('measurement_method')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Device Name (conditional) -->
        @if($measurement_method === 'device')
            <div>
                <label for="device_name" class="block text-sm font-medium text-gray-700 mb-1">
                    Device Name *
                </label>
                <input
                    wire:model="device_name"
                    type="text"
                    id="device_name"
                    placeholder="e.g., Omron BP Monitor, Apple Watch"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('device_name') border-red-500 @enderror"
                />
                @error('device_name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Primary Value -->
            <div>
                <label for="value_primary" class="block text-sm font-medium text-gray-700 mb-1">
                    @if($this->selectedType)
                        {{ $this->selectedType->display_name }} Value *
                    @else
                        Measurement Value *
                    @endif
                </label>
                <div class="relative">
                    <input
                        wire:model.live.debounce.300ms="value_primary"
                        wire:blur="validateValue"
                        type="number"
                        step="0.01"
                        id="value_primary"
                        placeholder="Enter value"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('value_primary') border-red-500 @enderror"
                    />
                    @if($unit)
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 text-sm">{{ $unit }}</span>
                        </div>
                    @endif
                </div>
                @error('value_primary')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror

                @if($this->selectedType && $value_primary)
                    <div class="mt-1 text-xs text-gray-500">
                        Normal range: {{ $this->selectedType->normal_range_min ?? '?' }} - {{ $this->selectedType->normal_range_max ?? '?' }} {{ $unit }}
                    </div>
                @endif
            </div>

            <!-- Secondary Value (conditional) -->
            @if($show_secondary)
                <div>
                    <label for="value_secondary" class="block text-sm font-medium text-gray-700 mb-1">
                        Secondary Value *
                    </label>
                    <div class="relative">
                        <input
                            wire:model="value_secondary"
                            type="number"
                            step="0.01"
                            id="value_secondary"
                            placeholder="Secondary value"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('value_secondary') border-red-500 @enderror"
                        />
                        @if($this->selectedType && $this->selectedType->unit_secondary)
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 text-sm">{{ $this->selectedType->unit_secondary }}</span>
                            </div>
                        @endif
                    </div>
                    @error('value_secondary')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <!-- Measurement Date/Time -->
            <div>
                <label for="measured_at" class="block text-sm font-medium text-gray-700 mb-1">
                    When was this measured? *
                </label>
                <input
                    wire:model="measured_at"
                    type="datetime-local"
                    id="measured_at"
                    max="{{ now()->format('Y-m-d\TH:i') }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('measured_at') border-red-500 @enderror"
                />
                @error('measured_at')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Notes -->
        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                Additional Notes
            </label>
            <textarea
                wire:model="notes"
                id="notes"
                rows="3"
                placeholder="Any additional context or notes about this measurement..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('notes') border-red-500 @enderror"
            ></textarea>
            @error('notes')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-sm text-gray-500">{{ strlen($notes ?? '') }}/1000 characters</p>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
            >
                <span wire:loading.remove>Record Vital Signs</span>
                <span wire:loading>
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
        </div>
    </form>
</div>
