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

        // Courbes (SVG rendu côté serveur pour DomPDF).
        $phys = $this->aggregatePhysicalCurve($project);
        $fin = $this->aggregateFinancialCurve($project);

        $physicalChartSvg = count($phys['labels']) >= 1
            ? $this->lineChartSvg($phys['labels'], [
                ['color' => '#6366f1', 'dash' => true, 'values' => $phys['planned']],
                ['color' => '#10b981', 'dash' => false, 'values' => $phys['actual']],
            ], 100.0, fn ($v) => round($v) . ' %')
            : null;

        $finMax = 0.0;
        foreach (['pv', 'ev', 'ac'] as $kk) {
            foreach ($fin[$kk] as $v) {
                $finMax = max($finMax, (float) $v);
            }
        }
        $financialChartSvg = count($fin['labels']) >= 1
            ? $this->lineChartSvg($fin['labels'], [
                ['color' => '#6366f1', 'dash' => true, 'values' => $fin['pv']],
                ['color' => '#10b981', 'dash' => false, 'values' => $fin['ev']],
                ['color' => '#f59e0b', 'dash' => false, 'values' => $fin['ac']],
            ], max(1.0, $finMax * 1.1), fn ($v) => number_format($v / 1000000, 0, ',', ' ') . ' M')
            : null;

        $pdf = Pdf::loadView('pdf.rapport-projet', [
            'project' => $project,
            'kpis' => $kpis,
            'moa' => $project->financialMoa(),
            'physicalChartSvg' => $physicalChartSvg,
            'financialChartSvg' => $financialChartSvg,
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

    /** Avancement physique moyen par période (courbe en S). */
    private function aggregatePhysicalCurve(PduProject $project): array
    {
        $grouped = [];
        foreach ($project->physicalProgresses as $p) {
            $k = (string) $p->period;
            if ($k === '') continue;
            $grouped[$k] ??= ['pl' => 0.0, 'ac' => 0.0, 'c' => 0];
            $grouped[$k]['pl'] += (float) $p->planned_percentage;
            $grouped[$k]['ac'] += (float) $p->actual_percentage;
            $grouped[$k]['c']++;
        }
        ksort($grouped);
        $labels = $planned = $actual = [];
        foreach ($grouped as $period => $g) {
            if ($g['c'] === 0) continue;
            $labels[] = $period;
            $planned[] = round($g['pl'] / $g['c'], 1);
            $actual[] = round($g['ac'] / $g['c'], 1);
        }
        return ['labels' => $labels, 'planned' => $planned, 'actual' => $actual];
    }

    /** EVM cumulée du projet : somme des ouvrages par période, puis cumul. */
    private function aggregateFinancialCurve(PduProject $project): array
    {
        $grouped = [];
        foreach ($project->financialProgresses as $f) {
            $k = (string) $f->period;
            if ($k === '') continue;
            $grouped[$k] ??= ['pv' => 0.0, 'ev' => 0.0, 'ac' => 0.0];
            $grouped[$k]['pv'] += (float) $f->planned_value;
            $grouped[$k]['ev'] += (float) $f->earned_value;
            $grouped[$k]['ac'] += (float) $f->actual_cost;
        }
        ksort($grouped);
        $labels = $pv = $ev = $ac = [];
        $cpv = $cev = $cac = 0.0;
        foreach ($grouped as $period => $g) {
            $cpv += $g['pv']; $cev += $g['ev']; $cac += $g['ac'];
            $labels[] = $period;
            $pv[] = round($cpv, 2);
            $ev[] = round($cev, 2);
            $ac[] = round($cac, 2);
        }
        return ['labels' => $labels, 'pv' => $pv, 'ev' => $ev, 'ac' => $ac];
    }

    /**
     * Génère un graphique en courbes au format SVG (rendu par DomPDF).
     * $series : [ ['color'=>hex, 'dash'=>bool, 'values'=>[...]], ... ]
     */
    private function lineChartSvg(array $labels, array $series, float $yMax, callable $yFormat, int $yTicks = 5): string
    {
        if ($yMax <= 0) $yMax = 1;
        $W = 760; $H = 300; $L = 58; $R = 18; $T = 16; $B = 34;
        $pw = $W - $L - $R; $ph = $H - $T - $B;
        $n = count($labels);

        $x = fn ($i) => $n <= 1 ? $L + $pw / 2 : $L + ($i / ($n - 1)) * $pw;
        $y = fn ($v) => $T + $ph * (1 - max(0.0, min($yMax, (float) $v)) / $yMax);
        $esc = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);

        $svg = '<svg width="700" height="276" viewBox="0 0 ' . $W . ' ' . $H . '" xmlns="http://www.w3.org/2000/svg" font-family="DejaVu Sans, sans-serif">';
        $svg .= '<rect x="0" y="0" width="' . $W . '" height="' . $H . '" fill="#ffffff"/>';

        // Grille + labels Y
        for ($t = 0; $t <= $yTicks; $t++) {
            $val = $yMax * $t / $yTicks;
            $yy = round($y($val), 1);
            $svg .= '<line x1="' . $L . '" y1="' . $yy . '" x2="' . ($W - $R) . '" y2="' . $yy . '" stroke="#e5e7eb" stroke-width="1"/>';
            $svg .= '<text x="' . ($L - 6) . '" y="' . ($yy + 3) . '" text-anchor="end" font-size="10" fill="#6b7280">' . $esc($yFormat($val)) . '</text>';
        }

        // Labels X
        for ($i = 0; $i < $n; $i++) {
            $svg .= '<text x="' . round($x($i), 1) . '" y="' . ($H - $B + 16) . '" text-anchor="middle" font-size="10" fill="#6b7280">' . $esc($labels[$i]) . '</text>';
        }

        // Axes
        $svg .= '<line x1="' . $L . '" y1="' . $T . '" x2="' . $L . '" y2="' . ($T + $ph) . '" stroke="#9ca3af" stroke-width="1"/>';
        $svg .= '<line x1="' . $L . '" y1="' . ($T + $ph) . '" x2="' . ($W - $R) . '" y2="' . ($T + $ph) . '" stroke="#9ca3af" stroke-width="1"/>';

        // Séries
        foreach ($series as $s) {
            $pts = [];
            foreach ($s['values'] as $i => $v) {
                $pts[] = round($x($i), 1) . ',' . round($y($v), 1);
            }
            if (! $pts) continue;
            $dash = ! empty($s['dash']) ? ' stroke-dasharray="7,4"' : '';
            $svg .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $s['color'] . '" stroke-width="2.5"' . $dash . '/>';
            foreach ($s['values'] as $i => $v) {
                $svg .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y($v), 1) . '" r="2.6" fill="' . $s['color'] . '"/>';
            }
        }

        $svg .= '</svg>';
        return $svg;
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
