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
        $exists = Alert::where('pdu_project_id', $project->id)
            ->where('type', $type)
            ->where('is_resolved', false)
            ->exists();

        if ($exists) return false;

        $payload = $this->buildPayload($project, $type);

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
            'no_update' => [
                'severity' => 'info',
                'title' => 'Donnée manquante',
                'message' => 'Aucun avancement physique saisi ce mois-ci.',
                'context' => ['month' => now()->format('Y-m')],
            ],
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

    protected function detectNoUpdate(PduProject $project): bool
    {
        return ! $project->physicalProgresses()
            ->whereBetween('measurement_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->exists();
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

        $users = User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->when($principalMemberIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $principalMemberIds))
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
