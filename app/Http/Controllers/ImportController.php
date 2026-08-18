<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Services\Import\BaseDeCalculImporter;
use App\Services\Import\BaseDeCalculReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

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

        // Le fichier fraîchement déposé est lu à son emplacement temporaire,
        // toujours local ; il n'est conservé que pour l'étape de confirmation.
        $depose = $request->file('fichier');
        $chemin = $depose->store('imports');

        try {
            $lecture = $this->lecteur->read($depose->getRealPath());
        } catch (Throwable $e) {
            // Toute défaillance est rendue lisible : une lecture qui échoue en
            // silence laisserait l'utilisateur devant un écran inchangé.
            Storage::delete($chemin);
            Log::error('Lecture de la base de calcul impossible', ['exception' => $e]);

            return back()->withErrors(['fichier' => $this->message($e)]);
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

        $local = null;

        try {
            // Le disque configuré n'est pas nécessairement local : le classeur
            // est rapatrié dans un fichier temporaire avant d'être lu.
            $local = tempnam(sys_get_temp_dir(), 'bdc') . '.xlsx';
            file_put_contents($local, Storage::get($data['fichier']));

            $lecture = $this->lecteur->read($local);
        } catch (Throwable $e) {
            Log::error('Import de la base de calcul impossible', ['exception' => $e]);

            return back()->withErrors(['fichier' => $this->message($e)]);
        } finally {
            if ($local && is_file($local)) {
                @unlink($local);
            }
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

    /**
     * Message destiné à l'utilisateur. Les défaillances techniques sont
     * traduites plutôt que renvoyées telles quelles, sauf celles que le lecteur
     * a lui-même formulées à son intention.
     */
    protected function message(Throwable $e): string
    {
        if ($e instanceof RuntimeException) {
            return $e->getMessage();
        }

        if (! class_exists(\ZipArchive::class)) {
            return "La lecture de classeurs Excel est indisponible sur ce serveur : l'extension PHP « zip » n'y est pas activée.";
        }

        return 'Le fichier n’a pas pu être lu. Vérifiez qu’il s’agit bien de la base de calcul d’avancement, au format Excel, et qu’il n’est pas protégé par un mot de passe.';
    }

    protected function autoriser(Request $request): void
    {
        abort_unless($request->user()?->can('manage_physical'), 403);
    }
}
