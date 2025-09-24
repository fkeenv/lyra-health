<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VitalSignsRecord extends Model
{
    use HasFactory;

    protected $table = 'vital_signs_records';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'vital_sign_type_id',
        'value_primary',
        'value_secondary',
        'unit',
        'measured_at',
        'notes',
        'measurement_method',
        'device_name',
        'is_flagged',
        'flag_reason',
    ];

    protected function casts(): array
    {
        return [
            'value_primary' => 'decimal:2',
            'value_secondary' => 'decimal:2',
            'measured_at' => 'datetime',
            'is_flagged' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the user that owns this vital signs record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vital sign type for this record.
     */
    public function vitalSignType(): BelongsTo
    {
        return $this->belongsTo(VitalSignType::class);
    }

    /**
     * Get the recommendations associated with this vital signs record.
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    /**
     * Check if the vital signs reading is within normal range.
     */
    public function isNormal(): bool
    {
        return $this->vitalSignType->isValueNormal($this->value_primary);
    }

    /**
     * Check if the vital signs reading is flagged.
     */
    public function isFlagged(): bool
    {
        return $this->is_flagged;
    }

    /**
     * Get a formatted display value for the reading.
     */
    public function getDisplayValue(): string
    {
        $value = $this->value_primary;

        if ($this->value_secondary !== null) {
            $value .= '/'.$this->value_secondary;
        }

        return $value.' '.$this->unit;
    }
}
