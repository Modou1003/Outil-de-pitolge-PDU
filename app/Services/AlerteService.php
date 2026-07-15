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
    /** Seuil de dépassement budgétaire (budget_spent / budget_allocated). */
    public const BUDGET_THRESHOLD = 0.9;

    /** Seuil d'écart d'avancement (réel - prévu). */
    public const PROGRESS_GAP_THRESHOLD = -15.0;

    /** Seuil (en points) de décaissement en avance sur l'avancement physique. */
    public const PHYS_FIN_GAP_THRESHOLD = 20.0;

    /** Seuil (en points) au-delà duquel le décalage physico-financier est critique. */
    public const PHYS_FIN_GAP_CRITICAL = 35.0;

    public function generateForAll(): array
    {
        $summary = ['created' => 0, 'closed' => 0, 'scanned' => 0];

        PduProject::query()
            ->with(['physicalProgresses', 'lots', 'milestones'])
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
                    'Montant décaissé %.2f > 90%% du budget total (%.2f).',
                    (float) $project->budget_spent,
                    (float) $project->budget_allocated * self::BUDGET_THRESHOLD,
                ),
                'context' => [
                    'rate' => (float) $project->budget_execution_rate,
                    'spent' => (float) $project->budget_spent,
                    'allocated' => (float) $project->budget_allocated,
                    'threshold_rate' => self::BUDGET_THRESHOLD * 100,
                ],
            ],
            'progress_gap' => [
                'severity' => 'critical',
                'title' => 'Écart d’avancement critique',
                'message' => sprintf(
                    'Écart réel-prévu de %.1f pts (seuil %.1f pts).',
                    (float) $project->progress_percentage - (float) $project->planned_progress,
                    self::PROGRESS_GAP_THRESHOLD,
                ),
                'context' => [
                    'actual' => (float) $project->progress_percentage,
                    'planned' => (float) $project->planned_progress,
                    'gap' => (float) $project->progress_percentage - (float) $project->planned_progress,
                ],
            ],
            'no_update' => $this->noUpdatePayload($project),
            'physical_financial_gap' => $this->physicalFinancialPayload($project),
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
        if (! $project->budget_allocated || $project->budget_allocated <= 0) return false;
        return ($project->budget_spent / $project->budget_allocated) >= self::BUDGET_THRESHOLD;
    }

    protected function detectProgressGap(PduProject $project): bool
    {
        $gap = (float) $project->progress_percentage - (float) $project->planned_progress;
        return $gap < self::PROGRESS_GAP_THRESHOLD;
    }

    /**
     * Seuil (en jours) au-delà duquel la donnée de terrain est jugée périmée.
     */
    public const STALE_DATA_DAYS = 30;
    public const CRITICAL_DATA_DAYS = 60;

    protected function detectNoUpdate(PduProject $project): bool
    {
        // Non pertinent pour les projets sans travaux de terrain en cours.
        if (in_array($project->status, ['draft', 'completed', 'cancelled', 'archived'], true)) {
            return false;
        }

        $days = $this->daysSinceLastPhysical($project);

        // Alerte si aucune saisie OU dernière saisie plus ancienne que le seuil.
        return $days === null || $days > self::STALE_DATA_DAYS;
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

        $critical = $days > self::CRITICAL_DATA_DAYS;

        return [
            'severity' => $critical ? 'critical' : 'warning',
            'title' => $critical ? 'Donnée de terrain périmée' : 'Donnée de terrain à rafraîchir',
            'message' => sprintf(
                'Dernière saisie d\'avancement physique il y a %d jours (seuil %d j) — les indicateurs risquent de ne plus refléter la réalité du terrain.',
                $days,
                self::STALE_DATA_DAYS,
            ),
            'context' => ['days_since' => $days, 'threshold' => self::STALE_DATA_DAYS],
        ];
    }

    protected function detectPhysicalFinancialGap(PduProject $project): bool
    {
        // Non pertinent pour les projets sans exécution en cours.
        if (in_array($project->status, ['draft', 'completed', 'cancelled', 'archived'], true)) {
            return false;
        }
        if (! $project->budget_allocated || $project->budget_allocated <= 0) {
            return false;
        }

        $physical = (float) $project->progress_percentage;
        $financial = (float) $project->budget_execution_rate;

        // Alerte uniquement quand le décaissement dépasse la réalisation physique
        // (risque de façade / surfacturation), au-delà du seuil.
        return ($financial - $physical) > self::PHYS_FIN_GAP_THRESHOLD;
    }

    protected function physicalFinancialPayload(PduProject $project): array
    {
        $physical = round((float) $project->progress_percentage, 1);
        $financial = round((float) $project->budget_execution_rate, 1);
        $gap = round($financial - $physical, 1);
        $critical = $gap > self::PHYS_FIN_GAP_CRITICAL;

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
                'threshold' => self::PHYS_FIN_GAP_THRESHOLD,
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
        if ($project->planned_completion_date) {
            return Carbon::parse($project->planned_completion_date);
        }
        if ($project->end_date) {
            return Carbon::parse($project->end_date);
        }
        return null;
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
