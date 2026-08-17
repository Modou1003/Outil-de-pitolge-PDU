<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\BuildingWork;
use App\Models\PduProject;
use App\Models\University;
use App\Models\User;
use App\Services\AlerteService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Calendrier contractuel de l'ouvrage et détection du retard au démarrage.
 *
 * Un ouvrage qui devait commencer en avril et démarre en décembre pèse sur
 * l'avancement du projet sans que rien n'en désigne la cause : c'est ce que
 * ces règles rendent visible.
 */
class WorkScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function project(): PduProject
    {
        $university = University::create(['name' => 'Université de Test', 'acronym' => 'UT', 'region' => 'Abidjan']);

        return PduProject::create([
            'code' => 'PRJ-PLAN',
            'title' => 'Projet planning',
            'university_id' => $university->id,
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subMonths(12)->toDateString(),
            'end_date' => now()->addMonths(12)->toDateString(),
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => 100000000,
        ]);
    }

    private function work(PduProject $project, array $attributs = []): BuildingWork
    {
        return BuildingWork::create(array_merge([
            'pdu_project_id' => $project->id,
            'code' => 'OUV-' . uniqid(),
            'name' => 'Bâtiment',
            'weight_percentage' => 100,
        ], $attributs));
    }

    private function typesAlertes(PduProject $project): array
    {
        app(ThresholdService::class)->forget();
        app(AlerteService::class)->generateForProject(
            $project->fresh(['physicalProgresses', 'financialProgresses', 'buildingWorks', 'lots', 'milestones', 'amendments', 'payments'])
        );

        return Alert::where('pdu_project_id', $project->id)->where('is_resolved', false)->pluck('type')->all();
    }

    // ------------------------------------------------------ retard au démarrage

    public function test_le_retard_se_mesure_entre_le_debut_prevu_et_le_debut_reel(): void
    {
        $work = $this->work($this->project(), [
            'planned_start_date' => '2025-04-18',
            'actual_start_date' => '2025-12-01',
        ]);

        // Du 18 avril au 1er décembre 2025.
        $this->assertSame(227, $work->start_delay_days);
        $this->assertFalse($work->is_start_overdue, 'Un ouvrage démarré n’est plus en attente de démarrage.');
    }

    public function test_un_ouvrage_non_demarre_accumule_le_retard_jusqua_aujourdhui(): void
    {
        $work = $this->work($this->project(), [
            'planned_start_date' => now()->subDays(120)->toDateString(),
        ]);

        $this->assertSame(120, $work->start_delay_days);
        $this->assertTrue($work->is_start_overdue);
    }

    public function test_un_demarrage_anticipe_ne_compte_pas_comme_un_retard(): void
    {
        $work = $this->work($this->project(), [
            'planned_start_date' => '2025-06-01',
            'actual_start_date' => '2025-05-10',
        ]);

        $this->assertSame(0, $work->start_delay_days);
    }

    public function test_sans_date_prevue_le_retard_nest_pas_calculable(): void
    {
        $this->assertNull($this->work($this->project())->start_delay_days);
    }

    // ---------------------------------------------------------------- alerte

    public function test_un_ouvrage_non_demarre_declenche_une_alerte(): void
    {
        $project = $this->project();
        $this->work($project, ['planned_start_date' => now()->subDays(60)->toDateString()]);

        $this->assertContains('work_start_delay', $this->typesAlertes($project));

        $alerte = Alert::where('type', 'work_start_delay')->firstOrFail();
        $this->assertSame('warning', $alerte->severity);
        $this->assertSame(60, $alerte->context['worst_delay_days']);
    }

    public function test_un_retard_de_plus_dun_trimestre_est_critique(): void
    {
        $project = $this->project();
        $this->work($project, ['planned_start_date' => now()->subDays(200)->toDateString()]);

        $this->typesAlertes($project);

        $this->assertSame('critical', Alert::where('type', 'work_start_delay')->firstOrFail()->severity);
    }

    public function test_la_tolerance_evite_de_signaler_un_leger_decalage(): void
    {
        $project = $this->project();
        // Tolérance par défaut de 15 jours.
        $this->work($project, ['planned_start_date' => now()->subDays(10)->toDateString()]);

        $this->assertNotContains('work_start_delay', $this->typesAlertes($project));
    }

    public function test_la_tolerance_est_parametrable(): void
    {
        $project = $this->project();
        $this->work($project, ['planned_start_date' => now()->subDays(10)->toDateString()]);
        $this->assertNotContains('work_start_delay', $this->typesAlertes($project));

        app(ThresholdService::class)->save(['work_start_delay_days' => 5]);
        $this->assertContains('work_start_delay', $this->typesAlertes($project));
    }

    public function test_lalerte_se_referme_au_demarrage_de_louvrage(): void
    {
        $project = $this->project();
        $work = $this->work($project, ['planned_start_date' => now()->subDays(60)->toDateString()]);
        $this->assertContains('work_start_delay', $this->typesAlertes($project));

        $work->update(['actual_start_date' => now()->toDateString()]);

        $this->assertNotContains('work_start_delay', $this->typesAlertes($project));
    }

    // ------------------------------------------------------------- saisie

    public function test_le_calendrier_se_saisit_depuis_la_fiche_projet(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('chef_projet');
        $project = $this->project();
        $work = $this->work($project);

        $this->actingAs($user)
            ->put(route('projects.building-works.update', [$project, $work]), [
                'name' => 'Bâtiment présidence',
                'duration_days' => 348,
                'planned_start_date' => '2025-02-10',
                'planned_end_date' => '2026-05-04',
                'actual_start_date' => '2025-03-01',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $work->refresh();
        $this->assertSame(348, $work->duration_days);
        $this->assertSame('2025-03-01', $work->actual_start_date->toDateString());
        $this->assertSame(19, $work->start_delay_days);
    }

    public function test_une_fin_anterieure_au_debut_est_refusee(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('chef_projet');
        $project = $this->project();
        $work = $this->work($project);

        $this->actingAs($user)
            ->put(route('projects.building-works.update', [$project, $work]), [
                'name' => 'Bâtiment',
                'planned_start_date' => '2026-05-01',
                'planned_end_date' => '2026-01-01',
            ])
            ->assertSessionHasErrors('planned_end_date');
    }
}
