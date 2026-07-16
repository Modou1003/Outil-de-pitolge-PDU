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

        if ($this->periodExists($project, $data['period'], $data['project_lot_id'] ?? null)) {
            return back()->withErrors(['period' => 'Un relevé existe déjà pour cette période et ce lot.']);
        }

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

        if ($this->periodExists($project, $data['period'], $data['project_lot_id'] ?? null, $progress->id)) {
            return back()->withErrors(['period' => 'Un relevé existe déjà pour cette période et ce lot.']);
        }

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

    protected function periodExists(PduProject $project, string $period, ?int $lotId, ?int $ignoreId = null): bool
    {
        return PhysicalProgress::where('pdu_project_id', $project->id)
            ->where('period', $period)
            ->when(
                $lotId,
                fn ($q) => $q->where('project_lot_id', $lotId),
                fn ($q) => $q->whereNull('project_lot_id'),
            )
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
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
        // Si le projet possède des ouvrages d'avancement, un relevé doit y être
        // rattaché, sinon il serait ignoré dans le calcul de l'avancement.
        $hasLots = $project->lots()->where('kind', 'physical')->exists();

        return $request->validate([
            'project_lot_id' => [
                $hasLots ? 'required' : 'nullable',
                'integer',
                'exists:project_lots,id',
                function ($attribute, $value, $fail) use ($project) {
                    if (! $value) {
                        return;
                    }
                    $belongs = ProjectLot::whereKey($value)->where('pdu_project_id', $project->id)->exists();
                    if (! $belongs) {
                        $fail('Ce lot ne fait pas partie du projet.');
                    }
                },
            ],
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'measurement_date' => ['required', 'date'],
            'planned_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'actual_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ], [
            'project_lot_id.required' => 'Ce projet comporte des lots : veuillez rattacher le relevé à un lot.',
        ]);
    }
}
