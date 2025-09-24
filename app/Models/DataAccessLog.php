<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DataAccessLog extends Model
{
    use HasFactory;

    protected $table = 'data_access_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'medical_professional_id',
        'user_id',
        'accessed_at',
        'access_type',
        'data_scope',
        'ip_address',
        'user_agent',
        'session_duration',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
            'data_scope' => 'array',
            'session_duration' => 'integer',
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
     * Get the medical professional that accessed the data.
     */
    public function medicalProfessional(): BelongsTo
    {
        return $this->belongsTo(MedicalProfessional::class);
    }

    /**
     * Get the user whose data was accessed.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the formatted session duration.
     */
    public function getFormattedDuration(): string
    {
        if ($this->session_duration === null) {
            return 'Unknown';
        }

        $hours = floor($this->session_duration / 3600);
        $minutes = floor(($this->session_duration % 3600) / 60);
        $seconds = $this->session_duration % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }

    /**
     * Get a summary of the access.
     */
    public function getAccessSummary(): string
    {
        $scope = is_array($this->data_scope) ? implode(', ', $this->data_scope) : 'All data';

        return sprintf(
            '%s accessed %s for %s on %s',
            $this->medicalProfessional->name ?? 'Unknown',
            $scope,
            $this->user->name ?? 'Unknown Patient',
            $this->accessed_at->format('M j, Y \a\t g:i A')
        );
    }
}
