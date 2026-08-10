<?php

namespace Tests\Feature;

use App\Models\BuildingWork;
use App\Models\PduProject;
use App\Models\PhysicalProgress;
use App\Models\University;
use App\Models\User;
use App\Services\EarnedScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EarnedScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(string $start, string $end): PduProject
    {
        $user = User::factory()->create();
        $university = University::create(['name' => 'Université de Test', 'acronym' => 'UT', 'region' => 'Abidjan']);

        return PduProject::create([
            'code' => 'PRJ-ES',
            'title' => 'Projet Earned Schedule',
            'university_id' => $university->id,
            'created_by' => $user->id,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => 100000000,
        ]);
    }

    /** Un ouvrage unique, avec une courbe planifiée mois par mois. */
    private function seedCurve(PduProject $project, string $start, array $plannedByMonth, float $actual): BuildingWork
    {
        $work = BuildingWork::create([
            'pdu_project_id' => $project->id,
            'code' => 'OUV-01',
            'name' => 'Bâtiment pédagogique',
            'weight_percentage' => 100,
        ]);

        $s = Carbon::parse($start);
        foreach ($plannedByMonth as $month => $planned) {
            $date = $s->copy()->addMonthsNoOverflow($month);
            PhysicalProgress::create([
                'pdu_project_id' => $project->id,
                'building_work_id' => $work->id,
                'period' => $date->format('Y-m'),
                'measurement_date' => $date->toDateString(),
                'planned_percentage' => $planned,
                'actual_percentage' => $actual,
            ]);
        }

        $project->progress_percentage = $actual;
        $project->saveQuietly();

        return $work;
    }

    public function test_le_retard_est_mesure_sur_la_courbe_planifiee_et_non_sur_un_rythme_lineaire(): void
    {
        // Projet de 24 mois démarré il y a 12 mois, avancement réel 30 %.
        $start = now()->subMonthsNoOverflow(12)->startOfDay();
        $project = $this->makeProject($start->toDateString(), $start->copy()->addMonthsNoOverflow(24)->toDateString());

        // Courbe planifiée en S : le plan prévoyait 30 % au mois 9.
        $this->seedCurve($project, $start->toDateString(), [
            0 => 0, 3 => 8, 6 => 18, 9 => 30, 12 => 45,
        ], 30);

        $project->load(['physicalProgresses', 'buildingWorks', 'amendments']);
        $forecast = app(EarnedScheduleService::class)->forecast($project);

        // ES ≈ 9 mois, AT = 12 mois → SPI(t) ≈ 0,75 → durée ≈ 32 mois → ~8 mois de retard.
        $this->assertSame('earned_schedule', $forecast['method']);
        $this->assertEqualsWithDelta(0.75, $forecast['spi_t'], 0.03);
        $this->assertEqualsWithDelta(240, $forecast['delay_days'], 20);

        // L'extrapolation linéaire aurait annoncé environ le double de retard.
        $elapsed = (int) $project->start_date->diffInDays(now()->startOfDay());
        $lineaire = (int) ceil((100 - 30) / (30 / $elapsed)) + $elapsed
            - (int) $project->start_date->diffInDays($project->planned_end_date_revised);
        $this->assertGreaterThan($forecast['delay_days'] * 1.5, $lineaire);
    }

    public function test_un_projet_conforme_a_son_planning_est_annonce_dans_les_temps(): void
    {
        $start = now()->subMonthsNoOverflow(12)->startOfDay();
        $project = $this->makeProject($start->toDateString(), $start->copy()->addMonthsNoOverflow(24)->toDateString());

        // L'avancement réel correspond exactement au planifié du mois 12.
        $this->seedCurve($project, $start->toDateString(), [
            0 => 0, 6 => 18, 12 => 45,
        ], 45);

        $project->load(['physicalProgresses', 'buildingWorks', 'amendments']);
        $forecast = app(EarnedScheduleService::class)->forecast($project);

        $this->assertEqualsWithDelta(1.0, $forecast['spi_t'], 0.05);
        $this->assertSame('on_track', $forecast['level']);
    }

    public function test_un_avenant_de_delai_reduit_le_retard_projete(): void
    {
        $start = now()->subMonthsNoOverflow(12)->startOfDay();
        $project = $this->makeProject($start->toDateString(), $start->copy()->addMonthsNoOverflow(24)->toDateString());
        $this->seedCurve($project, $start->toDateString(), [0 => 0, 6 => 18, 9 => 30, 12 => 45], 30);

        $project->load(['physicalProgresses', 'buildingWorks', 'amendments']);
        $avant = app(EarnedScheduleService::class)->forecast($project);

        \App\Models\ProjectAmendment::create([
            'pdu_project_id' => $project->id,
            'number' => 'AV-01',
            'amount' => 0,
            'duration_days' => 180,
        ]);

        $project->load(['physicalProgresses', 'buildingWorks', 'amendments']);
        $apres = app(EarnedScheduleService::class)->forecast($project);

        $this->assertLessThan($avant['delay_days'], $apres['delay_days']);
    }

    public function test_sans_courbe_planifiee_la_projection_nest_pas_affichee(): void
    {
        $start = now()->subMonthsNoOverflow(6)->startOfDay();
        $project = $this->makeProject($start->toDateString(), $start->copy()->addMonthsNoOverflow(24)->toDateString());
        $project->progress_percentage = 20;
        $project->saveQuietly();

        $project->load(['physicalProgresses', 'buildingWorks', 'amendments']);
        $forecast = app(EarnedScheduleService::class)->forecast($project);

        $this->assertSame('none', $forecast['level']);
        $this->assertSame('no_planned_curve', $forecast['reason']);
        $this->assertNull($forecast['projected_end_date']);
    }

    public function test_un_chantier_acheve_ne_projette_plus_de_date(): void
    {
        $start = now()->subMonthsNoOverflow(12)->startOfDay();
        $project = $this->makeProject($start->toDateString(), $start->copy()->addMonthsNoOverflow(24)->toDateString());
        $this->seedCurve($project, $start->toDateString(), [0 => 0, 6 => 40, 12 => 90], 100);

        $project->load(['physicalProgresses', 'buildingWorks', 'amendments']);
        $forecast = app(EarnedScheduleService::class)->forecast($project);

        $this->assertSame('done', $forecast['level']);
        $this->assertNull($forecast['projected_end_date']);
    }

    public function test_le_score_de_sante_a_bien_ete_retire(): void
    {
        $this->assertFalse(
            class_exists(\App\Services\ProjectHealthService::class),
            'Le service de score de santé ne doit plus exister.',
        );

        $user = User::factory()->create();
        $user->assignRole('admin');
        $start = now()->subMonthsNoOverflow(6)->startOfDay();
        $project = $this->makeProject($start->toDateString(), $start->copy()->addMonthsNoOverflow(24)->toDateString());

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('kpis.health'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('projects.0.health_score'));
    }
}
