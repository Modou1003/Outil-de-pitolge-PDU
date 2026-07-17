<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\ProjectPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectPaymentController extends Controller
{
    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request);

        ProjectPayment::create(array_merge($data, [
            'pdu_project_id' => $project->id,
            'recorded_by' => $request->user()->id,
        ]));

        return back()->with('success', 'Décompte enregistré.');
    }

    public function update(Request $request, PduProject $project, ProjectPayment $payment): RedirectResponse
    {
        abort_if($payment->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request);

        $payment->update($data);

        return back()->with('success', 'Décompte mis à jour.');
    }

    public function destroy(Request $request, PduProject $project, ProjectPayment $payment): RedirectResponse
    {
        abort_if($payment->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $payment->delete();

        return back()->with('success', 'Décompte supprimé.');
    }

    public function updateAdvances(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'startup_advance_amount' => ['required', 'numeric', 'min:0'],
            'supply_advance_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $project->update($data);

        return back()->with('success', 'Avances mises à jour.');
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

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'number' => ['required', 'string', 'max:40'],
            'period' => ['nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'payment_date' => ['nullable', 'date'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'startup_advance_recovery' => ['nullable', 'numeric', 'min:0'],
            'supply_advance_recovery' => ['nullable', 'numeric', 'min:0'],
            'net_paid' => ['required', 'numeric', 'min:0'],
            'is_paid' => ['nullable', 'boolean'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
