<?php

namespace Database\Seeders;

use App\Models\FinancialProgress;
use App\Models\PduProject;
use App\Models\PhysicalProgress;
use App\Models\ProjectLot;
use App\Models\ProjectMilestone;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectDetailsDemoSeeder extends Seeder
{
    protected array $defaultLots = [
        ['code' => 'L01', 'name' => 'Études & conception', 'weight' => 10],
        ['code' => 'L02', 'name' => 'Terrassements & VRD', 'weight' => 15],
        ['code' => 'L03', 'name' => 'Gros œuvre (fondations + structure)', 'weight' => 40],
        ['code' => 'L04', 'name' => 'Second œuvre (cloisons, menuiseries, finitions)', 'weight' => 25],
        ['code' => 'L05', 'name' => 'Équipements & réception', 'weight' => 10],
    ];

    protected array $defaultMilestones = [
        ['name' => 'Lancement officiel', 'offset' => 0, 'critical' => true],
        ['name' => 'Validation études APS', 'offset' => 45, 'critical' => false],
        ['name' => 'Fin terrassement et VRD', 'offset' => 120, 'critical' => false],
        ['name' => 'Hors-d\'eau / hors-d\'air', 'offset' => 270, 'critical' => true],
        ['name' => 'Réception provisoire', 'offset' => 450, 'critical' => true],
    ];

    public function run(): void
    {
        $projects = PduProject::with('lots')->get();

        foreach ($projects as $project) {
            if ($project->lots->isNotEmpty()) continue;

            DB::transaction(function () use ($project) {
                $this->createLots($project);
                $this->createMilestones($project);
                $this->createPhysicalProgresses($project);
                $this->createFinancialProgresses($project);
            });
        }
    }

    protected function createLots(PduProject $project): void
    {
        $start = $project->start_date ? Carbon::parse($project->start_date) : Carbon::now()->subMonths(6);
        $end = $project->end_date ? Carbon::parse($project->end_date) : (clone $start)->addMonths(18);
        $total = max(1, $start->diffInDays($end));
        $cursor = 0;

        foreach ($this->defaultLots as $i => $lot) {
            $duration = (int) round(($lot['weight'] / 100) * $total);
            $plannedStart = (clone $start)->addDays($cursor);
            $plannedEnd = (clone $plannedStart)->addDays($duration);
            $cursor += $duration;

            $progress = match (true) {
                $plannedEnd->isPast() => rand(70, 100),
                $plannedStart->isPast() => rand(10, 85),
                default => 0,
            };

            $status = match (true) {
                $progress >= 100 => 'completed',
                $progress > 0 => 'in_progress',
                default => 'not_started',
            };

            ProjectLot::create([
                'pdu_project_id' => $project->id,
                'code' => $lot['code'],
                'name' => $lot['name'],
                'weight_percentage' => $lot['weight'],
                'planned_start_date' => $plannedStart->toDateString(),
                'planned_end_date' => $plannedEnd->toDateString(),
                'actual_start_date' => $progress > 0 ? (clone $plannedStart)->addDays(rand(-3, 10))->toDateString() : null,
                'actual_end_date' => $progress >= 100 ? (clone $plannedEnd)->addDays(rand(-5, 20))->toDateString() : null,
                'progress_percentage' => $progress,
                'status' => $status,
                'sort_order' => $i,
            ]);
        }
    }

    protected function createMilestones(PduProject $project): void
    {
        $start = $project->start_date ? Carbon::parse($project->start_date) : Carbon::now()->subMonths(6);

        foreach ($this->defaultMilestones as $i => $m) {
            $planned = (clone $start)->addDays($m['offset']);
            $reached = $planned->isPast() && rand(0, 100) < 70;
            $missed = $planned->isPast() && ! $reached && rand(0, 100) < 40;

            ProjectMilestone::create([
                'pdu_project_id' => $project->id,
                'name' => $m['name'],
                'planned_date' => $planned->toDateString(),
                'actual_date' => $reached ? (clone $planned)->addDays(rand(-3, 15))->toDateString() : null,
                'status' => $reached ? 'reached' : ($missed ? 'missed' : 'pending'),
                'is_critical' => $m['critical'],
                'sort_order' => $i,
            ]);
        }
    }

    protected function createPhysicalProgresses(PduProject $project): void
    {
        $start = $project->start_date ? Carbon::parse($project->start_date) : Carbon::now()->subMonths(6);
        $months = min(12, max(3, $start->diffInMonths(Carbon::now())));

        for ($i = 0; $i <= $months; $i++) {
            $date = (clone $start)->addMonths($i);
            if ($date->isFuture()) break;

            $planned = min(100, round(($i / max(1, $months + 3)) * 100 + rand(-3, 3), 2));
            $actual = max(0, min(100, round($planned + rand(-12, 5), 2)));

            PhysicalProgress::create([
                'pdu_project_id' => $project->id,
                'project_lot_id' => null,
                'period' => $date->format('Y-m'),
                'measurement_date' => $date->toDateString(),
                'planned_percentage' => $planned,
                'actual_percentage' => $actual,
                'recorded_by' => $project->project_manager_id ?: $project->created_by,
                'status' => 'validated',
            ]);
        }
    }

    protected function createFinancialProgresses(PduProject $project): void
    {
        $bac = (float) $project->budget_allocated ?: 1_000_000_000;
        $start = $project->start_date ? Carbon::parse($project->start_date) : Carbon::now()->subMonths(6);
        $months = min(12, max(3, $start->diffInMonths(Carbon::now())));
        $cumPv = $cumEv = $cumAc = 0.0;

        for ($i = 0; $i <= $months; $i++) {
            $date = (clone $start)->addMonths($i);
            if ($date->isFuture()) break;

            $pv = round(($bac / max(1, $months + 3)) * (1 + (rand(-10, 10) / 100)), 2);
            $ev = round($pv * (rand(85, 105) / 100), 2);
            $ac = round($ev * (rand(90, 115) / 100), 2);
            $cumPv += $pv; $cumEv += $ev; $cumAc += $ac;

            FinancialProgress::create([
                'pdu_project_id' => $project->id,
                'period' => $date->format('Y-m'),
                'measurement_date' => $date->toDateString(),
                'planned_value' => $pv, 'earned_value' => $ev, 'actual_cost' => $ac,
                'cumulative_planned_value' => $cumPv,
                'cumulative_earned_value' => $cumEv,
                'cumulative_actual_cost' => $cumAc,
                'recorded_by' => $project->financial_agent_id ?: $project->created_by,
                'status' => 'validated',
            ]);
        }
    }
}
