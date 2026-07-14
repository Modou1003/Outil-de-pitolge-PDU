<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildingWork extends Model
{
    protected $fillable = [
        'pdu_project_id',
        'code',
        'name',
        'description',
        'status',
        'sort_order',
    ];

    public const STATUSES = [
        'not_started' => 'Non commencé',
        'in_progress' => 'En cours',
        'on_hold' => 'En pause',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PduProject::class, 'pdu_project_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ProjectLot::class, 'building_work_id')->orderBy('sort_order');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class, 'building_work_id')->orderBy('sort_order');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
