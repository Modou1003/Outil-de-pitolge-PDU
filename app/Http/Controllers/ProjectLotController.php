<?php

namespace App\Http\Controllers;

use App\Models\FinancialProgress;
use App\Models\PduProject;
use App\Models\ProjectLot;
use App\Services\ProjectAggregationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectLotController extends Controller
{
    public function __construct(protected ProjectAggregationService $agg) {}

    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request, $project);

        // Un lot neuf n'a pas encore de saisie physique : avancement à 0 et
        // statut rendu cohérent avec cet avancement.
        $data['status'] = $this->coherentStatus($data['status'], (float) ($data['progress_percentage'] ?? 0));

        $lot = ProjectLot::create(array_merge($data, [
            'pdu_project_id' => $project->id,
        ]));

        $this->agg->recomputeProjectProgress($project);

        return back()->with('success', "Lot « {$lot->name} » créé.");
    }

    public function update(Request $request, PduProject $project, ProjectLot $lot): RedirectResponse
    {
        abort_if($lot->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request, $project, $lot->id);

        // Le statut doit rester cohérent avec l'avancement réel (dérivé des
        // saisies physiques), pas avec une valeur arbitraire du formulaire.
        $data['status'] = $this->coherentStatus($data['status'], (float) $lot->progress_percentage);

        $lot->update($data);
        $this->agg->recomputeProjectProgress($project);

        return back()->with('success', 'Lot mis à jour.');
    }

    /**
     * Rend le statut cohérent avec l'avancement du lot.
     * « En pause » et « Annulé » sont des états de planning conservés tels quels.
     */
    protected function coherentStatus(string $status, float $progress): string
    {
        if (in_array($status, ['on_hold', 'cancelled'], true)) {
            return $status;
        }
        if ($progress >= 100) {
            return 'completed';
        }
        if ($progress <= 0) {
            return $status === 'completed' ? 'not_started' : $status;
        }
        // 0 < avancement < 100 : ne peut pas être « terminé ».
        return $status === 'completed' ? 'in_progress' : $status;
    }

    public function destroy(Request $request, PduProject $project, ProjectLot $lot): RedirectResponse
    {
        abort_if($lot->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        // Supprime explicitement les relevés financiers du lot : sans cela, la
        // FK nullOnDelete les transformerait en relevés « projet » orphelins,
        // faussant les cumuls financiers. (Les relevés physiques, eux, sont
        // supprimés en cascade par la base.)
        FinancialProgress::where('project_lot_id', $lot->id)->delete();

        $lot->delete();

        $this->agg->recomputeProjectProgress($project);
        $this->agg->recomputeFinancialCumulatives($project);
        $this->agg->recomputeProjectBudgetSpent($project);

        return back()->with('success', 'Lot supprimé.');
    }

    public function updateProgress(Request $request, PduProject $project, ProjectLot $lot): RedirectResponse
    {
        abort_if($lot->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $data = $request->validate([
            'progress_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'in:not_started,in_progress,on_hold,completed,cancelled'],
        ]);

        $lot->progress_percentage = $data['progress_percentage'];
        if (! empty($data['status'])) $lot->status = $data['status'];
        $lot->save();

        $this->agg->recomputeProjectProgress($project);

        return back()->with('success', "Avancement du lot {$lot->code} mis à jour.");
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
        $projectStart = $project->start_date?->toDateString();

        // Borne temporelle basse : un lot ne peut pas commencer/finir avant le
        // début du projet. (Le dépassement de la fin est autorisé : c'est un
        // signal de retard, révélé par les indicateurs, pas une erreur de saisie.)
        $plannedStartRules = ['nullable', 'date'];
        $plannedEndRules = ['nullable', 'date', 'after_or_equal:planned_start_date'];
        if ($projectStart) {
            $plannedStartRules[] = 'after_or_equal:' . $projectStart;
            $plannedEndRules[] = 'after_or_equal:' . $projectStart;
        }

        $rules = [
            'building_work_id' => ['nullable', 'exists:building_works,id'],
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'weight_percentage' => [
                'required', 'numeric', 'min:0', 'max:100',
                // La somme des pondérations de tous les lots du projet ne peut dépasser 100 %.
                function ($attribute, $value, $fail) use ($project, $ignoreId) {
                    $others = (float) ProjectLot::where('pdu_project_id', $project->id)
                        ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                        ->sum('weight_percentage');
                    if ($others + (float) $value > 100.001) {
                        $fail(sprintf(
                            'La somme des pondérations des lots dépasserait 100 %% (%.1f %% déjà attribués aux autres lots).',
                            $others,
                        ));
                    }
                },
            ],
            'planned_start_date' => $plannedStartRules,
            'planned_end_date' => $plannedEndRules,
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
            'progress_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:not_started,in_progress,on_hold,completed,cancelled'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        return $request->validate($rules, [
            'planned_start_date.after_or_equal' => 'La date de début du lot doit être postérieure ou égale au début du projet.',
            'planned_end_date.after_or_equal' => 'La date de fin du lot doit être cohérente avec le début du lot et du projet.',
        ]);
    }
}
