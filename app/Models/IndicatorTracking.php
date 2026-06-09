<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorTracking extends Model
{
    use HasFactory;

    protected $table = 'indicator_trackings';

    protected $fillable = [
        'indicator_id',
        'pdu_project_id',
        'recorded_by',
        'measurement_date',
        'period',
        'actual_value',
        'target_value',
        'previous_value',
        'status',
        'comments',
        'validation_notes',
        'validated_by',
        'validated_at',
        'data_sources',
        'attachments',
        'metadata',
    ];

    protected $casts = [
        'measurement_date' => 'date',
        'validated_at' => 'datetime',
        'actual_value' => 'decimal:4',
        'target_value' => 'decimal:4',
        'previous_value' => 'decimal:4',
        'data_sources' => 'array',
        'attachments' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the indicator that owns the tracking.
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    /**
     * Get the project that owns the tracking.
     */
    public function pduProject(): BelongsTo
    {
        return $this->belongsTo(PduProject::class);
    }

    /**
     * Get the user who recorded the tracking.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the user who validated the tracking.
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Scope a query to only include validated trackings.
     */
    public function scopeValidated($query)
    {
        return $query->where('status', 'validated');
    }

    /**
     * Scope a query to only include pending trackings.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope a query to filter by period.
     */
    public function scopeByPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    /**
     * Get the target achievement percentage.
     */
    public function getTargetAchievementAttribute(): ?float
    {
        if (!$this->actual_value || !$this->target_value) {
            return null;
        }

        return round(($this->actual_value / $this->target_value) * 100, 2);
    }

    /**
     * Get the progress from previous value.
     */
    public function getProgressFromPreviousAttribute(): ?float
    {
        if (!$this->actual_value || !$this->previous_value) {
            return null;
        }

        if ($this->previous_value == 0) {
            return null;
        }

        return round((($this->actual_value - $this->previous_value) / $this->previous_value) * 100, 2);
    }

    /**
     * Check if the tracking is within acceptable range.
     */
    public function isWithinRange(): bool
    {
        if (!$this->indicator) {
            return true;
        }

        $indicator = $this->indicator;

        if ($indicator->minimum_value !== null && $this->actual_value < $indicator->minimum_value) {
            return false;
        }

        if ($indicator->maximum_value !== null && $this->actual_value > $indicator->maximum_value) {
            return false;
        }

        return true;
    }
}