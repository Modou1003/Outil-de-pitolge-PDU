<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BuildingWork;
use App\Models\PduProject;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Journal d'activité : traçabilité des interventions métier et consultation
 * réservée à l'administration.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function project(): PduProject
    {
        $university = University::create(['name' => 'Université de Test', 'acronym' => 'UT', 'region' => 'Abidjan']);

        return PduProject::create([
            'code' => 'PRJ-LOG',
            'title' => 'Projet journal',
            'university_id' => $university->id,
            'created_by' => User::factory()->create()->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => 100000000,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');

        return $user;
    }

    public function test_une_saisie_davancement_physique_est_journalisee(): void
    {
        $user = $this->admin();
        $project = $this->project();
        $work = BuildingWork::create([
            'pdu_project_id' => $project->id, 'code' => 'OUV-01',
            'name' => 'Bâtiment', 'weight_percentage' => 100,
        ]);

        $this->actingAs($user)->post(route('projects.physical.store', $project), [
            'building_work_id' => $work->id,
            'period' => '2026-03',
            'measurement_date' => '2026-03-31',
            'planned_percentage' => 40,
            'actual_percentage' => 35,
        ])->assertRedirect();

        $log = ActivityLog::where('model', 'PhysicalProgress')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('created', $log->action);
        $this->assertSame($project->id, $log->pdu_project_id);
        $this->assertStringContainsString('2026-03', $log->description);
    }

    public function test_un_avenant_est_journalise_avec_son_montant_et_son_delai(): void
    {
        $user = $this->admin();
        $project = $this->project();

        $this->actingAs($user)->post(route('projects.amendments.store', $project), [
            'number' => 'AV-01',
            'object' => 'Travaux supplémentaires',
            'amount' => 5000000,
            'duration_days' => 30,
        ])->assertRedirect();

        $log = ActivityLog::where('model', 'ProjectAmendment')->firstOrFail();
        $this->assertSame('created', $log->action);
        $this->assertStringContainsString('AV-01', $log->description);
        $this->assertStringContainsString('+30', $log->description);
    }

    public function test_une_suppression_est_journalisee_avant_disparition(): void
    {
        $user = $this->admin();
        $project = $this->project();

        $this->actingAs($user)->post(route('projects.payments.store', $project), [
            'number' => 'D-01', 'gross_amount' => 1000000, 'net_paid' => 900000,
        ])->assertRedirect();

        $payment = \App\Models\ProjectPayment::firstOrFail();
        $this->actingAs($user)->delete(route('projects.payments.destroy', [$project, $payment]))->assertRedirect();

        $this->assertSame(1, ActivityLog::where('model', 'ProjectPayment')->where('action', 'deleted')->count());
        $this->assertStringContainsString('D-01', ActivityLog::where('action', 'deleted')->first()->description);
    }

    public function test_ladministrateur_consulte_le_journal(): void
    {
        $user = $this->admin();
        $project = $this->project();

        $this->actingAs($user)->post(route('projects.amendments.store', $project), [
            'number' => 'AV-01', 'amount' => 5000000,
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('admin.activites.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ActivityLog')
                ->has('logs.data', 1)
                ->where('logs.data.0.model_label', 'Avenant')
                ->where('logs.data.0.action_label', 'Création')
                ->where('logs.data.0.user', $user->name));
    }

    public function test_le_journal_se_filtre_par_type(): void
    {
        $user = $this->admin();
        $project = $this->project();

        $this->actingAs($user)->post(route('projects.amendments.store', $project), ['number' => 'AV-01', 'amount' => 5000000]);
        $this->actingAs($user)->post(route('projects.payments.store', $project), ['number' => 'D-01', 'gross_amount' => 100, 'net_paid' => 100]);

        $this->actingAs($user)
            ->get(route('admin.activites.index', ['model' => 'ProjectAmendment']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }

    public function test_un_non_administrateur_na_pas_acces_au_journal(): void
    {
        $intrus = User::factory()->create(['is_active' => true]);
        $intrus->assignRole('chef_projet');

        $this->actingAs($intrus)
            ->get(route('admin.activites.index'))
            ->assertForbidden();
    }
}
