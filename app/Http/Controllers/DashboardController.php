<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\University;
use App\Services\ProjectHealthService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(protected ProjectHealthService $healthService) {}

    public function index(): Response
    {
        $projects = PduProject::query()
            ->with([
                'university:id,name,acronym,location,region',
                'physicalProgresses',
                'financialProgresses',
                'milestones',
                'alerts' => fn ($q) => $q->where('is_resolved', false),
            ])
            ->orderBy('code')
            ->get();

        return Inertia::render('Dashboard', [
            'projects' => $this->projectsList($projects),
            'filters' => $this->filters(),
            'permissions' => auth()->user()->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }

    private function projectsList($projects): array
    {
        return $projects->map(function (PduProject $p) {
            $health = $this->healthService->score($p);

            return [
                'id' => $p->id,
                'code' => $p->code,
                'title' => $p->title,
                'status' => $p->status,
                'type' => $p->type,
                'progress_percentage' => (float) $p->progress_percentage,
                'planned_progress' => $p->planned_progress,
                'budget_execution_rate' => $p->budget_execution_rate,
                'budget_allocated' => (float) $p->budget_allocated,
                'budget_spent' => (float) $p->budget_spent,
                'university_id' => $p->university_id,
                'university_name' => $p->university?->name,
                'university_acronym' => $p->university?->acronym,
                'region' => $p->university?->region,
                'alerts_count' => $p->alerts->count(),
                'health_score' => $health['score'],
                'health_level' => $health['level'],
            ];
        })->values()->all();
    }

    private function filters(): array
    {
        return [
            'statuses' => ['draft', 'submitted', 'approved', 'in_progress', 'on_hold', 'completed', 'cancelled', 'archived'],
            'types' => ['construction', 'rehabilitation', 'equipement', 'formation', 'recherche', 'numerique'],
            'universities' => University::orderBy('name')->get(['id', 'name', 'acronym', 'region'])->toArray(),
        ];
    }
}
