<?php

namespace App\Services;

use App\Models\PduProject;
use App\Models\PhysicalProgress;

/**
 * Indicateurs dérivés de la fiche projet, partagés entre l'affichage à l'écran
 * et l'édition des rapports.
 *
 * Ces deux calculs vivaient dans le contrôleur de la fiche projet ; le rapport
 * PDF en avait besoin à son tour. Les rassembler ici évite d'entretenir deux
 * versions d'une même règle, qui finiraient par diverger.
 */
class ProjectIndicatorService
{
    public function __construct(protected ThresholdService $seuils) {}

    /**
     * Décalage physico-financier (« effet de façade »).
     *
     * Compare l'avancement physique réel au taux de décaissement. Un
     * décaissement nettement en avance sur la réalisation signale un risque de
     * surfacturation ou d'avances non justifiées ; l'inverse signale des
     * travaux réalisés mais non encore payés.
     */
    public function physicalFinancial(PduProject $project): array
    {
        $physical = round((float) $project->progress_percentage, 1);
        $financial = round((float) $project->budget_execution_rate, 1);
        $gap = round($physical - $financial, 1);
        $ratio = $financial > 0 ? round($physical / $financial, 2) : null;

        $direction = 'aligned';
        if ($gap < 0) {
            $direction = 'overspend';      // décaissement en avance → risque de façade
        } elseif ($gap > 0) {
            $direction = 'underspend';     // réalisation en avance → paiements en retard
        }

        $abs = abs($gap);
        if ($physical == 0.0 && $financial == 0.0) {
            $level = 'none';
        } elseif ($abs <= $this->seuils->get('physfin_aligned_points')) {
            $level = 'aligned';
        } elseif ($abs <= $this->seuils->get('phys_fin_gap_points')) {
            $level = 'watch';
        } else {
            $level = 'critical';
        }

        return [
            'physical' => $physical,
            'financial' => $financial,
            'gap' => $gap,
            'ratio' => $ratio,
            'direction' => $direction,
            'level' => $level,
        ];
    }

    /**
     * Indice de fraîcheur de la donnée : ancienneté de la dernière saisie
     * d'avancement physique et couverture des ouvrages récemment mis à jour.
     * Un indice calculé sur une donnée périmée n'a aucune valeur — cet
     * indicateur permet de le savoir.
     */
    public function dataFreshness(PduProject $project): array
    {
        $lastDate = $project->physicalProgresses
            ->pluck('measurement_date')
            ->filter()
            ->max();

        $daysSince = $lastDate
            ? (int) $lastDate->copy()->startOfDay()->diffInDays(now()->startOfDay())
            : null;

        // Couverture : part des ouvrages ayant reçu une saisie récemment.
        $worksTotal = $project->buildingWorks->count();
        $threshold = now()->copy()->subDays($this->seuils->days('freshness_fresh_days'))->startOfDay();
        $worksRecent = $project->physicalProgresses
            ->filter(fn (PhysicalProgress $p) => $p->measurement_date && $p->measurement_date->greaterThanOrEqualTo($threshold))
            ->pluck('building_work_id')
            ->filter()
            ->unique()
            ->count();
        $coverageRate = $worksTotal > 0 ? (int) round($worksRecent / $worksTotal * 100) : null;

        $level = 'none';
        if ($daysSince !== null) {
            $level = $daysSince <= $this->seuils->days('freshness_fresh_days')
                ? 'fresh'
                : ($daysSince <= $this->seuils->days('freshness_stale_days') ? 'stale' : 'critical');
        }

        return [
            'last_update' => $lastDate?->toDateString(),
            'days_since' => $daysSince,
            'level' => $level,
            'lots_total' => $worksTotal,
            'lots_recent' => $worksRecent,
            'coverage_rate' => $coverageRate,
        ];
    }
}
