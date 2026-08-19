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
        'weight_percentage',
        'contract_amount',
        'duration_days',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'sort_order',
    ];

    protected $casts = [
        'weight_percentage' => 'decimal:2',
        'duration_days' => 'integer',
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
    ];

    /**
     * Retard au démarrage, en jours : écart entre le début réellement constaté
     * et le début prévu au planning contractuel.
     *
     * Tant que l'ouvrage n'a pas démarré, le retard se mesure par rapport à
     * aujourd'hui : un ouvrage qui devait commencer il y a six mois accuse
     * six mois de retard, et non zéro faute de date de début.
     * Null si le planning ne prévoit pas de date de début.
     */
    public function getStartDelayDaysAttribute(): ?int
    {
        if (! $this->planned_start_date) {
            return null;
        }

        $reference = $this->actual_start_date ?: now();
        $delay = (int) $this->planned_start_date->copy()->startOfDay()
            ->diffInDays($reference->copy()->startOfDay(), false);

        // Un démarrage anticipé n'est pas un retard.
        return max(0, $delay);
    }

    /** L'ouvrage a-t-il dépassé sa date de démarrage sans avoir commencé ? */
    public function getIsStartOverdueAttribute(): bool
    {
        return $this->planned_start_date
            && ! $this->actual_start_date
            && $this->planned_start_date->isPast()
            && ! in_array($this->status, ['completed', 'cancelled'], true);
    }

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

    public function physicalProgresses(): HasMany
    {
        return $this->hasMany(PhysicalProgress::class, 'building_work_id');
    }

    public function financialProgresses(): HasMany
    {
        return $this->hasMany(FinancialProgress::class, 'building_work_id');
    }

    /** Avancement physique de l'ouvrage = dernière valeur réelle saisie. */
    public function getProgressPercentageAttribute(): float
    {
        $last = $this->physicalProgresses
            ->sortBy([['measurement_date', 'desc'], ['id', 'desc']])
            ->first();

        return $last ? round((float) $last->actual_percentage, 2) : 0.0;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
