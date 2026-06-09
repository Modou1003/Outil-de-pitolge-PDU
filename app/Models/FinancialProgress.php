<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialProgress extends Model
{
    use HasFactory;

    protected $table = 'financial_progresses';

    protected $fillable = [
        'pdu_project_id',
        'period',
        'measurement_date',
        'planned_value',
        'earned_value',
        'actual_cost',
        'cumulative_planned_value',
        'cumulative_earned_value',
        'cumulative_actual_cost',
        'observations',
        'recorded_by',
        'status',
    ];

    protected $casts = [
        'measurement_date' => 'date',
        'planned_value' => 'decimal:2',
        'earned_value' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'cumulative_planned_value' => 'decimal:2',
        'cumulative_earned_value' => 'decimal:2',
        'cumulative_actual_cost' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PduProject::class, 'pdu_project_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** CPI = EV / AC (>1 = sous le budget). */
    public function getCpiAttribute(): ?float
    {
        $ac = (float) $this->cumulative_actual_cost;
        if ($ac <= 0) return null;
        return round((float) $this->cumulative_earned_value / $ac, 3);
    }

    /** SPI = EV / PV (>1 = en avance). */
    public function getSpiAttribute(): ?float
    {
        $pv = (float) $this->cumulative_planned_value;
        if ($pv <= 0) return null;
        return round((float) $this->cumulative_earned_value / $pv, 3);
    }

    /** Cost Variance. */
    public function getCvAttribute(): float
    {
        return (float) $this->cumulative_earned_value - (float) $this->cumulative_actual_cost;
    }

    /** Schedule Variance. */
    public function getSvAttribute(): float
    {
        return (float) $this->cumulative_earned_value - (float) $this->cumulative_planned_value;
    }
}
