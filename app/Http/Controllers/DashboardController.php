<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\University;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $projects = PduProject::with('university:id,name,acronym,location,region')
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
                'alerts_count' => 0,
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
