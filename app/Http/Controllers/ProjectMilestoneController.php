<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\ProjectMilestone;
use App\Services\AlerteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectMilestoneController extends Controller
{
    public function __construct(protected AlerteService $alerteService) {}

    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request);

        ProjectMilestone::create(array_merge($data, [
            'pdu_project_id' => $project->id,
        ]));
        $this->alerteService->generateForAll();

        return back()->with('success', 'Jalon créé.');
    }

    public function update(Request $request, PduProject $project, ProjectMilestone $milestone): RedirectResponse
    {
        abort_if($milestone->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request);

        $milestone->update($data);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Jalon mis à jour.');
    }

    public function destroy(Request $request, PduProject $project, ProjectMilestone $milestone): RedirectResponse
    {
        abort_if($milestone->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $milestone->delete();
        $this->alerteService->generateForAll();

        return back()->with('success', 'Jalon supprimé.');
    }

    public function markReached(Request $request, PduProject $project, ProjectMilestone $milestone): RedirectResponse
    {
        abort_if($milestone->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $data = $request->validate([
            'actual_date' => ['required', 'date'],
            'observations' => ['nullable', 'string', 'max:500'],
        ]);

        $milestone->update([
            'status' => 'reached',
            'actual_date' => $data['actual_date'],
            'observations' => $data['observations'] ?? $milestone->observations,
        ]);
        $this->alerteService->generateForAll();

        return back()->with('success', "Jalon « {$milestone->name} » validé.");
    }

    protected function authorizeWrite(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && $user->can('manage_physical'),
            403,
        );
    }

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'building_work_id' => ['nullable', 'exists:building_works,id'],
            'project_lot_id' => ['nullable', 'exists:project_lots,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'planned_date' => ['required', 'date'],
            'actual_date' => ['nullable', 'date'],
            'status' => ['required', 'in:pending,reached,missed,cancelled'],
            'is_critical' => ['nullable', 'boolean'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
