<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\ThresholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paramétrage des seuils de déclenchement des alertes et des bornes de
 * coloration des indicateurs.
 */
class ThresholdController extends Controller
{
    public function __construct(
        protected ThresholdService $seuils,
        protected ActivityLogger $journal,
    ) {}

    public function index(): Response
    {
        $valeurs = $this->seuils->all();
        $modifie = Setting::with('editor:id,name')->get()->keyBy('key');

        $champs = collect(ThresholdService::CATALOG)
            ->map(function (array $meta, string $cle) use ($valeurs, $modifie) {
                // get() plutôt qu'un accès direct : la clé peut être absente
                // de la table comme de la collection des valeurs enregistrées.
                $reglage = $modifie->get($cle);

                return array_merge($meta, [
                    'key' => $cle,
                    'value' => $valeurs[$cle] ?? (float) $meta['default'],
                    'is_default' => $reglage === null,
                    'updated_by' => $reglage?->editor?->name,
                    'updated_at' => $reglage?->updated_at?->toIso8601String(),
                ]);
            })
            ->values();

        return Inertia::render('Admin/Thresholds', [
            'groups' => $champs->groupBy('group')->map(fn ($items, $nom) => [
                'name' => $nom,
                'fields' => $items->values()->all(),
            ])->values()->all(),
            'modified_count' => $modifie->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $regles = [];
        foreach (ThresholdService::CATALOG as $cle => $meta) {
            $regles[$cle] = ['nullable', 'numeric', 'min:' . $meta['min'], 'max:' . $meta['max']];
        }

        $valeurs = $request->validate($regles);
        $avant = $this->seuils->all();

        $this->seuils->save($valeurs, $request->user()?->id);

        // Ne journaliser que ce qui a effectivement bougé.
        $apres = $this->seuils->all();
        $changes = [];
        foreach ($apres as $cle => $valeur) {
            if (abs($valeur - ($avant[$cle] ?? $valeur)) > 0.0001) {
                $changes[$cle] = ['de' => $avant[$cle], 'a' => $valeur];
            }
        }

        if ($changes) {
            $this->journal->updated(
                'Setting',
                sprintf('%d seuil(s) modifié(s) : %s', count($changes), implode(', ', array_keys($changes))),
                null, null, $changes,
            );
        }

        return back()->with('success', $changes
            ? count($changes) . ' seuil(s) mis à jour.'
            : 'Aucune modification.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->seuils->reset();

        $this->journal->updated(
            'Setting',
            'Seuils rétablis à leurs valeurs par défaut',
        );

        return back()->with('success', 'Seuils rétablis aux valeurs par défaut.');
    }
}
