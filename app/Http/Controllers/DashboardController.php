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
        $projects = PduProject::query()
            ->with([
                'university:id,name,acronym,location,region',
                'physicalProgresses',
                'financialProgresses',
                'milestones',
                'amendments',
                'alerts' => fn ($q) => $q->where('is_resolved', false),
            ])
            ->orderBy('code')
            ->get();

        return Inertia::render('Dashboard', [
            'projects' => $this->projectsList($projects),
            'kpis' => $this->kpis($projects),
            'filters' => $this->filters(),
            'permissions' => auth()->user()->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }

    /**
     * Synthèse du portefeuille présentée en tête du tableau de bord.
     *
     * L'avancement moyen est pondéré par le montant des marchés : un projet de
     * soixante-huit milliards ne saurait peser autant qu'une opération de
     * quelques centaines de millions dans l'appréciation d'ensemble.
     */
    private function kpis($projects): array
    {
        $enveloppes = $projects->sum(fn (PduProject $p) => (float) $p->budget_revised);
        $avancement = $enveloppes > 0
            ? $projects->sum(fn (PduProject $p) => (float) $p->progress_percentage * (float) $p->budget_revised) / $enveloppes
            : (float) $projects->avg('progress_percentage');

        $engage = (float) $projects->sum(fn (PduProject $p) => (float) $p->budget_revised);
        $decaisse = (float) $projects->sum('budget_spent');

        return [
            'total_projects' => $projects->count(),
            'average_progress' => round($avancement, 1),
            'budget_allocated_total' => $engage,
            'budget_spent_total' => $decaisse,
            'budget_execution_rate' => $engage > 0 ? round($decaisse / $engage * 100, 1) : null,
            'active_alerts' => $projects->sum(fn (PduProject $p) => $p->alerts->count()),
            'status_breakdown' => $projects->countBy('status')->all(),
        ];
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
                'amendments_total' => $p->amendments_total,
                'budget_revised' => $p->budget_revised,
                'budget_spent' => (float) $p->budget_spent,
                'university_id' => $p->university_id,
                'university_name' => $p->university?->name,
                'university_acronym' => $p->university?->acronym,
                'region' => $p->university?->region,
                'alerts_count' => $p->alerts->count(),
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
