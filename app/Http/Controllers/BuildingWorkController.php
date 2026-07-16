<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\BuildingWork;
use App\Services\AlerteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BuildingWorkController extends Controller
{
    public function __construct(protected AlerteService $alerteService) {}

    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request, $project);

        // Auto-generate code — la contrainte d'unicité est GLOBALE, on calcule
        // donc le prochain numéro sur toute la table (tous projets confondus),
        // avec une boucle de sécurité pour éviter toute collision résiduelle.
        $maxCode = BuildingWork::query()
            ->get()
            ->map(fn($w) => (int) preg_replace('/[^0-9]/', '', $w->code))
            ->max() ?? 0;
        $next = $maxCode + 1;
        do {
            $code = 'OUV-' . str_pad($next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (BuildingWork::where('code', $code)->exists());
        $data['code'] = $code;
        $data['status'] = $data['status'] ?? 'not_started';

        BuildingWork::create(array_merge($data, [
            'pdu_project_id' => $project->id,
        ]));
        $this->alerteService->generateForAll();

        return back()->with('success', "Ouvrage créé : {$data['code']}");
    }

    public function update(Request $request, PduProject $project, BuildingWork $work): RedirectResponse
    {
        abort_if($work->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request, $project, $work->id);

        if (empty($data['status'])) unset($data['status']);
        $work->update($data);
        $this->alerteService->generateForAll();

        return back()->with('success', 'Ouvrage mis à jour.');
    }

    public function destroy(Request $request, PduProject $project, BuildingWork $work): RedirectResponse
    {
        abort_if($work->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $work->delete();
        $this->alerteService->generateForAll();

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
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:not_started,in_progress,on_hold,completed,cancelled'],
            'weight_percentage' => [
                'nullable', 'numeric', 'min:0', 'max:100',
                // La somme des pondérations des ouvrages ne peut dépasser 100 %.
                function ($attribute, $value, $fail) use ($project, $ignoreId) {
                    $others = (float) BuildingWork::where('pdu_project_id', $project->id)
                        ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                        ->sum('weight_percentage');
                    if ($others + (float) $value > 100.001) {
                        $fail(sprintf(
                            'La somme des pondérations des ouvrages dépasserait 100 %% (%.1f %% déjà attribués).',
                            $others,
                        ));
                    }
                },
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
