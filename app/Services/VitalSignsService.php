<?php

namespace App\Services;

use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VitalSignsService
{
    /**
     * Create a new vital signs record.
     */
    public function create(User $user, array $data): VitalSignsRecord
    {
        return DB::transaction(function () use ($user, $data) {
            // Get the vital sign type for validation
            $vitalSignType = VitalSignType::findOrFail($data['vital_sign_type_id']);

            // Create the record
            $record = new VitalSignsRecord([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'vital_sign_type_id' => $data['vital_sign_type_id'],
                'value_primary' => $data['value_primary'],
                'value_secondary' => $data['value_secondary'] ?? null,
                'unit' => $data['unit'],
                'measured_at' => $data['measured_at'],
                'notes' => $data['notes'] ?? null,
                'measurement_method' => $data['measurement_method'],
                'device_name' => $data['device_name'] ?? null,
            ]);

            // Check if the value should be flagged
            $this->evaluateAndFlagRecord($record, $vitalSignType);

            $record->save();

            return $record->load(['vitalSignType', 'user']);
        });
    }

    /**
     * Update an existing vital signs record.
     */
    public function update(VitalSignsRecord $record, array $data): VitalSignsRecord
    {
        return DB::transaction(function () use ($record, $data) {
            // Update the record with provided data
            $record->fill(array_filter($data, fn ($value) => $value !== null));

            // Get the vital sign type (might have changed)
            $vitalSignType = VitalSignType::findOrFail($record->vital_sign_type_id);

            // Re-evaluate flagging if values changed
            if (isset($data['value_primary']) || isset($data['value_secondary'])) {
                $this->evaluateAndFlagRecord($record, $vitalSignType);
            }

            $record->save();

            return $record->fresh(['vitalSignType', 'user']);
        });
    }

    /**
     * Delete a vital signs record.
     */
    public function delete(VitalSignsRecord $record): bool
    {
        return $record->delete();
    }

    /**
     * Get vital signs records for a user with filtering and pagination.
     */
    public function getUserVitalSigns(
        User $user,
        ?int $vitalSignTypeId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $user->vitalSignsRecords()
            ->with(['vitalSignType'])
            ->orderBy('measured_at', 'desc');

        if ($vitalSignTypeId) {
            $query->where('vital_sign_type_id', $vitalSignTypeId);
        }

        if ($startDate) {
            $query->whereDate('measured_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('measured_at', '<=', $endDate);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get recent vital signs for a user.
     */
    public function getRecentVitalSigns(User $user, int $days = 7): Collection
    {
        return $user->vitalSignsRecords()
            ->with(['vitalSignType'])
            ->where('measured_at', '>=', now()->subDays($days))
            ->orderBy('measured_at', 'desc')
            ->get();
    }

    /**
     * Get flagged vital signs records that need attention.
     */
    public function getFlaggedRecords(User $user): Collection
    {
        return $user->vitalSignsRecords()
            ->with(['vitalSignType'])
            ->where('is_flagged', true)
            ->orderBy('measured_at', 'desc')
            ->get();
    }

    /**
     * Get vital signs summary statistics for a user.
     */
    public function getUserSummary(User $user, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $records = $user->vitalSignsRecords()
            ->with('vitalSignType')
            ->where('measured_at', '>=', $startDate)
            ->get();

        $summary = [
            'total_records' => $records->count(),
            'flagged_records' => $records->where('is_flagged', true)->count(),
            'by_type' => [],
            'recent_activity' => $records->take(5),
        ];

        // Group by vital sign type
        foreach ($records->groupBy('vital_sign_type_id') as $typeId => $typeRecords) {
            $vitalSignType = $typeRecords->first()->vitalSignType;
            $summary['by_type'][$vitalSignType->name] = [
                'count' => $typeRecords->count(),
                'latest' => $typeRecords->sortByDesc('measured_at')->first(),
                'average' => $typeRecords->avg('value_primary'),
                'flagged' => $typeRecords->where('is_flagged', true)->count(),
            ];
        }

        return $summary;
    }

    /**
     * Get vital signs for a specific type over time.
     */
    public function getVitalSignsByType(
        User $user,
        int $vitalSignTypeId,
        int $days = 30
    ): Collection {
        return $user->vitalSignsRecords()
            ->with(['vitalSignType'])
            ->where('vital_sign_type_id', $vitalSignTypeId)
            ->where('measured_at', '>=', now()->subDays($days))
            ->orderBy('measured_at', 'asc')
            ->get();
    }

    /**
     * Evaluate if a record should be flagged based on vital sign type ranges.
     */
    protected function evaluateAndFlagRecord(VitalSignsRecord $record, VitalSignType $vitalSignType): void
    {
        $primaryValue = $record->value_primary;
        $isFlagged = false;
        $flagReason = null;

        // Check if value is outside critical limits
        if ($vitalSignType->isValueCritical($primaryValue)) {
            $isFlagged = true;
            $flagReason = 'Value outside acceptable limits';
        }
        // Check if value is outside normal range
        elseif (! $vitalSignType->isValueNormal($primaryValue)) {
            $isFlagged = true;
            $flagReason = 'Value outside normal range';
        }

        // Additional checks for secondary values (e.g., diastolic BP)
        if (! $isFlagged && $record->value_secondary !== null) {
            // For blood pressure, check diastolic value separately
            if ($vitalSignType->name === 'blood_pressure') {
                $diastolic = $record->value_secondary;
                if ($diastolic < 40 || $diastolic > 120) {
                    $isFlagged = true;
                    $flagReason = 'Diastolic pressure outside normal range';
                }
            }
        }

        $record->is_flagged = $isFlagged;
        $record->flag_reason = $flagReason;
    }

    /**
     * Bulk import vital signs records.
     */
    public function bulkImport(User $user, array $records): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($user, $records, &$results) {
            foreach ($records as $index => $recordData) {
                try {
                    $this->create($user, $recordData);
                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'index' => $index,
                        'data' => $recordData,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return $results;
    }

    /**
     * Get records that may need review or follow-up.
     */
    public function getRecordsNeedingReview(User $user): Collection
    {
        return $user->vitalSignsRecords()
            ->with(['vitalSignType'])
            ->where(function ($query) {
                $query->where('is_flagged', true)
                    ->orWhere('measured_at', '<', now()->subDays(1))
                    ->where('notes', 'like', '%unusual%')
                    ->orWhere('notes', 'like', '%concern%');
            })
            ->orderBy('measured_at', 'desc')
            ->limit(20)
            ->get();
    }
}
