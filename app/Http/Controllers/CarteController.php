<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\University;
use Inertia\Inertia;
use Inertia\Response;

class CarteController extends Controller
{
    public function index(): Response
    {
        $universities = University::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['pduProjects' => function ($q) {
                $q->select('id', 'code', 'title', 'status', 'type', 'progress_percentage', 'university_id');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'acronym', 'location', 'region', 'latitude', 'longitude']);

        $sites = $universities->map(function (University $u) {
            $projects = $u->pduProjects;
            $activeCount = $projects->whereIn('status', ['approved', 'in_progress'])->count();
            $completedCount = $projects->where('status', 'completed')->count();
            $avgProgress = $projects->count()
                ? round($projects->avg('progress_percentage'), 1)
                : 0;

            return [
                'id' => $u->id,
                'name' => $u->name,
                'acronym' => $u->acronym,
                'location' => $u->location,
                'region' => $u->region,
                'latitude' => (float) $u->latitude,
                'longitude' => (float) $u->longitude,
                'projects_total' => $projects->count(),
                'projects_active' => $activeCount,
                'projects_completed' => $completedCount,
                'avg_progress' => $avgProgress,
                'dominant_status' => $this->dominantStatus($projects),
                'projects' => $projects->map(fn ($p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'title' => $p->title,
                    'status' => $p->status,
                    'type' => $p->type,
                    'progress_percentage' => (float) $p->progress_percentage,
                ])->values()->all(),
            ];
        })->values()->all();

        return Inertia::render('Carte', [
            'sites' => $sites,
            'stats' => [
                'total_sites' => count($sites),
                'total_projects' => PduProject::count(),
                'active_projects' => PduProject::whereIn('status', ['approved', 'in_progress'])->count(),
                'completed_projects' => PduProject::where('status', 'completed')->count(),
            ],
        ]);
    }

    private function dominantStatus($projects): string
    {
        if ($projects->isEmpty()) {
            return 'none';
        }

        if ($projects->where('status', 'in_progress')->count() > 0) {
            return 'in_progress';
        }

        if ($projects->where('status', 'on_hold')->count() > 0) {
            return 'on_hold';
        }

        if ($projects->where('status', 'completed')->count() === $projects->count()) {
            return 'completed';
        }

        if ($projects->where('status', 'approved')->count() > 0) {
            return 'approved';
        }

        return $projects->first()->status;
    }
}
