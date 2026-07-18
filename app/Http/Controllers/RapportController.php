<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\PduProject;
use App\Models\University;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RapportController extends Controller
{
    public function index(): InertiaResponse
    {
        $projects = PduProject::orderBy('code')
            ->get(['id', 'code', 'title', 'status', 'progress_percentage', 'university_id']);

        return Inertia::render('Rapports/Index', [
            'projects' => $projects,
            'stats' => [
                'total_projects' => PduProject::count(),
                'active_projects' => PduProject::whereIn('status', ['approved', 'in_progress'])->count(),
                'completed_projects' => PduProject::where('status', 'completed')->count(),
                'open_alerts' => Alert::where('is_resolved', false)->count(),
            ],
        ]);
    }

    public function projet(PduProject $project): Response
    {
        $this->authorizeGenerate();

        $project->load([
            'university:id,name,acronym,location,region',
            'director:id,name,email',
            'projectManager:id,name,email',
            'financialAgent:id,name,email',
            'buildingWorks.physicalProgresses',
            'lots',
            'milestones',
            'physicalProgresses',
            'financialProgresses',
            'payments',
            'indicatorTrackings.indicator',
            'alerts' => fn ($q) => $q->where('is_resolved', false)->orderByDesc('severity'),
        ]);

        $latestFinancial = $project->financialProgresses->last();
        $kpis = [
            'cpi' => $latestFinancial?->cpi,
            'spi' => $latestFinancial?->spi,
            'cv' => $latestFinancial?->cv,
            'sv' => $latestFinancial?->sv,
            'eac' => ($latestFinancial && $latestFinancial->cpi > 0)
                ? round((float) $project->budget_allocated / $latestFinancial->cpi, 2)
                : null,
            'budget_rate' => $project->budget_allocated > 0
                ? round(((float) $project->budget_spent / (float) $project->budget_allocated) * 100, 1)
                : null,
            'planned_progress' => $project->planned_progress,
            'milestones_reached' => $project->milestones->where('status', 'reached')->count(),
            'milestones_total' => $project->milestones->count(),
        ];

        $pdf = Pdf::loadView('pdf.rapport-projet', [
            'project' => $project,
            'kpis' => $kpis,
            'moa' => $project->financialMoa(),
            'generatedAt' => now(),
        ])->setPaper('A4', 'portrait');

        $filename = 'rapport-projet-' . $project->code . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function globalReport(Request $request): Response
    {
        $this->authorizeGenerate();

        $projects = PduProject::with('university:id,name,acronym,region')
            ->orderBy('code')
            ->get();

        $universities = University::active()
            ->withCount('pduProjects')
            ->orderBy('name')
            ->get(['id', 'name', 'acronym', 'region']);

        $stats = [
            'total_projects' => $projects->count(),
            'active_projects' => $projects->whereIn('status', ['approved', 'in_progress'])->count(),
            'completed_projects' => $projects->where('status', 'completed')->count(),
            'on_hold_projects' => $projects->where('status', 'on_hold')->count(),
            'total_budget' => (float) $projects->sum('budget_allocated'),
            'total_spent' => (float) $projects->sum('budget_spent'),
            'avg_progress' => $projects->count() ? round((float) $projects->avg('progress_percentage'), 1) : 0,
            'open_alerts' => Alert::where('is_resolved', false)->count(),
            'critical_alerts' => Alert::where('is_resolved', false)->where('severity', 'critical')->count(),
        ];

        $byRegion = $projects->groupBy(fn ($p) => $p->university?->region ?? 'Autre')
            ->map(fn ($group, $region) => [
                'region' => $region,
                'count' => $group->count(),
                'budget' => (float) $group->sum('budget_allocated'),
                'avg_progress' => round((float) $group->avg('progress_percentage'), 1),
            ])->values();

        $pdf = Pdf::loadView('pdf.rapport-global', [
            'projects' => $projects,
            'universities' => $universities,
            'stats' => $stats,
            'byRegion' => $byRegion,
            'generatedAt' => now(),
        ])->setPaper('A4', 'landscape');

        $filename = 'rapport-global-pdu-ci-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    protected function authorizeGenerate(): void
    {
        $user = request()->user();
        abort_unless(
            $user && $user->can('export_reports'),
            403,
            'Vous n\'avez pas le droit d\'exporter ou de générer des rapports.',
        );
    }
}
