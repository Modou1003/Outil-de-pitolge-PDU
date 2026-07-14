<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdu_project_id',
        'code',
        'name',
        'description',
        'weight_percentage',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'progress_percentage',
        'status',
        'observations',
        'sort_order',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'weight_percentage' => 'decimal:2',
        'progress_percentage' => 'decimal:2',
    ];

    public const STATUSES = [
        'not_started' => 'Non démarré',
        'in_progress' => 'En cours',
        'on_hold' => 'Suspendu',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PduProject::class, 'pdu_project_id');
    }

    public function physicalProgresses(): HasMany
    {
        return $this->hasMany(PhysicalProgress::class);
    }
}
