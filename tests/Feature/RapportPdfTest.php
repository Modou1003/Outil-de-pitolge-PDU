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

    public function test_sans_droit_dexport_la_generation_est_refusee(): void
    {
        $lecteur = User::factory()->create(['is_active' => true]);
        $lecteur->assignRole('visiteur');

        $this->actingAs($lecteur)
            ->get(route('rapports.projet', $this->project()))
            ->assertForbidden();
    }
}
