<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\BuildingWork;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BuildingWorkController extends Controller
{
    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request, $project);

        // Générer le code automatiquement
        $maxCode = BuildingWork::where('pdu_project_id', $project->id)
            ->get()
            ->map(fn($w) => (int) preg_replace('/[^0-9]/', '', $w->code))
            ->max() ?? 0;

        $data['code'] = 'OUV-' . str_pad($maxCode + 1, 3, '0', STR_PAD_LEFT);

        $work = BuildingWork::create(array_merge($data, [
            'pdu_project_id' => $project->id,
        ]));

        return back()->with('success', "Ouvrage « {$work->name} » créé.");
    }

    public function show(PduProject $project, BuildingWork $work)
    {
        abort_if($work->pdu_project_id !== $project->id, 404);
        
        return inertia('Projects/BuildingWorkDetail', [
            'project' => $project->load('user'),
            'work' => $work->load('lots'),
            'lots' => $work->lots()->orderBy('sort_order')->get(),
            'milestones' => $project->milestones()->get(),
        ]);
    }

    public function update(Request $request, PduProject $project, BuildingWork $work): RedirectResponse
    {
        abort_if($work->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request, $project, $work->id);

        $work->update($data);

        return back()->with('success', 'Ouvrage mis à jour.');
    }

    public function destroy(Request $request, PduProject $project, BuildingWork $work): RedirectResponse
    {
        abort_if($work->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $work->delete();

        return back()->with('success', 'Ouvrage supprimé.');
    }

    protected function authorizeWrite(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && $user->can('manage_physical'),
            403,
        );
    }

    protected function validatePayload(Request $request, PduProject $project, ?int $ignoreId = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
            'progress_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:not_started,in_progress,on_hold,completed,cancelled'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        return $request->validate($rules);
    }
}
