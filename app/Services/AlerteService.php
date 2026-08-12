<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\PduProject;
use App\Models\User;
use App\Notifications\AlertDetectedNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class AlerteService
{
    public function __construct(
        protected EarnedScheduleService $earnedSchedule,
        protected ThresholdService $seuils,
    ) {}

    // Les seuils sont portés par ThresholdService : catalogue, valeurs par
    // défaut et bornes admissibles y font autorité.

    public function generateForAll(): array
    {
        $summary = ['created' => 0, 'closed' => 0, 'scanned' => 0];

        PduProject::query()
            ->with(['physicalProgresses', 'financialProgresses', 'buildingWorks', 'lots', 'milestones', 'amendments', 'payments'])
            ->chunk(50, function (Collection $projects) use (&$summary) {
                foreach ($projects as $project) {
                    $summary['scanned']++;
                    $res = $this->generateForProject($project);
                    $summary['created'] += $res['created'];
                    $summary['closed'] += $res['closed'];
                }
            });

        return $summary;
    }

    public function generateForProject(PduProject $project): array
    {
        $created = 0;
        $closed = 0;

        $active = [];

        if ($this->detectDelay($project)) $active[] = 'delay';
        if ($this->detectBudgetOverrun($project)) $active[] = 'budget_overrun';
        if ($this->detectProgressGap($project)) $active[] = 'progress_gap';
        if ($this->detectNoUpdate($project)) $active[] = 'no_update';
        if ($this->detectMilestoneMissed($project)) $active[] = 'milestone_missed';
        if ($this->detectPhysicalFinancialGap($project)) $active[] = 'physical_financial_gap';
        if ($this->detectForecastDelay($project)) $active[] = 'forecast_delay';
        if ($this->detectCostDrift($project)) $active[] = 'cost_drift';
        if ($this->detectAmendmentsExcess($project)) $active[] = 'amendments_excess';
        if ($this->detectPaymentPending($project)) $active[] = 'payment_pending';

        foreach ($active as $type) {
            if ($this->upsertOpen($project, $type)) {
                $created++;
            }
        }

        // Ferme les alertes ouvertes dont le critère n'est plus valide
        $closed = Alert::where('pdu_project_id', $project->id)
            ->where('is_resolved', false)
            ->whereNotIn('type', $active)
            ->update([
                'is_resolved' => true,
                'resolved_at' => now(),
                'resolution_note' => 'Auto-fermée : la condition n\'est plus remplie.',
            ]);

        return ['created' => $created, 'closed' => $closed];
    }

    protected function upsertOpen(PduProject $project, string $type): bool
    {
        $payload = $this->buildPayload($project, $type);

        $existing = Alert::where('pdu_project_id', $project->id)
            ->where('type', $type)
            ->where('is_resolved', false)
            ->first();

        // Alerte déjà ouverte : on rafraîchit sa sévérité/message pour qu'ils
        // restent exacts (ex. la donnée passe de « à rafraîchir » à « périmée »).
        if ($existing) {
            $existing->update($payload);
            return false;
        }

        $alert = Alert::create(array_merge([
            'pdu_project_id' => $project->id,
            'type' => $type,
            'detected_at' => now(),
        ], $payload));

        $this->notifyDataActors($alert);

        return true;
    }

    protected function buildPayload(PduProject $project, string $type): array
    {
        return match ($type) {
            'delay' => [
                'severity' => 'critical',
                'title' => 'Retard de livraison',
                'message' => sprintf(
                    'Date de fin réelle (%s) supérieure à la date de fin prévue (%s).',
                    $this->resolveActualEndDate($project)?->toDateString(),
                    $this->resolvePlannedEndDate($project)?->toDateString(),
                ),
                'context' => [
                    'actual_end_date' => $this->resolveActualEndDate($project)?->toDateString(),
                    'planned_end_date' => $this->resolvePlannedEndDate($project)?->toDateString(),
                ],
            ],
            'budget_overrun' => [
                'severity' => 'warning',
                'title' => 'Dépassement budgétaire imminent',
                'message' => sprintf(
                    'Montant décaissé %.2f > %.0f%% du marché actualisé (%.2f).',
                    (float) $project->budget_spent,
                    $this->seuils->get('budget_overrun_rate'),
                    $project->budget_revised * $this->seuils->ratio('budget_overrun_rate'),
                ),
                'context' => [
                    'rate' => (float) $project->budget_execution_rate,
                    'spent' => (float) $project->budget_spent,
                    'allocated' => (float) $project->budget_allocated,
                    'revised' => $project->budget_revised,
                    'amendments_total' => $project->amendments_total,
                    'threshold_rate' => $this->seuils->ratio('budget_overrun_rate') * 100,
                ],
            ],
            'progress_gap' => [
                'severity' => 'critical',
                'title' => 'Écart d’avancement critique',
                'message' => sprintf(
                    'Écart réel-prévu de %.1f pts (seuil %.1f pts).',
                    (float) $project->progress_percentage - (float) $project->planned_progress,
                    (-1 * $this->seuils->get('progress_gap_points')),
                ),
                'context' => [
                    'actual' => (float) $project->progress_percentage,
                    'planned' => (float) $project->planned_progress,
                    'gap' => (float) $project->progress_percentage - (float) $project->planned_progress,
                ],
            ],
            'no_update' => $this->noUpdatePayload($project),
            'cost_drift' => $this->costDriftPayload($project),
            'amendments_excess' => $this->amendmentsExcessPayload($project),
            'payment_pending' => $this->paymentPendingPayload($project),
            'physical_financial_gap' => $this->physicalFinancialPayload($project),
            'forecast_delay' => $this->forecastDelayPayload($project),
            'milestone_missed' => [
                'severity' => 'critical',
                'title' => 'Jalon dépassé',
                'message' => 'Au moins un jalon a dépassé sa date prévue sans être atteint.',
                'context' => [],
            ],
            default => ['severity' => 'warning', 'title' => 'Alerte', 'message' => '', 'context' => null],
        };
    }

    protected function detectDelay(PduProject $project): bool
    {
        $plannedEnd = $this->resolvePlannedEndDate($project);
        $actualEnd = $this->resolveActualEndDate($project);

        if (! $plannedEnd || ! $actualEnd) {
            return false;
        }

        return $actualEnd->greaterThan($plannedEnd);
    }

    protected function detectBudgetOverrun(PduProject $project): bool
    {
        $budget = $project->budget_revised;
        if ($budget <= 0) return false;
        return ((float) $project->budget_spent / $budget) >= $this->seuils->ratio('budget_overrun_rate');
    }

    protected function detectProgressGap(PduProject $project): bool
    {
        $gap = (float) $project->progress_percentage - (float) $project->planned_progress;
        return $gap < (-1 * $this->seuils->get('progress_gap_points'));
    }

    protected function detectNoUpdate(PduProject $project): bool
    {
        // Non pertinent pour les projets sans travaux de terrain en cours.
        if (in_array($project->status, ['draft', 'completed', 'cancelled', 'archived'], true)) {
            return false;
        }

        $days = $this->daysSinceLastPhysical($project);

        // Alerte si aucune saisie OU dernière saisie plus ancienne que le seuil.
        return $days === null || $days > $this->seuils->days('stale_data_days');
    }

    /**
     * Nombre de jours depuis la dernière saisie d'avancement physique
     * (null si aucune donnée n'a jamais été saisie).
     */
    protected function daysSinceLastPhysical(PduProject $project): ?int
    {
        $last = $project->physicalProgresses
            ->pluck('measurement_date')
            ->filter()
            ->max();

        return $last
            ? (int) $last->copy()->startOfDay()->diffInDays(now()->startOfDay())
            : null;
    }

    /**
     * Charge utile graduée de l'alerte « donnée de terrain » selon son ancienneté.
     */
    protected function noUpdatePayload(PduProject $project): array
    {
        $days = $this->daysSinceLastPhysical($project);

        if ($days === null) {
            return [
                'severity' => 'warning',
                'title' => 'Aucune donnée de terrain',
                'message' => 'Aucun avancement physique n\'a jamais été saisi : les indicateurs (SPI, CPI, avancement) ne reposent sur aucune réalité mesurée.',
                'context' => ['days_since' => null],
            ];
        }

        $critical = $days > $this->seuils->days('critical_data_days');

        return [
            'severity' => $critical ? 'critical' : 'warning',
            'title' => $critical ? 'Donnée de terrain périmée' : 'Donnée de terrain à rafraîchir',
            'message' => sprintf(
                'Dernière saisie d\'avancement physique il y a %d jours (seuil %d j) — les indicateurs risquent de ne plus refléter la réalité du terrain.',
                $days,
                $this->seuils->days('stale_data_days'),
            ),
            'context' => ['days_since' => $days, 'threshold' => $this->seuils->days('stale_data_days')],
        ];
    }

    protected function detectPhysicalFinancialGap(PduProject $project): bool
    {
        // Non pertinent pour les projets sans exécution en cours.
        if (in_array($project->status, ['draft', 'completed', 'cancelled', 'archived'], true)) {
            return false;
        }
        if ($project->budget_revised <= 0) {
            return false;
        }

        $physical = (float) $project->progress_percentage;
        $financial = (float) $project->budget_execution_rate;

        // Alerte uniquement quand le décaissement dépasse la réalisation physique
        // (risque de façade / surfacturation), au-delà du seuil.
        return ($financial - $physical) > $this->seuils->get('phys_fin_gap_points');
    }

    protected function physicalFinancialPayload(PduProject $project): array
    {
        $physical = round((float) $project->progress_percentage, 1);
        $financial = round((float) $project->budget_execution_rate, 1);
        $gap = round($financial - $physical, 1);
        $critical = $gap > $this->seuils->get('phys_fin_gap_critical');

        return [
            'severity' => $critical ? 'critical' : 'warning',
            'title' => $critical ? 'Effet de façade critique' : 'Décaissement en avance sur la réalisation',
            'message' => sprintf(
                'Le décaissement (%.1f%%) dépasse l\'avancement physique (%.1f%%) de %.1f points — risque de surfacturation ou d\'avances non justifiées.',
                $financial,
                $physical,
                $gap,
            ),
            'context' => [
                'physical' => $physical,
                'financial' => $financial,
                'gap' => $gap,
                'threshold' => $this->seuils->get('phys_fin_gap_points'),
            ],
        ];
    }

    protected function detectForecastDelay(PduProject $project): bool
    {
        if (in_array($project->status, ['draft', 'completed', 'cancelled', 'archived'], true)) {
            return false;
        }

        $delay = $this->projectedDelayDays($project);

        return $delay !== null && $delay > $this->seuils->days('forecast_delay_days');
    }

    protected function forecastDelayPayload(PduProject $project): array
    {
        $delay = $this->projectedDelayDays($project) ?? 0;
        $critical = $delay > $this->seuils->days('forecast_delay_critical');

        return [
            'severity' => $critical ? 'critical' : 'warning',
            'title' => $critical ? 'Dérive de planning majeure' : 'Retard de livraison projeté',
            'message' => sprintf(
                'Au rythme d\'exécution constaté par rapport à la courbe planifiée, la livraison accuserait un retard d\'environ %d jours sur l\'échéance contractuelle.',
                $delay,
            ),
            'context' => ['delay_days' => $delay, 'threshold' => $this->seuils->days('forecast_delay_days')],
        ];
    }

    /**
     * Retard de livraison projeté (en jours) par la méthode Earned Schedule ;
     * null si la projection n'est pas calculable.
     */
    protected function projectedDelayDays(PduProject $project): ?int
    {
        return $this->earnedSchedule->projectedDelayDays($project);
    }

    /**
     * Indice de performance des coûts (CPI) = valeur acquise ÷ coût réel,
     * cumulés sur tous les relevés du projet. Null tant qu'aucun coût réel
     * n'a été saisi : la division n'aurait pas de sens.
     */
    protected function costPerformanceIndex(PduProject $project): ?float
    {
        $earned = (float) $project->financialProgresses->sum('earned_value');
        $actual = (float) $project->financialProgresses->sum('actual_cost');

        return $actual > 0 ? round($earned / $actual, 3) : null;
    }

    protected function detectCostDrift(PduProject $project): bool
    {
        if (in_array($project->status, ['draft', 'cancelled', 'archived'], true)) {
            return false;
        }

        $cpi = $this->costPerformanceIndex($project);

        return $cpi !== null && $cpi < $this->seuils->get('cpi_threshold');
    }

    protected function costDriftPayload(PduProject $project): array
    {
        $cpi = $this->costPerformanceIndex($project) ?? 0.0;

        return [
            'severity' => 'warning',
            'title' => 'Dérive de coût',
            'message' => sprintf(
                'Indice de performance des coûts de %.2f (seuil %.2f) : le projet consomme plus de budget qu\'il ne produit de valeur.',
                $cpi,
                $this->seuils->get('cpi_threshold'),
            ),
            'context' => ['cpi' => $cpi, 'threshold' => $this->seuils->get('cpi_threshold')],
        ];
    }

    /**
     * Part cumulée des avenants rapportée au montant initial du marché.
     * On raisonne sur la plus-value nette : une moins-value ne constitue pas
     * une dérive au sens de la réglementation des marchés.
     */
    protected function amendmentsRatio(PduProject $project): ?float
    {
        $initial = (float) $project->budget_allocated;
        if ($initial <= 0) {
            return null;
        }

        return round($project->amendments_total / $initial, 4);
    }

    protected function detectAmendmentsExcess(PduProject $project): bool
    {
        $ratio = $this->amendmentsRatio($project);

        return $ratio !== null && $ratio > $this->seuils->ratio('amendments_ratio');
    }

    protected function amendmentsExcessPayload(PduProject $project): array
    {
        $ratio = $this->amendmentsRatio($project) ?? 0.0;

        return [
            'severity' => 'warning',
            'title' => 'Avenants cumulés excessifs',
            'message' => sprintf(
                'Les avenants représentent %.1f%% du montant initial du marché (seuil %.0f%%) : l\'économie du contrat est substantiellement modifiée.',
                $ratio * 100,
                $this->seuils->ratio('amendments_ratio') * 100,
            ),
            'context' => [
                'ratio' => round($ratio * 100, 1),
                'threshold' => $this->seuils->ratio('amendments_ratio') * 100,
                'initial' => (float) $project->budget_allocated,
                'amendments_total' => $project->amendments_total,
                'revised' => $project->budget_revised,
            ],
        ];
    }

    /**
     * Décomptes transmis mais non réglés depuis plus longtemps que le seuil.
     * La date de référence est celle du décompte, à défaut sa date de saisie.
     */
    protected function pendingPayments(PduProject $project)
    {
        $limit = now()->startOfDay()->subDays($this->seuils->days('payment_pending_days'));

        return $project->payments
            ->where('is_paid', false)
            ->filter(function ($payment) use ($limit) {
                $reference = $payment->payment_date ?: $payment->created_at;

                return $reference && $reference->lessThan($limit);
            });
    }

    protected function detectPaymentPending(PduProject $project): bool
    {
        return $this->pendingPayments($project)->isNotEmpty();
    }

    protected function paymentPendingPayload(PduProject $project): array
    {
        $pending = $this->pendingPayments($project);
        $oldest = $pending
            ->map(fn ($p) => $p->payment_date ?: $p->created_at)
            ->filter()
            ->min();

        $days = $oldest ? (int) $oldest->copy()->startOfDay()->diffInDays(now()->startOfDay()) : 0;

        return [
            'severity' => 'info',
            'title' => 'Décompte en attente de règlement',
            'message' => sprintf(
                '%d décompte(s) transmis et non réglé(s), le plus ancien depuis %d jours (seuil %d j).',
                $pending->count(),
                $days,
                $this->seuils->days('payment_pending_days'),
            ),
            'context' => [
                'count' => $pending->count(),
                'oldest_days' => $days,
                'threshold' => $this->seuils->days('payment_pending_days'),
            ],
        ];
    }

    protected function detectMilestoneMissed(PduProject $project): bool
    {
        return $project->milestones->contains(function ($milestone) {
            if (! $milestone->planned_date) {
                return false;
            }
            return $milestone->planned_date->lt(now()->startOfDay()) && $milestone->status !== 'reached';
        });
    }

    protected function resolvePlannedEndDate(PduProject $project): ?Carbon
    {
        // Date contractuelle actualisée : un avenant de délai repousse l'échéance
        // au-delà de laquelle le projet est considéré en retard.
        $revised = $project->planned_end_date_revised;

        return $revised ? Carbon::parse($revised) : null;
    }

    protected function resolveActualEndDate(PduProject $project): ?Carbon
    {
        $lotActualEnd = $project->lots->whereNotNull('actual_end_date')->max('actual_end_date');
        $physicalCompletion = $project->physicalProgresses
            ->where('actual_percentage', '>=', 100)
            ->max('measurement_date');

        $candidate = $lotActualEnd ?: $physicalCompletion;
        if (! $candidate) {
            return null;
        }

        return Carbon::parse($candidate);
    }

    protected function notifyDataActors(Alert $alert): void
    {
        $project = $alert->project()->first([
            'id',
            'created_by',
            'director_id',
            'project_manager_id',
            'financial_agent_id',
            'director_email',
            'project_manager_email',
            'financial_agent_email',
        ]);

        if (! $project) {
            return;
        }

        $principalMemberIds = collect([
            $project->created_by,
            $project->director_id,
            $project->project_manager_id,
            $project->financial_agent_id,
        ])->filter()->unique()->values();

        $supervisorIds = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'directeur']))
            ->pluck('id');

        $recipientIds = $principalMemberIds->merge($supervisorIds)->unique()->values();

        $users = User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->when($recipientIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $recipientIds))
            ->get();

        $notification = new AlertDetectedNotification($alert->loadMissing('project:id,code,title'));

        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }

        $userEmails = $users->pluck('email')->filter()->map(fn ($e) => mb_strtolower(trim((string) $e)));
        $externalEmails = collect([
            $project->director_email,
            $project->project_manager_email,
            $project->financial_agent_email,
        ])
            ->filter()
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->reject(fn ($e) => $userEmails->contains($e))
            ->unique()
            ->values();

        foreach ($externalEmails as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }
}
