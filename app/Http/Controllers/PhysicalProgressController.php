<?php

namespace App\Http\Controllers;

use App\Models\BuildingWork;
use App\Models\PduProject;
use App\Models\PhysicalProgress;
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

        if ($this->periodExists($project, $data['period'], $data['building_work_id'])) {
            return back()->withErrors(['period' => 'Un relevé existe déjà pour cette période et cet ouvrage.']);
        }

        PhysicalProgress::create(array_merge($data, [
            'pdu_project_id' => $project->id,
            'recorded_by' => $request->user()->id,
            'status' => 'submitted',
        ]));

        $this->agg->recomputeProjectProgress($project);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Avancement physique enregistré.');
    }

    public function update(Request $request, PduProject $project, PhysicalProgress $progress): RedirectResponse
    {
        abort_if($progress->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $data = $this->validatePayload($request, $project);

        if ($this->periodExists($project, $data['period'], $data['building_work_id'], $progress->id)) {
            return back()->withErrors(['period' => 'Un relevé existe déjà pour cette période et cet ouvrage.']);
        }

        $progress->update($data);

        $this->agg->recomputeProjectProgress($project);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Avancement physique mis à jour.');
    }

    public function destroy(Request $request, PduProject $project, PhysicalProgress $progress): RedirectResponse
    {
        abort_if($progress->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $progress->delete();

        $this->agg->recomputeProjectProgress($project);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Relevé supprimé.');
    }

    protected function periodExists(PduProject $project, string $period, ?int $workId, ?int $ignoreId = null): bool
    {
        return PhysicalProgress::where('pdu_project_id', $project->id)
            ->where('period', $period)
            ->where('building_work_id', $workId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
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

    protected function validatePayload(Request $request, PduProject $project): array
    {
        return $request->validate([
            'building_work_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($project) {
                    if (! BuildingWork::whereKey($value)->where('pdu_project_id', $project->id)->exists()) {
                        $fail('Cet ouvrage ne fait pas partie du projet.');
                    }
                },
            ],
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'measurement_date' => ['required', 'date'],
            'planned_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'actual_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ], [
            'building_work_id.required' => "Veuillez rattacher le relevé à un ouvrage.",
        ]);
    }
}
