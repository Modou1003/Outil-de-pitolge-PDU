<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\PduProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public const CATEGORIES = [
        'etude' => 'Études techniques',
        'contrat' => 'Contrats & marchés',
        'photo' => 'Photos chantier',
        'pv' => 'PV de réception',
        'rapport' => 'Rapports & audits',
        'autre' => 'Autre',
    ];

    protected const MAX_SIZE_KB = 20480; // 20 Mo

    protected const ALLOWED_MIMES = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'csv', 'txt', 'zip',
    ];

    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeUpload($request);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:' . self::MAX_SIZE_KB, 'mimes:' . implode(',', self::ALLOWED_MIMES)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', 'in:' . implode(',', array_keys(self::CATEGORIES))],
            'visibility' => ['nullable', 'in:public,internal,confidential,restricted'],
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $path = $file->store("documents/projects/{$project->id}", 'public');

        $project->documents()->create([
            'title' => $data['title'] ?: pathinfo($originalName, PATHINFO_FILENAME),
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'file_name' => $originalName,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'category' => $data['category'],
            'visibility' => $data['visibility'] ?? 'internal',
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        return back()->with('success', "Document « {$originalName} » téléversé.");
    }

    public function download(Request $request, PduProject $project, Document $document): BinaryFileResponse|StreamedResponse
    {
        $this->authorizeBelongsTo($document, $project);

        abort_unless(Storage::disk('public')->exists($document->file_path), 404, 'Fichier introuvable.');

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    public function destroy(Request $request, PduProject $project, Document $document): RedirectResponse
    {
        $this->authorizeBelongsTo($document, $project);
        $this->authorizeDelete($request, $document);

        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }
        $document->delete();

        return back()->with('success', 'Document supprimé.');
    }

    protected function authorizeUpload(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && $user->hasAnyRole(['admin', 'directeur', 'chef_projet', 'agent_financier']),
            403,
            'Vous n\'êtes pas autorisé à téléverser des documents.',
        );
    }

    protected function authorizeDelete(Request $request, Document $document): void
    {
        $user = $request->user();
        // Admin / directeur peuvent tout supprimer ; l'uploader peut supprimer son propre fichier.
        $isPrivileged = $user && $user->hasAnyRole(['admin', 'directeur']);
        $isOwner = $user && $document->uploaded_by === $user->id;

        abort_unless($isPrivileged || $isOwner, 403, 'Seul l\'uploader ou un administrateur peut supprimer.');
    }

    protected function authorizeBelongsTo(Document $document, PduProject $project): void
    {
        abort_unless(
            $document->documentable_type === PduProject::class && $document->documentable_id === $project->id,
            404,
        );
    }
}
