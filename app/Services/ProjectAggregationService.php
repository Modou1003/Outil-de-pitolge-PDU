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
        $lots = $project->lots()->get();

        if ($lots->isNotEmpty()) {
            $totalWeight = (float) $lots->sum('weight_percentage');

            if ($totalWeight > 0) {
                $weighted = 0.0;
                foreach ($lots as $lot) {
                    $w = (float) $lot->weight_percentage;
                    $p = (float) $lot->progress_percentage;
                    $weighted += $w * $p;
                }
                $progress = round($weighted / $totalWeight, 2);
            } else {
                // Fallback when lots exist but are not weighted yet.
                $progress = round((float) $lots->avg('progress_percentage'), 2);
            }
        } else {
            $last = $project->physicalProgresses()
                ->orderByDesc('measurement_date')
                ->orderByDesc('id')
                ->first();
            $progress = $last ? round((float) $last->actual_percentage, 2) : 0;
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
        $last = FinancialProgress::where('pdu_project_id', $project->id)
            ->orderByDesc('period')
            ->first();

        $project->budget_spent = $last ? (float) $last->cumulative_actual_cost : 0;
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
