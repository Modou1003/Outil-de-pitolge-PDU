<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildingWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdu_project_id',
        'code',
        'name',
        'description',
        'budget',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'status',
        'progress_percentage',
        'observations',
        'sort_order',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'budget' => 'decimal:2',
        'progress_percentage' => 'decimal:2',
    ];

    public const STATUSES = [
        'not_started' => 'Non démarré',
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
        return $this->hasMany(ProjectLot::class, 'building_work_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
