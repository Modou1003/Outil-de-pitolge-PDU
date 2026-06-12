<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\PhysicalProgress;
use App\Models\ProjectLot;
use App\Services\AlerteService;
use App\Services\ProjectAggregationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PhysicalProgressController extends Controller
{
    public function __construct(
        protected ProjectAggregationService $agg,
        protected AlerteService $alerteService,
    ) {}

    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeWrite($request);

        $data = $this->validatePayload($request, $project);

        $row = PhysicalProgress::create(array_merge($data, [
            'pdu_project_id' => $project->id,
            'recorded_by' => $request->user()->id,
            'status' => 'submitted',
        ]));

        $this->refreshAggregates($project, $row->project_lot_id);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Avancement physique enregistré.');
    }

    public function update(Request $request, PduProject $project, PhysicalProgress $progress): RedirectResponse
    {
        abort_if($progress->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $data = $this->validatePayload($request, $project, $progress->id);
        $previousLotId = $progress->project_lot_id;

        $progress->update($data);

        $this->refreshAggregates($project, $progress->project_lot_id, $previousLotId);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Avancement physique mis à jour.');
    }

    public function destroy(Request $request, PduProject $project, PhysicalProgress $progress): RedirectResponse
    {
        abort_if($progress->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $lotId = $progress->project_lot_id;
        $progress->delete();

        $this->refreshAggregates($project, $lotId);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Relevé supprimé.');
    }

    protected function refreshAggregates(PduProject $project, ?int $lotId = null, ?int $previousLotId = null): void
    {
        foreach (array_unique(array_filter([$lotId, $previousLotId])) as $id) {
            if ($lot = ProjectLot::find($id)) {
                $this->agg->recomputeLotProgress($lot);
            }
        }
        $this->agg->recomputeProjectProgress($project);
    }

    protected function authorizeWrite(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && $user->can('manage_physical'),
            403,
            'Réservé aux chefs de projet, directeurs et administrateurs.',
        );
    }

    protected function validatePayload(Request $request, PduProject $project, ?int $ignoreId = null): array
    {
        return $request->validate([
            'project_lot_id' => ['nullable', 'integer', 'exists:project_lots,id'],
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'measurement_date' => ['required', 'date'],
            'planned_percentage' => ['required', 'numeric', 'max:100'],
            'actual_percentage' => ['required', 'numeric', 'max:100'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
