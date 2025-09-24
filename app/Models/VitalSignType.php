<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VitalSignType extends Model
{
    use HasFactory;

    protected $table = 'vital_sign_types';

    protected $fillable = [
        'name',
        'display_name',
        'unit_primary',
        'unit_secondary',
        'min_value',
        'max_value',
        'normal_range_min',
        'normal_range_max',
        'warning_range_min',
        'warning_range_max',
        'has_secondary_value',
        'input_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
            'normal_range_min' => 'decimal:2',
            'normal_range_max' => 'decimal:2',
            'warning_range_min' => 'decimal:2',
            'warning_range_max' => 'decimal:2',
            'has_secondary_value' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the vital signs records for this type.
     */
    public function vitalSignsRecords(): HasMany
    {
        return $this->hasMany(VitalSignsRecord::class);
    }

    /**
     * Check if a value is within normal range.
     */
    public function isValueNormal(float $value): bool
    {
        if ($this->normal_range_min === null || $this->normal_range_max === null) {
            return true;
        }

        return $value >= $this->normal_range_min && $value <= $this->normal_range_max;
    }

    /**
     * Check if a value is within warning range.
     */
    public function isValueInWarningRange(float $value): bool
    {
        if ($this->warning_range_min === null || $this->warning_range_max === null) {
            return false;
        }

        return $value >= $this->warning_range_min && $value <= $this->warning_range_max;
    }

    /**
     * Check if a value is outside acceptable limits.
     */
    public function isValueCritical(float $value): bool
    {
        if ($this->min_value !== null && $value < $this->min_value) {
            return true;
        }

        if ($this->max_value !== null && $value > $this->max_value) {
            return true;
        }

        return false;
    }

    /**
     * Get the appropriate unit for display.
     */
    public function getDisplayUnit(): string
    {
        return $this->unit_primary;
    }
}
