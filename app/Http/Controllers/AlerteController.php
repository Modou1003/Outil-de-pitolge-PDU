<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertComment;
use App\Services\AlerteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlerteController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Alert::with([
                'project:id,code,title,status',
                'comments.author:id,name',
            ])
            ->orderByDesc('severity')
            ->orderByDesc('detected_at');

        if ($request->filled('status')) {
            $query->where('is_resolved', $request->string('status')->toString() === 'resolved');
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $alerts = $query->paginate(25)->withQueryString();

        return Inertia::render('Alertes/Index', [
            'alerts' => $alerts->through(fn (Alert $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'type_label' => $a->type_label,
                'severity' => $a->severity,
                'severity_label' => $a->severity_label,
                'title' => $a->title,
                'message' => $a->message,
                'context' => $a->context,
                'is_resolved' => $a->is_resolved,
                'detected_at' => $a->detected_at?->toIso8601String(),
                'resolved_at' => $a->resolved_at?->toIso8601String(),
                'project' => $a->project ? [
                    'id' => $a->project->id,
                    'code' => $a->project->code,
                    'title' => $a->project->title,
                    'status' => $a->project->status,
                ] : null,
                'comments' => $a->comments->map(fn ($c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'author' => $c->author?->name ?? 'Utilisateur supprimé',
                    'user_id' => $c->user_id,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values(),
            ]),
            'filters' => [
                'status' => $request->input('status', ''),
                'severity' => $request->input('severity', ''),
                'type' => $request->input('type', ''),
            ],
            'stats' => [
                'open' => Alert::open()->count(),
                'critical' => Alert::open()->where('severity', 'critical')->count(),
                'warning' => Alert::open()->where('severity', 'warning')->count(),
                'resolved_last_30' => Alert::resolved()->where('resolved_at', '>=', now()->subDays(30))->count(),
            ],
            'types' => Alert::TYPES,
            'severities' => Alert::SEVERITIES,
        ]);
    }

    public function resolve(Request $request, Alert $alert): RedirectResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $alert->update([
            'is_resolved' => true,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
            'resolution_note' => $request->input('note'),
        ]);

        return back()->with('success', 'Alerte marquée comme résolue.');
    }

    public function addComment(Request $request, Alert $alert): RedirectResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $alert->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Observation ajoutée.');
    }

    public function deleteComment(Request $request, Alert $alert, AlertComment $comment): RedirectResponse
    {
        if ($comment->alert_id !== $alert->id) {
            abort(404);
        }

        $isOwner = $comment->user_id === $request->user()->id;
        $isSupervisor = $request->user()->hasAnyRole(['admin', 'directeur']);

        if (! $isOwner && ! $isSupervisor) {
            abort(403, 'Accès refusé.');
        }

        $comment->delete();

        return back()->with('success', 'Observation supprimée.');
    }

    public function destroy(Request $request, Alert $alert): RedirectResponse
    {
        if (!$request->user()->hasAnyRole(['admin', 'directeur'])) {
            abort(403, 'Accès refusé.');
        }

        $alert->delete();

        return back()->with('success', 'Alerte supprimée.');
    }

    public function generate(AlerteService $service): RedirectResponse
    {
        $summary = $service->generateForAll();

        return back()->with(
            'success',
            sprintf(
                'Analyse terminée : %d projet(s) scanné(s), %d alerte(s) créée(s), %d fermée(s).',
                $summary['scanned'],
                $summary['created'],
                $summary['closed'],
            ),
        );
    }
}
