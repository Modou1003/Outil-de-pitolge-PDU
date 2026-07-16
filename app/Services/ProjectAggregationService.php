<?php

namespace App\Services;

use App\Models\FinancialProgress;
use App\Models\PduProject;
use App\Models\PhysicalProgress;
use App\Models\ProjectLot;

class ProjectAggregationService
{
    /**
     * Recalcule l'avancement global du projet :
     * - Si des lots existent avec des pondérations : moyenne pondérée des progress_percentage des lots.
     * - Sinon : dernier actual_percentage saisi dans physical_progresses.
     */
    public function recomputeProjectProgress(PduProject $project): void
    {
        // L'avancement du projet = moyenne PONDÉRÉE de l'avancement des
        // ouvrages (dernière valeur réelle de chacun), par leur pondération.
        $works = $project->buildingWorks()->with('physicalProgresses')->get();

        $totalWeight = (float) $works->sum('weight_percentage');

        if ($totalWeight > 0) {
            // Tous les ouvrages pondérés comptent (0 % s'ils n'ont pas de saisie).
            $acc = 0.0;
            foreach ($works as $work) {
                $acc += (float) $work->weight_percentage * (float) $work->progress_percentage;
            }
            $progress = round($acc / $totalWeight, 2);
        } else {
            // Pas de pondération : moyenne simple des ouvrages ayant une saisie.
            $values = [];
            foreach ($works as $work) {
                if ($work->physicalProgresses->isNotEmpty()) {
                    $values[] = (float) $work->progress_percentage;
                }
            }
            if (! empty($values)) {
                $progress = round(array_sum($values) / count($values), 2);
            } else {
                $last = $project->physicalProgresses()
                    ->orderByDesc('measurement_date')
                    ->orderByDesc('id')
                    ->first();
                $progress = $last ? round((float) $last->actual_percentage, 2) : 0;
            }
        }

        $project->progress_percentage = $progress;
        $project->saveQuietly();
    }

    /**
     * Recalcule les colonnes cumulative_* dans financial_progresses pour un projet,
     * triées par period (YYYY-MM) croissant.
     */
    public function recomputeFinancialCumulatives(PduProject $project): void
    {
        $rows = FinancialProgress::where('pdu_project_id', $project->id)
            ->orderBy('period')
            ->get();

        $cumPv = 0.0; $cumEv = 0.0; $cumAc = 0.0;
        foreach ($rows as $row) {
            $cumPv += (float) $row->planned_value;
            $cumEv += (float) $row->earned_value;
            $cumAc += (float) $row->actual_cost;
            $row->cumulative_planned_value = $cumPv;
            $row->cumulative_earned_value = $cumEv;
            $row->cumulative_actual_cost = $cumAc;
            $row->saveQuietly();
        }
    }

    /**
     * Recalcule le budget_spent du projet à partir du dernier AC cumulé.
     */
    public function recomputeProjectBudgetSpent(PduProject $project): void
    {
        $project->budget_spent = (float) FinancialProgress::where('pdu_project_id', $project->id)->sum('actual_cost');
        $project->saveQuietly();
    }

    /**
     * Recalcule l'avancement d'un lot à partir des saisies physiques associées.
     */
    public function recomputeLotProgress(ProjectLot $lot): void
    {
        $last = PhysicalProgress::where('project_lot_id', $lot->id)
            ->orderByDesc('measurement_date')
            ->orderByDesc('id')
            ->first();

        if ($last) {
            $lot->progress_percentage = round((float) $last->actual_percentage, 2);
            if ($lot->progress_percentage >= 100 && $lot->status !== 'completed') {
                $lot->status = 'completed';
                $lot->actual_end_date = $lot->actual_end_date ?? $last->measurement_date;
            } elseif ($lot->progress_percentage > 0 && $lot->status === 'not_started') {
                $lot->status = 'in_progress';
                $lot->actual_start_date = $lot->actual_start_date ?? $last->measurement_date;
            }
        } else {
            $lot->progress_percentage = 0;
            $lot->status = 'not_started';
            $lot->actual_start_date = null;
            $lot->actual_end_date = null;
        }

        $lot->saveQuietly();
    }
}
