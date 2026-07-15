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
        $data = $this->validatePayload($request);

        // Auto-generate code
        $maxCode = BuildingWork::where('pdu_project_id', $project->id)
            ->get()
            ->map(fn($w) => (int) preg_replace('/[^0-9]/', '', $w->code))
            ->max() ?? 0;
        $data['code'] = 'OUV-' . str_pad($maxCode + 1, 3, '0', STR_PAD_LEFT);
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
        $data = $this->validatePayload($request);

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

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:not_started,in_progress,on_hold,completed,cancelled'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
