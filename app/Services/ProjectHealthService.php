<?php

namespace App\Services;

use App\Models\PduProject;

/**
 * Score de santé global d'un projet (0–100).
 *
 * Agrège en une note unique les signaux « réalité terrain » déjà calculés
 * ailleurs (planning projeté, coût, effet de façade, fraîcheur de la donnée,
 * respect des jalons, alertes ouvertes). Chaque composante vaut 0–100 ; le
 * score final est la moyenne pondérée des seules composantes calculables.
 *
 * Le projet passé en argument doit avoir ses relations chargées :
 * physicalProgresses, financialProgresses, milestones, alerts, lots.
 */
class ProjectHealthService
{
    /** Pondération de chaque composante (sur les composantes disponibles). */
    private const WEIGHTS = [
        'schedule' => 0.30,
        'cost' => 0.20,
        'facade' => 0.15,
        'data' => 0.15,
        'milestones' => 0.10,
        'alerts' => 0.10,
    ];

    public function score(PduProject $project): array
    {
        $components = [
            'schedule' => $this->scheduleScore($project),
            'cost' => $this->costScore($project),
            'facade' => $this->facadeScore($project),
            'data' => $this->dataScore($project),
            'milestones' => $this->milestonesScore($project),
            'alerts' => $this->alertsScore($project),
        ];

        $weightedSum = 0.0;
        $weightTotal = 0.0;
        foreach ($components as $key => $value) {
            if ($value === null) {
                continue;
            }
            $weightedSum += $value * self::WEIGHTS[$key];
            $weightTotal += self::WEIGHTS[$key];
        }

        if ($weightTotal <= 0.0) {
            return ['score' => null, 'level' => 'unknown', 'components' => $components];
        }

        $score = (int) round($weightedSum / $weightTotal);
        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'level' => $this->level($score),
            'components' => array_map(
                fn ($v) => $v === null ? null : (int) round($v),
                $components,
            ),
        ];
    }

    private function level(int $score): string
    {
        if ($score >= 80) return 'healthy';
        if ($score >= 60) return 'fair';
        if ($score >= 40) return 'at_risk';
        return 'critical';
    }

    /** Planning : retard projeté au rythme réel, à défaut le SPI. */
    private function scheduleScore(PduProject $project): ?float
    {
        $delay = $this->projectedDelayDays($project);
        if ($delay !== null) {
            return $delay <= 0 ? 100.0 : $this->clamp(100 - $delay / 2.4); // 240 j → 0
        }
        $spi = $this->spi($project);
        return $spi !== null ? $this->clamp($spi * 100) : null;
    }

    /** Coût : efficience CPI. */
    private function costScore(PduProject $project): ?float
    {
        $cpi = $this->cpi($project);
        return $cpi !== null ? $this->clamp($cpi * 100) : null;
    }

    /** Effet de façade : décaissement en avance sur l'avancement physique. */
    private function facadeScore(PduProject $project): ?float
    {
        if (! $project->budget_allocated || $project->budget_allocated <= 0) {
            return null;
        }
        $physical = (float) $project->progress_percentage;
        $financial = (float) $project->budget_execution_rate;
        if ($physical <= 0 && $financial <= 0) {
            return null;
        }
        $gap = $financial - $physical; // positif = décaissement en avance
        return $gap <= 10 ? 100.0 : $this->clamp(100 - ($gap - 10) / 40 * 100); // écart 50 → 0
    }

    /** Fiabilité : fraîcheur de la dernière saisie d'avancement physique. */
    private function dataScore(PduProject $project): ?float
    {
        $last = $project->physicalProgresses->pluck('measurement_date')->filter()->max();
        if (! $last) {
            // Aucune donnée : neutre pour les projets non actifs, pénalisant sinon.
            return in_array($project->status, ['draft', 'completed', 'cancelled', 'archived'], true) ? null : 0.0;
        }
        $days = (int) $last->copy()->startOfDay()->diffInDays(now()->startOfDay());
        return $days <= 30 ? 100.0 : $this->clamp(100 - ($days - 30) / 60 * 100); // 90 j → 0
    }

    /** Jalons : proportion de jalons non manqués (statut « missed » OU échu non atteint). */
    private function milestonesScore(PduProject $project): ?float
    {
        $total = $project->milestones->count();
        if ($total === 0) {
            return null;
        }
        $missed = $project->milestones->filter(function ($m) {
            if ($m->status === 'missed') {
                return true;
            }
            // Échu sans avoir été atteint ni annulé = manqué de fait.
            return $m->planned_date
                && $m->planned_date->isPast()
                && ! in_array($m->status, ['reached', 'cancelled'], true);
        })->count();

        return $this->clamp(100 * ($total - $missed) / $total);
    }

    /** Alertes : pénalité pondérée par la sévérité des alertes ouvertes. */
    private function alertsScore(PduProject $project): ?float
    {
        $open = $project->alerts->where('is_resolved', false);
        if ($open->isEmpty()) {
            return 100.0;
        }
        $penalty = 0;
        foreach ($open as $alert) {
            $penalty += match ($alert->severity) {
                'critical' => 30,
                'warning' => 12,
                default => 5,
            };
        }
        return $this->clamp(100 - $penalty);
    }

    private function spi(PduProject $project): ?float
    {
        $pv = (float) $project->financialProgresses->sum('planned_value');
        $ev = (float) $project->financialProgresses->sum('earned_value');
        return $pv > 0 ? $ev / $pv : null;
    }

    private function cpi(PduProject $project): ?float
    {
        $ac = (float) $project->financialProgresses->sum('actual_cost');
        $ev = (float) $project->financialProgresses->sum('earned_value');
        return $ac > 0 ? $ev / $ac : null;
    }

    /** Retard de livraison projeté (jours) au rythme physique réel ; null si non calculable. */
    private function projectedDelayDays(PduProject $project): ?int
    {
        $start = $project->start_date;
        $plannedEnd = $project->planned_completion_date ?: $project->end_date;
        if (! $start || ! $plannedEnd) {
            return null;
        }
        $physical = (float) $project->progress_percentage;
        if ($physical <= 0 || $physical >= 100) {
            return null;
        }

        $today = now()->startOfDay();
        $start = $start->copy()->startOfDay();
        $plannedEnd = $plannedEnd->copy()->startOfDay();

        $elapsed = $start->diffInDays($today, false);
        if ($elapsed <= 0) {
            return null;
        }

        $pacePerDay = $physical / $elapsed;
        $remainingDays = (int) ceil((100 - $physical) / $pacePerDay);
        $projectedEnd = $today->copy()->addDays($remainingDays);

        return (int) $plannedEnd->diffInDays($projectedEnd, false);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}
