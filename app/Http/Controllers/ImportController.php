<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Services\Import\BaseDeCalculImporter;
use App\Services\Import\BaseDeCalculReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Import de la base de calcul d'avancement établie par la mission de contrôle.
 *
 * L'opération se fait en deux temps : une lecture qui ne touche à rien et rend
 * compte de ce qu'elle a reconnu, puis une écriture que l'utilisateur déclenche
 * en connaissance de cause. Le fichier déposé est conservé le temps de la
 * confirmation, puis effacé.
 */
class ImportController extends Controller
{
    public function __construct(
        protected BaseDeCalculReader $lecteur,
        protected BaseDeCalculImporter $importeur,
    ) {}

    /** Première étape : lecture du classeur et restitution de son contenu. */
    public function preview(Request $request, PduProject $project)
    {
        $this->autoriser($request);

        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xlsm,xls', 'max:20480'],
        ], [
            'fichier.mimes' => 'Le fichier attendu est la base de calcul au format Excel.',
            'fichier.max' => 'Le fichier ne doit pas dépasser 20 Mo.',
        ]);

        $chemin = $request->file('fichier')->store('imports');

        try {
            $lecture = $this->lecteur->read(Storage::path($chemin));
        } catch (RuntimeException $e) {
            Storage::delete($chemin);

            return back()->withErrors(['fichier' => $e->getMessage()]);
        }

        return back()->with([
            'import_apercu' => array_merge($this->importeur->preview($project, $lecture), [
                'fichier' => $chemin,
                'nom_fichier' => $request->file('fichier')->getClientOriginalName(),
            ]),
        ]);
    }

    /** Seconde étape : écriture de ce que le projet ne connaît pas encore. */
    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->autoriser($request);

        $data = $request->validate([
            'fichier' => ['required', 'string'],
        ]);

        if (! str_starts_with($data['fichier'], 'imports/') || ! Storage::exists($data['fichier'])) {
            return back()->withErrors(['fichier' => 'Le fichier déposé n’est plus disponible : reprenez l’import.']);
        }

        try {
            $lecture = $this->lecteur->read(Storage::path($data['fichier']));
        } catch (RuntimeException $e) {
            return back()->withErrors(['fichier' => $e->getMessage()]);
        } finally {
            Storage::delete($data['fichier']);
        }

        // Les décomptes relèvent de la section financière : l'import ne les
        // touche que si l'utilisateur en a la charge.
        $compte = $this->importeur->import(
            $project,
            $lecture,
            $request->user(),
            $request->user()->can('manage_finances'),
        );

        return back()->with('success', sprintf(
            'Base de calcul du %s importée : %d relevé(s), %d ouvrage(s) créé(s), %d décompte(s). Avancement consolidé : %s %%.',
            $compte['arrete_au'] ?? '—',
            $compte['releves'],
            $compte['ouvrages_crees'],
            $compte['decomptes'],
            number_format($compte['avancement'], 2, ',', ' '),
        ));
    }

    protected function autoriser(Request $request): void
    {
        abort_unless($request->user()?->can('manage_physical'), 403);
    }
}
