<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalProgress extends Model
{
    use HasFactory;

    protected $table = 'physical_progresses';

    protected $fillable = [
        'pdu_project_id',
        'project_lot_id',
        'period',
        'measurement_date',
        'planned_percentage',
        'actual_percentage',
        'observations',
        'recorded_by',
        'status',
    ];

    protected $casts = [
        'measurement_date' => 'date',
        'planned_percentage' => 'decimal:2',
        'actual_percentage' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PduProject::class, 'pdu_project_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProjectLot::class, 'project_lot_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getVarianceAttribute(): float
    {
        return (float) $this->actual_percentage - (float) $this->planned_percentage;
    }
}
