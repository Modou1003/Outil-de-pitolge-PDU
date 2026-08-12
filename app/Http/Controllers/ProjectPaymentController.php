<?php

namespace App\Http\Controllers;

use App\Models\PduProject;
use App\Models\ProjectPayment;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectPaymentController extends Controller
{
    public function __construct(protected ActivityLogger $journal) {}

    public function store(Request $request, PduProject $project): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request);

        $payment = ProjectPayment::create(array_merge($data, [
            'pdu_project_id' => $project->id,
            'recorded_by' => $request->user()->id,
        ]));

        $this->journal->created(
            'ProjectPayment',
            sprintf('Décompte n° %s enregistré (brut %s)', $data['number'], number_format((float) $data['gross_amount'], 0, ',', ' ')),
            $payment, $project->id,
        );

        return back()->with('success', 'Décompte enregistré.');
    }

    public function update(Request $request, PduProject $project, ProjectPayment $payment): RedirectResponse
    {
        abort_if($payment->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);
        $data = $this->validatePayload($request);

        $payment->update($data);

        $this->journal->updated(
            'ProjectPayment',
            sprintf('Décompte n° %s modifié', $data['number']),
            $payment, $project->id,
        );

        return back()->with('success', 'Décompte mis à jour.');
    }

    public function destroy(Request $request, PduProject $project, ProjectPayment $payment): RedirectResponse
    {
        abort_if($payment->pdu_project_id !== $project->id, 404);
        $this->authorizeWrite($request);

        $this->journal->deleted(
            'ProjectPayment',
            sprintf('Décompte n° %s supprimé', $payment->number),
            $payment, $project->id,
        );

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

        $this->journal->updated(
            'ProjectAdvance',
            sprintf(
                'Avances contractuelles fixées : démarrage %s, approvisionnement %s',
                number_format((float) $data['startup_advance_amount'], 0, ',', ' '),
                number_format((float) $data['supply_advance_amount'], 0, ',', ' '),
            ),
            $project, $project->id,
        );

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
