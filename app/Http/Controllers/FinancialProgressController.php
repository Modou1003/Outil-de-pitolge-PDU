<?php

namespace App\Http\Controllers;

use App\Models\FinancialProgress;
use App\Models\PduProject;
use App\Models\ProjectLot;
use App\Services\AlerteService;
use App\Services\ProjectAggregationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinancialProgressController extends Controller
{
    public function __construct(
        protected ProjectAggregationService $agg,
        protected AlerteService $alerteService,
    ) {}

    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeWrite($request);

        $data = $this->validatePayload($request, $project);

        // Vérifie unicité de la période
        $exists = FinancialProgress::where('pdu_project_id', $project->id)
            ->where('period', $data['period'])
            ->when(
                $data['project_lot_id'] ?? null,
                fn ($q, $lotId) => $q->where('project_lot_id', $lotId),
                fn ($q) => $q->whereNull('project_lot_id'),
            )
            ->exists();
        if ($exists) {
            return back()->withErrors(['period' => 'Cette période existe déjà pour cet ouvrage.']);
        }

        FinancialProgress::create(array_merge($data, [
            'pdu_project_id' => $project->id,
            'recorded_by' => $request->user()->id,
            'status' => 'submitted',
        ]));

        $this->agg->recomputeFinancialCumulatives($project);
        $this->agg->recomputeProjectBudgetSpent($project);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Avancement financier enregistré.');
    }

    public function update(Request $request, PduProject $project, FinancialProgress $progress): RedirectResponse
    {
        abort_if($progress->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $data = $this->validatePayload($request, $project);

        $exists = FinancialProgress::where('pdu_project_id', $project->id)
            ->where('period', $data['period'])
            ->when(
                $data['project_lot_id'] ?? null,
                fn ($q, $lotId) => $q->where('project_lot_id', $lotId),
                fn ($q) => $q->whereNull('project_lot_id'),
            )
            ->where('id', '!=', $progress->id)->exists();
        if ($exists) {
            return back()->withErrors(['period' => 'Cette période existe déjà pour cet ouvrage.']);
        }

        $progress->update($data);

        $this->agg->recomputeFinancialCumulatives($project);
        $this->agg->recomputeProjectBudgetSpent($project);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Avancement financier mis à jour.');
    }

    public function destroy(Request $request, PduProject $project, FinancialProgress $progress): RedirectResponse
    {
        abort_if($progress->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $progress->delete();

        $this->agg->recomputeFinancialCumulatives($project);
        $this->agg->recomputeProjectBudgetSpent($project);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Relevé financier supprimé.');
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

    protected function validatePayload(Request $request, PduProject $project): array
    {
        // Comme pour l'avancement physique : si le projet a des ouvrages
        // d'avancement, le relevé doit y être rattaché — évite de mélanger des
        // relevés « par ouvrage » et « par projet » dans les cumuls (double comptage).
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
            'planned_value' => ['required', 'numeric', 'min:0'],
            'earned_value' => ['required', 'numeric', 'min:0'],
            'actual_cost' => ['required', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ], [
            'project_lot_id.required' => 'Ce projet comporte des lots : veuillez rattacher le relevé à un lot.',
        ]);
    }
}
