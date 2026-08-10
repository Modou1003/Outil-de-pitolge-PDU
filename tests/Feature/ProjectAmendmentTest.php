<?php

namespace Tests\Feature;

use App\Models\PduProject;
use App\Models\ProjectAmendment;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAmendmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeContext(): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');

        $university = University::create([
            'name' => 'Université de Test',
            'acronym' => 'UT',
            'region' => 'Abidjan',
        ]);

        $project = PduProject::create([
            'code' => 'PRJ-TEST',
            'title' => 'Projet de test',
            'university_id' => $university->id,
            'created_by' => $user->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => 100000000,
        ]);

        return [$user, $project];
    }

    public function test_le_montant_initial_reste_intact_et_lactualise_suit_les_avenants(): void
    {
        [$user, $project] = $this->makeContext();

        // Sans avenant : actualisé = initial.
        $this->assertSame(100000000.0, $project->budget_revised);

        // Création via la route réelle.
        $this->actingAs($user)
            ->post(route('projects.amendments.store', $project), [
                'number' => 'AV-01',
                'object' => 'Travaux supplémentaires',
                'signed_date' => '2026-08-01',
                'amount' => 25000000,
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame(100000000.0, (float) $project->budget_allocated, 'Le montant initial doit rester intact');
        $this->assertSame(25000000.0, $project->amendments_total);
        $this->assertSame(125000000.0, $project->budget_revised);

        // Un avenant en moins-value réduit le marché actualisé.
        $this->actingAs($user)
            ->post(route('projects.amendments.store', $project), [
                'number' => 'AV-02',
                'amount' => -5000000,
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame(20000000.0, $project->amendments_total);
        $this->assertSame(120000000.0, $project->budget_revised);
        $this->assertSame(100000000.0, (float) $project->budget_allocated);
    }

    public function test_les_taux_financiers_sont_calcules_sur_le_montant_actualise(): void
    {
        [$user, $project] = $this->makeContext();
        $project->update(['budget_spent' => 60000000]);

        // 60 M / 100 M = 60 %
        $this->assertSame(60.0, $project->budget_execution_rate);

        ProjectAmendment::create([
            'pdu_project_id' => $project->id,
            'number' => 'AV-01',
            'amount' => 50000000,
        ]);

        // 60 M / 150 M = 40 %
        $project->refresh();
        $this->assertSame(40.0, $project->budget_execution_rate);
        $this->assertSame(90000000.0, $project->remaining_budget);

        $moa = $project->financialMoa();
        $this->assertSame(150000000.0, $moa['budget']);
        $this->assertSame(100000000.0, $moa['budget_initial']);
        $this->assertSame(50000000.0, $moa['amendments_total']);
        $this->assertSame(1, $moa['amendments_count']);
    }

    public function test_la_page_projet_expose_les_avenants(): void
    {
        [$user, $project] = $this->makeContext();

        ProjectAmendment::create([
            'pdu_project_id' => $project->id,
            'number' => 'AV-01',
            'object' => 'Extension',
            'amount' => 10000000,
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('amendments', 1)
                ->where('amendments.0.number', 'AV-01')
                // Le JSON sérialise ces décimaux sans partie fractionnaire.
                ->where('project.budget_allocated', 100000000)
                ->where('project.amendments_total', 10000000)
                ->where('project.budget_revised', 110000000));
    }

    public function test_la_date_de_fin_initiale_reste_intacte_et_lactualisee_suit_les_avenants(): void
    {
        [$user, $project] = $this->makeContext();

        // Sans avenant : date actualisée = date initiale (fin 2026-12-31).
        $this->assertSame('2026-12-31', $project->planned_end_date_revised->toDateString());

        $this->actingAs($user)
            ->post(route('projects.amendments.store', $project), [
                'number' => 'AV-01',
                'object' => 'Prolongation de délai',
                'amount' => 0,
                'duration_days' => 90,
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('2026-12-31', $project->end_date->toDateString(), 'La date de fin initiale doit rester intacte');
        $this->assertSame(90, $project->amendments_duration_days);
        $this->assertSame('2027-03-31', $project->planned_end_date_revised->toDateString());

        // Une réduction de délai ramène la date en arrière.
        $this->actingAs($user)
            ->post(route('projects.amendments.store', $project), [
                'number' => 'AV-02',
                'amount' => 0,
                'duration_days' => -30,
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame(60, $project->amendments_duration_days);
        $this->assertSame('2027-03-01', $project->planned_end_date_revised->toDateString());
        $this->assertSame('2026-12-31', $project->planned_end_date->toDateString());
    }

    public function test_un_avenant_de_delai_repousse_la_date_de_retard(): void
    {
        [, $project] = $this->makeContext();
        // Projet dont l'échéance initiale est déjà dépassée.
        $project->update(['end_date' => now()->subDays(10)->toDateString()]);

        $project->refresh();
        $this->assertTrue($project->is_overdue, 'Sans avenant, le projet est en retard');

        ProjectAmendment::create([
            'pdu_project_id' => $project->id,
            'number' => 'AV-01',
            'amount' => 0,
            'duration_days' => 60,
        ]);

        $project->refresh();
        $this->assertFalse($project->is_overdue, "L'avenant de délai repousse l'échéance");
    }

    public function test_un_avenant_peut_porter_montant_et_delai(): void
    {
        [$user, $project] = $this->makeContext();

        $this->actingAs($user)
            ->post(route('projects.amendments.store', $project), [
                'number' => 'AV-01',
                'object' => 'Travaux supplémentaires avec prolongation',
                'amount' => 20000000,
                'duration_days' => 45,
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame(120000000.0, $project->budget_revised);
        $this->assertSame(45, $project->amendments_duration_days);
        $this->assertSame('2027-02-14', $project->planned_end_date_revised->toDateString());
    }

    public function test_un_avenant_peut_ne_porter_quun_delai_sans_montant(): void
    {
        [$user, $project] = $this->makeContext();

        // Aucun champ « amount » envoyé : l'avenant ne porte qu'un délai.
        $this->actingAs($user)
            ->post(route('projects.amendments.store', $project), [
                'number' => 'AV-DELAI',
                'object' => 'Prolongation pour intempéries',
                'duration_days' => 120,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(0.0, $project->amendments_total);
        $this->assertSame(100000000.0, $project->budget_revised, 'Le montant du marché ne bouge pas');
        $this->assertSame(120, $project->amendments_duration_days);
        $this->assertSame('2027-04-30', $project->planned_end_date_revised->toDateString());
    }

    public function test_un_avenant_peut_ne_porter_quun_montant_sans_delai(): void
    {
        [$user, $project] = $this->makeContext();

        $this->actingAs($user)
            ->post(route('projects.amendments.store', $project), [
                'number' => 'AV-MONTANT',
                'amount' => 15000000,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(115000000.0, $project->budget_revised);
        $this->assertSame(0, $project->amendments_duration_days);
        $this->assertSame('2026-12-31', $project->planned_end_date_revised->toDateString());
    }

    public function test_un_avenant_sans_montant_ni_delai_est_refuse(): void
    {
        [$user, $project] = $this->makeContext();

        $this->actingAs($user)
            ->post(route('projects.amendments.store', $project), [
                'number' => 'AV-VIDE',
                'amount' => 0,
                'duration_days' => 0,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, ProjectAmendment::count());
    }

    public function test_la_fin_projetee_se_compare_a_lecheance_actualisee(): void
    {
        [$user, $project] = $this->makeContext();
        // Chantier à mi-parcours mais en retard sur sa courbe planifiée.
        $start = now()->subDays(300)->startOfDay();
        $project->update(['start_date' => $start->toDateString(), 'progress_percentage' => 50]);

        // L'Earned Schedule a besoin d'une courbe planifiée : sans elle, aucune
        // projection n'est affichée (c'est le comportement voulu).
        $work = \App\Models\BuildingWork::create([
            'pdu_project_id' => $project->id,
            'code' => 'OUV-01',
            'name' => 'Ouvrage principal',
            'weight_percentage' => 100,
        ]);
        foreach ([0 => 0, 100 => 25, 200 => 50, 300 => 75] as $day => $planned) {
            \App\Models\PhysicalProgress::create([
                'pdu_project_id' => $project->id,
                'building_work_id' => $work->id,
                'period' => $start->copy()->addDays($day)->format('Y-m'),
                'measurement_date' => $start->copy()->addDays($day)->toDateString(),
                'planned_percentage' => $planned,
                'actual_percentage' => 50,
            ]);
        }

        $before = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->viewData('page')['props']['kpis']['forecast_completion'];

        $this->assertSame('earned_schedule', $before['method']);
        $this->assertNotNull($before['delay_days']);
        $this->assertSame('2026-12-31', $before['planned_end_date']);

        // Un avenant de 200 jours repousse l'échéance contractuelle d'autant.
        ProjectAmendment::create([
            'pdu_project_id' => $project->id,
            'number' => 'AV-01',
            'amount' => 0,
            'duration_days' => 200,
        ]);

        $after = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->viewData('page')['props']['kpis']['forecast_completion'];

        $this->assertSame('2027-07-19', $after['planned_end_date'], "L'échéance de référence suit l'avenant");
        $this->assertSame(
            $before['projected_end_date'],
            $after['projected_end_date'],
            "La date projetée découle de la performance constatée, que l'avenant ne modifie pas",
        );
        $this->assertSame($before['delay_days'] - 200, $after['delay_days'], "Le retard se réduit de la prolongation");
    }

    public function test_la_suppression_ramene_au_montant_initial(): void
    {
        [$user, $project] = $this->makeContext();

        $amendment = ProjectAmendment::create([
            'pdu_project_id' => $project->id,
            'number' => 'AV-01',
            'amount' => 25000000,
        ]);

        $this->actingAs($user)
            ->delete(route('projects.amendments.destroy', [$project, $amendment]))
            ->assertRedirect();

        $project->refresh();
        $this->assertSame(0.0, $project->amendments_total);
        $this->assertSame(100000000.0, $project->budget_revised);
    }

    public function test_un_utilisateur_sans_droit_financier_ne_peut_pas_creer_davenant(): void
    {
        [, $project] = $this->makeContext();

        $intruder = User::factory()->create(['is_active' => true]);

        $this->actingAs($intruder)
            ->post(route('projects.amendments.store', $project), [
                'number' => 'AV-99',
                'amount' => 1000000,
            ])
            ->assertForbidden();

        $this->assertSame(0, ProjectAmendment::count());
    }
}
