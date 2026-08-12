<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PduProject;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consultation du journal d'activité : qui a fait quoi, quand et sur quel
 * projet. Réservé aux profils habilités à l'administration.
 */
class ActivityLogController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'model' => ['nullable', 'string'],
            'action' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = ActivityLog::query()
            ->with(['user:id,name', 'project:id,code,title'])
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['project_id'] ?? null, fn ($q, $v) => $q->where('pdu_project_id', $v))
            ->when($filters['model'] ?? null, fn ($q, $v) => $q->where('model', $v))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/ActivityLog', [
            'logs' => [
                'data' => collect($logs->items())->map(fn (ActivityLog $l) => [
                    'id' => $l->id,
                    'user' => $l->user?->name ?? 'Compte supprimé',
                    'action' => $l->action,
                    'action_label' => ActivityLogger::ACTIONS[$l->action] ?? $l->action,
                    'model' => $l->model,
                    'model_label' => ActivityLogger::MODELS[$l->model] ?? $l->model,
                    'description' => $l->description,
                    'project' => $l->project ? $l->project->code . ' — ' . $l->project->title : null,
                    'project_id' => $l->pdu_project_id,
                    'created_at' => $l->created_at?->toIso8601String(),
                ])->values()->all(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
            'filters' => $filters,
            'options' => [
                'users' => User::orderBy('name')->get(['id', 'name'])->toArray(),
                'projects' => PduProject::orderBy('code')->get(['id', 'code', 'title'])
                    ->map(fn ($p) => ['id' => $p->id, 'label' => $p->code . ' — ' . $p->title])->all(),
                'models' => collect(ActivityLogger::MODELS)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->all(),
                'actions' => collect(ActivityLogger::ACTIONS)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->all(),
            ],
            'stats' => [
                'total' => ActivityLog::count(),
                'today' => ActivityLog::whereDate('created_at', now()->toDateString())->count(),
                'contributors' => ActivityLog::distinct('user_id')->count('user_id'),
            ],
        ]);
    }
}
