<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Recommendation extends Model
{
    use HasFactory;

    protected $table = 'recommendations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'vital_signs_record_id',
        'recommendation_type',
        'title',
        'message',
        'severity',
        'action_required',
        'read_at',
        'dismissed_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'action_required' => 'boolean',
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
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
     * Get the user that owns this recommendation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vital signs record that triggered this recommendation.
     */
    public function vitalSignsRecord(): BelongsTo
    {
        return $this->belongsTo(VitalSignsRecord::class);
    }

    /**
     * Check if the recommendation has been read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Check if the recommendation has been dismissed.
     */
    public function isDismissed(): bool
    {
        return $this->dismissed_at !== null;
    }

    /**
     * Check if the recommendation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Mark the recommendation as read.
     */
    public function markAsRead(): bool
    {
        if ($this->isRead()) {
            return true;
        }

        $this->read_at = now();

        return $this->save();
    }
}
