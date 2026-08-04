<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\ProjectAmendment;
use App\Services\AlerteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectAmendmentController extends Controller
{
    public function __construct(protected AlerteService $alerteService) {}

    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request);

        ProjectAmendment::create(array_merge($data, [
            'pdu_project_id' => $project->id,
            'recorded_by' => $request->user()->id,
        ]));

        // Le montant actualisé change : les seuils budgétaires sont réévalués.
        $this->alerteService->generateForProject($project->fresh(['physicalProgresses', 'lots', 'milestones', 'amendments']));

        return back()->with('success', 'Avenant enregistré.');
    }

    public function update(Request $request, PduProject $project, ProjectAmendment $amendment): RedirectResponse
    {
        abort_if($amendment->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request);

        $amendment->update($data);

        $this->alerteService->generateForProject($project->fresh(['physicalProgresses', 'lots', 'milestones', 'amendments']));

        return back()->with('success', 'Avenant mis à jour.');
    }

    public function destroy(Request $request, PduProject $project, ProjectAmendment $amendment): RedirectResponse
    {
        abort_if($amendment->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $amendment->delete();

        $this->alerteService->generateForProject($project->fresh(['physicalProgresses', 'lots', 'milestones', 'amendments']));

        return back()->with('success', 'Avenant supprimé.');
    }

    protected function authorizeWrite(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && $user->can('manage_finances'),
            403,
            'Réservé aux agents financiers, directeurs et administrateurs.',
        );
    }

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'number' => ['required', 'string', 'max:40'],
            'object' => ['nullable', 'string', 'max:255'],
            'signed_date' => ['nullable', 'date'],
            // Un avenant peut être en plus-value (+) ou en moins-value (−).
            'amount' => ['required', 'numeric'],
            // Prolongation de délai en jours (négative = réduction).
            'duration_days' => ['nullable', 'integer', 'between:-3650,3650'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ], [
            'amount.required' => "Le montant de l'avenant est obligatoire.",
            'duration_days.between' => 'La prolongation doit être comprise entre -3650 et 3650 jours.',
        ]);
    }
}
