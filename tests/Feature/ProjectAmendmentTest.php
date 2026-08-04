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
