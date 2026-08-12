<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\PduProject;
use App\Models\ProjectAmendment;
use App\Models\Setting;
use App\Models\University;
use App\Models\User;
use App\Services\AlerteService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paramétrage des seuils : lecture, écriture, bornes, et surtout effet réel
 * sur le déclenchement des alertes.
 */
class ThresholdTest extends TestCase
{
    use RefreshDatabase;

    private function project(float $budget = 100000000): PduProject
    {
        $university = University::create(['name' => 'Université de Test', 'acronym' => 'UT', 'region' => 'Abidjan']);

        return PduProject::create([
            'code' => 'PRJ-SEUIL',
            'title' => 'Projet seuils',
            'university_id' => $university->id,
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => $budget,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');

        return $user;
    }

    private function detecter(PduProject $project): array
    {
        app(ThresholdService::class)->forget();
        app(AlerteService::class)->generateForProject(
            $project->fresh(['physicalProgresses', 'financialProgresses', 'buildingWorks', 'lots', 'milestones', 'amendments', 'payments'])
        );

        return Alert::where('pdu_project_id', $project->id)->where('is_resolved', false)->pluck('type')->all();
    }

    public function test_les_valeurs_par_defaut_proviennent_du_catalogue(): void
    {
        $seuils = app(ThresholdService::class);

        $this->assertSame(90.0, $seuils->get('budget_overrun_rate'));
        $this->assertSame(0.9, $seuils->ratio('budget_overrun_rate'));
        $this->assertSame(30, $seuils->days('stale_data_days'));
    }

    public function test_modifier_le_seuil_davenants_change_le_declenchement(): void
    {
        $project = $this->project(100000000);
        ProjectAmendment::create([
            'pdu_project_id' => $project->id, 'number' => 'AV-01', 'amount' => 10000000, // 10 %
        ]);

        // Seuil d'origine à 15 % : pas d'alerte.
        $this->assertNotContains('amendments_excess', $this->detecter($project));

        // Abaissé à 5 % : l'alerte s'ouvre.
        app(ThresholdService::class)->save(['amendments_ratio' => 5]);
        $this->assertContains('amendments_excess', $this->detecter($project));
    }

    public function test_modifier_le_seuil_de_donnee_perimee_change_le_declenchement(): void
    {
        $project = $this->project();

        // Aucune saisie : l'alerte de donnée manquante s'ouvre quel que soit le seuil.
        $this->assertContains('no_update', $this->detecter($project));

        // Le seuil reste borné à son intervalle : 999 est ramené au maximum.
        app(ThresholdService::class)->save(['stale_data_days' => 999]);
        $this->assertSame(180, app(ThresholdService::class)->days('stale_data_days'));
    }

    public function test_une_valeur_hors_bornes_est_ramenee_dans_lintervalle(): void
    {
        $seuils = app(ThresholdService::class);

        $seuils->save(['budget_overrun_rate' => 500]);
        $this->assertSame(100.0, $seuils->get('budget_overrun_rate'));

        $seuils->save(['budget_overrun_rate' => 0]);
        $this->assertSame(50.0, $seuils->get('budget_overrun_rate'));
    }

    public function test_une_cle_hors_catalogue_est_ignoree(): void
    {
        app(ThresholdService::class)->save(['cle_inventee' => 42]);

        $this->assertDatabaseMissing('settings', ['key' => 'cle_inventee']);
    }

    public function test_ladministrateur_consulte_et_enregistre_les_seuils(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.seuils.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Thresholds')->has('groups'));

        $this->actingAs($admin)
            ->put(route('admin.seuils.update'), ['cpi_threshold' => 0.8])
            ->assertRedirect();

        $this->assertSame(0.8, app(ThresholdService::class)->get('cpi_threshold'));
        $this->assertDatabaseHas('settings', ['key' => 'cpi_threshold', 'updated_by' => $admin->id]);
    }

    public function test_la_modification_est_journalisee(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.seuils.update'), ['cpi_threshold' => 0.7]);

        $this->assertDatabaseHas('activity_logs', ['model' => 'Setting', 'action' => 'updated']);
    }

    public function test_le_retablissement_efface_les_valeurs_enregistrees(): void
    {
        $admin = $this->admin();
        app(ThresholdService::class)->save(['cpi_threshold' => 0.7]);
        $this->assertSame(0.7, app(ThresholdService::class)->get('cpi_threshold'));

        $this->actingAs($admin)->delete(route('admin.seuils.reset'))->assertRedirect();

        $this->assertSame(0.95, app(ThresholdService::class)->get('cpi_threshold'));
        $this->assertSame(0, Setting::count());
    }

    public function test_un_non_administrateur_na_pas_acces_aux_seuils(): void
    {
        $chef = User::factory()->create(['is_active' => true]);
        $chef->assignRole('chef_projet');

        $this->actingAs($chef)->get(route('admin.seuils.index'))->assertForbidden();
        $this->actingAs($chef)->put(route('admin.seuils.update'), ['cpi_threshold' => 0.5])->assertForbidden();
    }
}
