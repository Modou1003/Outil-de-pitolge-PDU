<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\ProjectLot;
use App\Models\ProjectMilestone;
use App\Services\AlerteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProjectMilestoneController extends Controller
{
    public function __construct(protected AlerteService $alerteService) {}

    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->coherentMilestone($this->validatePayload($request, $project));

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
        $data = $this->coherentMilestone($this->validatePayload($request, $project));

        $milestone->update($data);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Jalon mis à jour.');
    }

    /**
     * Rend cohérents le statut et la date réelle d'un jalon.
     * Une date réelle implique « atteint » ; « atteint » impose une date réelle.
     */
    protected function coherentMilestone(array $data): array
    {
        $status = $data['status'] ?? 'pending';
        $actual = $data['actual_date'] ?? null;

        if ($status !== 'cancelled' && ! empty($actual)) {
            $data['status'] = 'reached';
        }
        if ($status !== 'reached') {
            // Un jalon non atteint ne conserve pas de date de réalisation.
            $data['actual_date'] = null;
        }

        return $data;
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

    protected function validatePayload(Request $request, PduProject $project): array
    {
        $projectStart = $project->start_date?->toDateString();

        // Borne basse uniquement : un jalon ne peut précéder le début du projet.
        // Un jalon au-delà de la fin planifiée reste permis (signal de retard).
        $plannedRules = ['required', 'date'];
        if ($projectStart) $plannedRules[] = 'after_or_equal:' . $projectStart;

        // La date prévue doit aussi rester dans la période du lot associé.
        $plannedRules[] = function ($attribute, $value, $fail) use ($request, $project) {
            $lotId = $request->input('project_lot_id');
            if (! $lotId || ! $value) {
                return;
            }
            $lot = ProjectLot::whereKey($lotId)->where('pdu_project_id', $project->id)->first();
            if (! $lot) {
                return;
            }
            $d = Carbon::parse($value);
            if ($lot->planned_start_date && $d->lt($lot->planned_start_date)) {
                $fail('La date prévue du jalon est antérieure au début de son lot.');
            }
            if ($lot->planned_end_date && $d->gt($lot->planned_end_date)) {
                $fail('La date prévue du jalon est postérieure à la fin de son lot.');
            }
        };

        return $request->validate([
            'building_work_id' => ['nullable', 'exists:building_works,id'],
            'project_lot_id' => [
                'nullable',
                function ($attribute, $value, $fail) use ($project) {
                    if ($value && ! ProjectLot::whereKey($value)->where('pdu_project_id', $project->id)->exists()) {
                        $fail('Ce lot ne fait pas partie du projet.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'planned_date' => $plannedRules,
            'actual_date' => ['nullable', 'date', 'required_if:status,reached'],
            'status' => ['required', 'in:pending,reached,missed,cancelled'],
            'is_critical' => ['nullable', 'boolean'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'planned_date.after_or_equal' => 'La date prévue du jalon doit être postérieure ou égale au début du projet.',
            'actual_date.required_if' => 'Un jalon « atteint » doit avoir une date de réalisation.',
        ]);
    }
}
