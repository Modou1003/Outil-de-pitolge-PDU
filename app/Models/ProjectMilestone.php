<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdu_project_id',
        'building_work_id',
        'project_lot_id',
        'name',
        'description',
        'planned_date',
        'actual_date',
        'status',
        'is_critical',
        'observations',
        'sort_order',
    ];

    protected $casts = [
        'planned_date' => 'date',
        'actual_date' => 'date',
        'is_critical' => 'boolean',
    ];

    public const STATUSES = [
        'pending' => 'En attente',
        'reached' => 'Atteint',
        'missed' => 'Manqué',
        'cancelled' => 'Annulé',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PduProject::class, 'pdu_project_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(BuildingWork::class, 'building_work_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProjectLot::class, 'project_lot_id');
    }

    public function getIsLateAttribute(): bool
    {
        if ($this->status === 'reached' || $this->status === 'cancelled') return false;
        return $this->planned_date && $this->planned_date->isPast();
    }
}
