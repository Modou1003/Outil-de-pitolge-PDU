<?php

namespace App\Models;

use App\Observers\PduProjectObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([PduProjectObserver::class])]
class PduProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pdu_projects';

    protected $fillable = [
        'title',
        'description',
        'code',
        'university_id',
        'created_by',
        'start_date',
        'end_date',
        'planned_completion_date',
        'status',
        'type',
        'progress_percentage',
        'budget_allocated',
        'budget_spent',
        'startup_advance_amount',
        'supply_advance_amount',
        'currency',
        'objectives',
        'stakeholders',
        'metadata',
        'director_id',
        'director_name',
        'director_email',
        'project_manager_id',
        'project_manager_name',
        'project_manager_email',
        'financial_agent_id',
        'financial_agent_name',
        'financial_agent_email',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'planned_completion_date' => 'date',
        'progress_percentage' => 'decimal:2',
        'budget_allocated' => 'decimal:2',
        'budget_spent' => 'decimal:2',
        'startup_advance_amount' => 'decimal:2',
        'supply_advance_amount' => 'decimal:2',
        'objectives' => 'array',
        'stakeholders' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the university that owns the project.
     */
    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    /**
     * Get the user who created the project.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the director of the project.
     */
    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    /**
     * Get the project manager.
     */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    /**
     * Get the financial agent.
     */
    public function financialAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'financial_agent_id');
    }

    /**
     * Get the indicator trackings for the project.
     */
    public function indicatorTrackings(): HasMany
    {
        return $this->hasMany(IndicatorTracking::class);
    }

    /**
     * Get the alerts for the project.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ProjectLot::class)->orderBy('sort_order');
    }

    public function buildingWorks(): HasMany
    {
        return $this->hasMany(BuildingWork::class)->orderBy('sort_order');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('planned_date');
    }

    public function physicalProgresses(): HasMany
    {
        return $this->hasMany(PhysicalProgress::class)->orderBy('measurement_date');
    }

    public function financialProgresses(): HasMany
    {
        return $this->hasMany(FinancialProgress::class)->orderBy('measurement_date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ProjectPayment::class)->orderBy('number');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ProjectAmendment::class)->orderBy('signed_date')->orderBy('id');
    }

    /**
     * Somme des avenants (positive = plus-value, négative = moins-value).
     */
    public function getAmendmentsTotalAttribute(): float
    {
        $sum = $this->relationLoaded('amendments')
            ? $this->amendments->sum('amount')
            : $this->amendments()->sum('amount');

        return round((float) $sum, 2);
    }

    /**
     * Montant du marché actualisé = montant initial + avenants.
     * Référence de tous les taux financiers ; budget_allocated reste le montant
     * initial, jamais modifié par un avenant.
     */
    public function getBudgetRevisedAttribute(): float
    {
        return round((float) $this->budget_allocated + $this->amendments_total, 2);
    }

    /**
     * Somme des prolongations de délai portées par les avenants, en jours
     * (positive = prolongation, négative = réduction).
     */
    public function getAmendmentsDurationDaysAttribute(): int
    {
        $sum = $this->relationLoaded('amendments')
            ? $this->amendments->sum('duration_days')
            : $this->amendments()->sum('duration_days');

        return (int) $sum;
    }

    /**
     * Synthèse financière « maître d'ouvrage » : facturation, reste à facturer,
     * décaissement et exposition sur les avances (démarrage + approvisionnement).
     */
    /**
     * Budget à l'achèvement du périmètre effectivement suivi.
     *
     * Les indices de performance portent sur les seuls ouvrages ; le coût final
     * estimé doit donc diviser leur enveloppe cumulée, et non le marché entier,
     * qui comprend des postes hors suivi physique. À défaut de montant
     * contractuel connu, l'ouvrage est valorisé au prorata de sa pondération.
     */
    public function evmBase(): float
    {
        $ouvrages = $this->relationLoaded('buildingWorks') ? $this->buildingWorks : $this->buildingWorks()->get();
        if ($ouvrages->isEmpty()) {
            return (float) $this->budget_revised;
        }

        $marche = (float) $this->budget_revised;
        $base = $ouvrages->sum(fn ($o) => (float) ($o->contract_amount ?: $marche * (float) $o->weight_percentage / 100));

        return $base > 0 ? round($base, 2) : $marche;
    }

    public function financialMoa(): array
    {
        // Le marché de référence est le montant actualisé (initial + avenants).
        $budget = $this->budget_revised;
        $payments = $this->payments;

        $invoiced = (float) $payments->sum('gross_amount');
        $netPaid = (float) $payments->sum('net_paid');

        $advanceGranted = (float) $this->startup_advance_amount + (float) $this->supply_advance_amount;
        $advanceRecovered = (float) $payments->sum('startup_advance_recovery') + (float) $payments->sum('supply_advance_recovery');
        $advanceRemaining = max(0.0, $advanceGranted - $advanceRecovered);

        $rate = fn (float $part) => $budget > 0 ? round($part / $budget * 100, 2) : null;

        // Une avance versée peut être enregistrée comme décompte — c'est l'usage
        // du tableau de facturation de la mission de contrôle. Elle figure alors
        // déjà dans les sommes versées : ne sont ajoutées que les avances qui
        // n'ont pas donné lieu à un décompte.
        $advanceInvoiced = (float) $payments->where('is_advance', true)->sum('net_paid');
        $encashed = $netPaid + max(0.0, $advanceGranted - $advanceInvoiced);

        return [
            'budget' => $budget,
            'budget_initial' => (float) $this->budget_allocated,
            'amendments_total' => $this->amendments_total,
            'amendments_count' => $this->relationLoaded('amendments')
                ? $this->amendments->count()
                : $this->amendments()->count(),
            'invoiced' => $invoiced,
            'invoice_rate' => $rate($invoiced),
            'remaining_to_invoice' => max(0.0, $budget - $invoiced),
            'remaining_to_invoice_rate' => $rate(max(0.0, $budget - $invoiced)),
            'net_paid' => $netPaid,
            'encashed' => $encashed,
            'encashment_rate' => $rate($encashed),
            'advance_granted' => $advanceGranted,
            'advance_recovered' => $advanceRecovered,
            'advance_remaining' => $advanceRemaining,
            'advance_recovery_rate' => $advanceGranted > 0 ? round($advanceRecovered / $advanceGranted * 100, 2) : null,
            'advance_remaining_rate' => $advanceGranted > 0 ? round($advanceRemaining / $advanceGranted * 100, 2) : null,
            'payments_count' => $payments->count(),
        ];
    }

    /**
     * Get the reports for the project.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get the documents for the project.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(ProjectTeamMember::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the comments for the project.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'commentable_id')->where('commentable_type', self::class);
    }

    /**
     * Scope a query to only include active projects.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['in_progress', 'approved']);
    }

    /**
     * Scope a query to only include completed projects.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Get the remaining budget (sur le montant actualisé).
     */
    public function getRemainingBudgetAttribute(): float
    {
        return round($this->budget_revised - (float) $this->budget_spent, 2);
    }

    /**
     * Get the budget execution rate (sur le montant actualisé).
     */
    public function getBudgetExecutionRateAttribute(): float
    {
        $budget = $this->budget_revised;
        if ($budget <= 0) {
            return 0;
        }
        return round(((float) $this->budget_spent / $budget) * 100, 2);
    }

    /**
     * Check if the project is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        $end = $this->planned_end_date_revised;

        return $end && $end->isPast() && $this->status !== 'completed';
    }

    /**
     * Date de fin contractuelle initiale : la date de livraison prévue si
     * renseignée, sinon la date de fin. Jamais décalée par un avenant.
     */
    public function getPlannedEndDateAttribute()
    {
        return $this->planned_completion_date ?: $this->end_date;
    }

    /**
     * Date de fin actualisée = date de fin initiale + prolongations des avenants.
     * Référence de tous les indicateurs de délai ; la date initiale reste intacte.
     */
    public function getPlannedEndDateRevisedAttribute()
    {
        $base = $this->planned_end_date;
        if (! $base) {
            return null;
        }

        $days = $this->amendments_duration_days;

        return $days === 0 ? $base->copy() : $base->copy()->addDays($days);
    }

    /**
     * Get the planned progress based on elapsed time between start_date and planned end date.
     */
    public function getPlannedProgressAttribute(): float
    {
        // L'avancement attendu se mesure sur le calendrier actualisé.
        $plannedEnd = $this->planned_end_date_revised;
        if (! $this->start_date || ! $plannedEnd) {
            return 0;
        }

        $total = $this->start_date->diffInDays($plannedEnd);
        if ($total <= 0) {
            return 100;
        }

        $elapsed = $this->start_date->diffInDays(now());
        $planned = ($elapsed / $total) * 100;

        return (float) max(0, min(100, round($planned, 2)));
    }
}