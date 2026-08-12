<?php

namespace Tests\Feature;

use App\Models\BuildingWork;
use App\Models\PduProject;
use App\Models\PhysicalProgress;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Génération des rapports PDF de bout en bout.
 *
 * Le rendu PDF n'était couvert par aucun test : une erreur dans le gabarit
 * (image introuvable, variable absente) ne se manifestait qu'au téléchargement.
 */
class RapportPdfTest extends TestCase
{
    use RefreshDatabase;

    private function project(): PduProject
    {
        $university = University::create(['name' => 'Université de Test', 'acronym' => 'UT', 'region' => 'Abidjan']);

        return PduProject::create([
            'code' => 'PRJ-PDF',
            'title' => 'Projet rapport',
            'university_id' => $university->id,
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
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

    private function assertIsPdf($response): void
    {
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_le_rapport_projet_se_genere_sans_donnees(): void
    {
        $this->assertIsPdf(
            $this->actingAs($this->admin())->get(route('rapports.projet', $this->project()))
        );
    }

    public function test_le_rapport_projet_se_genere_avec_des_releves(): void
    {
        $project = $this->project();
        $work = BuildingWork::create([
            'pdu_project_id' => $project->id, 'code' => 'OUV-01',
            'name' => 'Bâtiment', 'weight_percentage' => 100,
        ]);
        foreach ([['2026-01', 10, 8], ['2026-02', 25, 20]] as [$period, $planned, $actual]) {
            PhysicalProgress::create([
                'pdu_project_id' => $project->id,
                'building_work_id' => $work->id,
                'period' => $period,
                'measurement_date' => $period . '-28',
                'planned_percentage' => $planned,
                'actual_percentage' => $actual,
            ]);
        }

        $this->assertIsPdf(
            $this->actingAs($this->admin())->get(route('rapports.projet', $project))
        );
    }

    public function test_le_rapport_global_se_genere(): void
    {
        $this->project();

        $this->assertIsPdf(
            $this->actingAs($this->admin())->get(route('rapports.global'))
        );
    }

    public function test_le_logo_du_pdu_est_disponible_pour_le_gabarit(): void
    {
        $this->assertFileExists(
            public_path('images/login/logo-pdu.png'),
            'Le gabarit référence ce logo : son absence ferait retomber sur le carré de repli.',
        );
    }

    /** Rend le gabarit de la fiche projet avec la sélection de sections voulue. */
    private function renderProjet(PduProject $project, array $sections): string
    {
        return view('pdf.rapport-projet', [
            'show' => fn (string $key) => in_array($key, $sections, true),
            'project' => $project->load(['university', 'buildingWorks', 'lots', 'milestones',
                'physicalProgresses', 'financialProgresses', 'payments', 'amendments', 'alerts', 'teamMembers']),
            'kpis' => ['cpi' => null, 'spi' => null, 'cv' => 0, 'sv' => 0, 'eac' => null,
                'budget_rate' => 0, 'planned_progress' => 0, 'milestones_reached' => 0, 'milestones_total' => 0],
            'moa' => $project->financialMoa(),
            'physicalChartSvg' => null,
            'financialChartSvg' => null,
            'generatedAt' => now(),
        ])->render();
    }

    public function test_le_gabarit_nemet_que_les_sections_demandees(): void
    {
        $project = $this->project();

        $complet = $this->renderProjet($project, array_keys(\App\Http\Controllers\RapportController::PROJECT_SECTIONS));
        $this->assertStringContainsString('Jalons clés', $complet);
        $this->assertStringContainsString('Équipe projet', $complet);
        $this->assertStringContainsString('Indicateurs clés', $complet);

        $restreint = $this->renderProjet($project, ['identite', 'indicateurs']);
        $this->assertStringContainsString('Indicateurs clés', $restreint);
        $this->assertStringNotContainsString('Jalons clés', $restreint);
        $this->assertStringNotContainsString('Équipe projet', $restreint);
        $this->assertStringNotContainsString('Situation financière', $restreint);
    }

    public function test_le_rapport_restreint_est_plus_leger_que_le_rapport_complet(): void
    {
        $project = $this->project();

        $complet = $this->actingAs($this->admin())
            ->get(route('rapports.projet', $project))->getContent();
        $restreint = $this->actingAs($this->admin())
            ->get(route('rapports.projet', [$project, 'sections' => 'identite,indicateurs']))->getContent();

        $this->assertLessThan(strlen($complet), strlen($restreint));
    }

    public function test_une_section_inconnue_est_ignoree_et_le_rapport_reste_complet(): void
    {
        $project = $this->project();

        $reponse = $this->actingAs($this->admin())
            ->get(route('rapports.projet', [$project, 'sections' => 'nimportequoi']));
        $this->assertIsPdf($reponse);

        // Une sélection vide après filtrage retombe sur le rapport intégral.
        $complet = $this->actingAs($this->admin())
            ->get(route('rapports.projet', $project))->getContent();
        $this->assertEqualsWithDelta(strlen($complet), strlen($reponse->getContent()), 2000);
    }

    public function test_le_rapport_global_se_limite_aux_sections_demandees(): void
    {
        $this->project();

        $complet = $this->actingAs($this->admin())
            ->get(route('rapports.global'))->getContent();
        $restreint = $this->actingAs($this->admin())
            ->get(route('rapports.global', ['sections' => 'synthese']));

        $this->assertIsPdf($restreint);
        $this->assertLessThan(strlen($complet), strlen($restreint->getContent()));
    }

    public function test_sans_droit_dexport_la_generation_est_refusee(): void
    {
        $lecteur = User::factory()->create(['is_active' => true]);
        $lecteur->assignRole('visiteur');

        $this->actingAs($lecteur)
            ->get(route('rapports.projet', $this->project()))
            ->assertForbidden();
    }
}
