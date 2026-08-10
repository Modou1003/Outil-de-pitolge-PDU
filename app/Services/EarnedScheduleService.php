<?php

namespace App\Services;

use App\Models\BuildingWork;
use App\Models\PduProject;
use App\Models\PhysicalProgress;
use Carbon\Carbon;

/**
 * Prévision de la date d'achèvement par la méthode « Earned Schedule »
 * (W. Lipke, 2003), extension de l'analyse de la valeur acquise au délai.
 *
 * Une extrapolation linéaire du rythme observé surestime fortement le retard
 * en début de chantier, parce qu'elle prolonge indéfiniment la lenteur de la
 * phase d'installation. L'Earned Schedule corrige ce biais en prenant la
 * courbe planifiée — c'est-à-dire la courbe en S contractuelle — comme
 * référence :
 *
 *   ES              = date à laquelle la courbe planifiée atteignait
 *                     l'avancement réel constaté aujourd'hui
 *   AT              = temps réellement écoulé depuis le démarrage
 *   SPI(t)          = ES / AT
 *   Durée estimée   = durée planifiée / SPI(t)
 *
 * Aucune constante n'est postulée : la forme de la courbe provient des
 * pourcentages planifiés saisis avec chaque relevé d'avancement physique.
 *
 * Le projet doit avoir ses relations chargées : physicalProgresses,
 * buildingWorks et amendments.
 */
class EarnedScheduleService
{
    /** Retard (en jours) au-delà duquel la dérive est signalée puis critique. */
    public const WATCH_DAYS = 15;
    public const CRITICAL_DAYS = 60;

    public function forecast(PduProject $project): array
    {
        $physical = round((float) $project->progress_percentage, 1);

        $none = fn (string $reason) => [
            'level' => 'none',
            'reason' => $reason,
            'method' => 'earned_schedule',
            'projected_end_date' => null,
            'planned_end_date' => null,
            'delay_days' => null,
            'physical' => $physical,
            'spi_t' => null,
            'earned_schedule_days' => null,
            'actual_time_days' => null,
        ];

        $start = $project->start_date;
        // Deux échéances distinctes, et il ne faut pas les confondre :
        //   - la référence du planning initial, qui est celle que décrit la
        //     courbe planifiée saisie relevé après relevé ;
        //   - l'échéance contractuelle actualisée par les avenants de délai.
        // La durée planifiée se mesure sur la première (sinon on étirerait une
        // courbe qui n'a pas été re-basée), le retard se mesure sur la seconde.
        $baselineEnd = $project->planned_end_date;
        $plannedEnd = $project->planned_end_date_revised;
        if (! $start || ! $baselineEnd || ! $plannedEnd) {
            return $none('no_dates');
        }

        $today = now()->startOfDay();
        $start = $start->copy()->startOfDay();
        $baselineEnd = $baselineEnd->copy()->startOfDay();
        $plannedEnd = $plannedEnd->copy()->startOfDay();

        // Chantier physiquement achevé : il n'y a plus rien à projeter.
        if ($physical >= 100) {
            return array_merge($none('completed'), [
                'level' => 'done',
                'planned_end_date' => $plannedEnd->toDateString(),
            ]);
        }

        // PD : durée du planning de référence, cohérente avec la courbe planifiée.
        $plannedDuration = (int) $start->diffInDays($baselineEnd, false);
        if ($plannedDuration <= 0) {
            return $none('no_dates');
        }

        // AT : temps écoulé.
        $actualTime = (int) $start->diffInDays($today, false);
        if ($actualTime <= 0 || $physical <= 0) {
            return $none('not_enough_data');
        }

        $curve = $this->plannedCurve($project, $start);
        if (count($curve) < 2) {
            return $none('no_planned_curve');
        }

        $earnedSchedule = $this->earnedScheduleDays($curve, $physical, $actualTime);
        if ($earnedSchedule === null || $earnedSchedule <= 0) {
            return $none('no_planned_curve');
        }

        $spiT = $earnedSchedule / $actualTime;
        if ($spiT <= 0) {
            return $none('not_enough_data');
        }

        $estimatedDuration = (int) ceil($plannedDuration / $spiT);
        $projectedEnd = $start->copy()->addDays($estimatedDuration);
        $delayDays = (int) $plannedEnd->diffInDays($projectedEnd, false);

        if ($delayDays <= self::WATCH_DAYS) {
            $level = 'on_track';
        } elseif ($delayDays <= self::CRITICAL_DAYS) {
            $level = 'watch';
        } else {
            $level = 'critical';
        }

        return [
            'level' => $level,
            'reason' => null,
            'method' => 'earned_schedule',
            'projected_end_date' => $projectedEnd->toDateString(),
            'planned_end_date' => $plannedEnd->toDateString(),
            'delay_days' => $delayDays,
            'physical' => $physical,
            'spi_t' => round($spiT, 3),
            'earned_schedule_days' => (int) round($earnedSchedule),
            'actual_time_days' => $actualTime,
        ];
    }

    /** Retard projeté en jours, ou null si la projection n'est pas calculable. */
    public function projectedDelayDays(PduProject $project): ?int
    {
        return $this->forecast($project)['delay_days'];
    }

    /**
     * Courbe planifiée du projet : pour chaque date de mesure, l'avancement
     * prévu au niveau projet, agrégé depuis les ouvrages avec les mêmes
     * pondérations que l'avancement réel.
     *
     * Un ouvrage sans relevé à une date donnée compte pour 0 %, comme dans le
     * calcul de l'avancement réel : les deux courbes restent comparables.
     *
     * @return list<array{day:int, planned:float}>
     */
    private function plannedCurve(PduProject $project, Carbon $start): array
    {
        $works = $project->buildingWorks;
        if ($works->isEmpty()) {
            return [];
        }

        $rows = $project->physicalProgresses
            ->filter(fn (PhysicalProgress $p) => $p->measurement_date && $p->building_work_id);
        if ($rows->isEmpty()) {
            return [];
        }

        $totalWeight = (float) $works->sum('weight_percentage');
        $byWork = $rows->groupBy('building_work_id');

        $dates = $rows->pluck('measurement_date')
            ->unique(fn (Carbon $d) => $d->toDateString())
            ->sort()
            ->values();

        $curve = [];
        foreach ($dates as $date) {
            $weighted = 0.0;
            $simpleSum = 0.0;
            $simpleCount = 0;

            foreach ($works as $work) {
                /** @var BuildingWork $work */
                $last = ($byWork[$work->id] ?? collect())
                    ->filter(fn (PhysicalProgress $p) => $p->measurement_date->lessThanOrEqualTo($date))
                    ->sortBy('measurement_date')
                    ->last();

                $value = $last ? (float) $last->planned_percentage : 0.0;

                $weighted += (float) $work->weight_percentage * $value;
                $simpleSum += $value;
                $simpleCount++;
            }

            $planned = $totalWeight > 0
                ? $weighted / $totalWeight
                : ($simpleCount > 0 ? $simpleSum / $simpleCount : 0.0);

            $day = (int) $start->diffInDays($date, false);
            if ($day < 0) {
                continue;
            }

            $curve[] = ['day' => $day, 'planned' => round($planned, 2)];
        }

        return $curve;
    }

    /**
     * ES : instant, exprimé en jours depuis le démarrage, où la courbe
     * planifiée atteignait l'avancement réel d'aujourd'hui. Interpolation
     * linéaire entre les deux points qui encadrent cette valeur.
     *
     * Si l'avancement réel dépasse toute la courbe planifiée connue, ES est
     * plafonné au temps écoulé : la courbe planifiée ne va pas au-delà
     * d'aujourd'hui, on ne peut donc pas mesurer une avance, seulement
     * constater l'absence de retard.
     */
    private function earnedScheduleDays(array $curve, float $physical, int $actualTime): ?float
    {
        $prevDay = 0.0;
        $prevValue = 0.0;

        foreach ($curve as $point) {
            if ($point['planned'] >= $physical) {
                $delta = $point['planned'] - $prevValue;
                if ($delta <= 0) {
                    return (float) $point['day'];
                }
                $ratio = ($physical - $prevValue) / $delta;

                return $prevDay + $ratio * ($point['day'] - $prevDay);
            }

            $prevDay = (float) $point['day'];
            $prevValue = (float) $point['planned'];
        }

        return (float) $actualTime;
    }
}
