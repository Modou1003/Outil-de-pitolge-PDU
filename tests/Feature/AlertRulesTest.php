<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\FinancialProgress;
use App\Models\PduProject;
use App\Models\ProjectAmendment;
use App\Models\ProjectPayment;
use App\Models\University;
use App\Models\User;
use App\Services\AlerteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trois règles de détection complétant le module d'alertes : dérive de coût
 * (CPI), avenants cumulés excessifs et décomptes en attente de règlement.
 */
class AlertRulesTest extends TestCase
{
    use RefreshDatabase;

    private function project(float $budget = 100000000): PduProject
    {
        $university = University::create(['name' => 'Université de Test', 'acronym' => 'UT', 'region' => 'Abidjan']);

        return PduProject::create([
            'code' => 'PRJ-ALERT',
            'title' => 'Projet alertes',
            'university_id' => $university->id,
            'created_by' => User::factory()->create()->id,
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => $budget,
        ]);
    }

    private function generate(PduProject $project): void
    {
        app(AlerteService::class)->generateForProject(
            $project->fresh(['physicalProgresses', 'financialProgresses', 'buildingWorks', 'lots', 'milestones', 'amendments', 'payments'])
        );
    }

    private function openTypes(PduProject $project): array
    {
        return Alert::where('pdu_project_id', $project->id)
            ->where('is_resolved', false)
            ->pluck('type')->all();
    }

    private function evm(PduProject $project, float $earned, float $actual): void
    {
        FinancialProgress::create([
            'pdu_project_id' => $project->id,
            'period' => now()->format('Y-m'),
            'measurement_date' => now()->toDateString(),
            'planned_value' => $earned,
            'earned_value' => $earned,
            'actual_cost' => $actual,
        ]);
    }

    // ------------------------------------------------------------ dérive de coût

    public function test_un_cpi_sous_le_seuil_declenche_une_derive_de_cout(): void
    {
        $project = $this->project();
        $this->evm($project, 90000000, 100000000); // CPI = 0,90

        $this->generate($project);

        $this->assertContains('cost_drift', $this->openTypes($project));
        $alert = Alert::where('type', 'cost_drift')->firstOrFail();
        $this->assertSame('warning', $alert->severity);
        $this->assertSame(0.9, $alert->context['cpi']);
    }

    public function test_un_cpi_au_dessus_du_seuil_ne_declenche_rien(): void
    {
        $project = $this->project();
        $this->evm($project, 100000000, 100000000); // CPI = 1,00

        $this->generate($project);

        $this->assertNotContains('cost_drift', $this->openTypes($project));
    }

    public function test_sans_cout_reel_la_derive_de_cout_nest_pas_calculable(): void
    {
        $project = $this->project();
        $this->evm($project, 0, 0);

        $this->generate($project);

        $this->assertNotContains('cost_drift', $this->openTypes($project));
    }

    // ------------------------------------------------------- avenants cumulés

    public function test_des_avenants_au_dela_de_15_pourcent_sont_signales(): void
    {
        $project = $this->project(100000000);
        ProjectAmendment::create([
            'pdu_project_id' => $project->id,
            'number' => 'AV-01',
            'amount' => 20000000, // 20 % du marché initial
        ]);

        $this->generate($project);

        $this->assertContains('amendments_excess', $this->openTypes($project));
        $alert = Alert::where('type', 'amendments_excess')->firstOrFail();
        $this->assertSame('warning', $alert->severity);
        // Le contexte est stocké en JSON : 20.0 s'y sérialise en entier.
        $this->assertEqualsWithDelta(20.0, $alert->context['ratio'], 0.01);
    }

    public function test_des_avenants_sous_le_seuil_ne_sont_pas_signales(): void
    {
        $project = $this->project(100000000);
        ProjectAmendment::create(['pdu_project_id' => $project->id, 'number' => 'AV-01', 'amount' => 10000000]);

        $this->generate($project);

        $this->assertNotContains('amendments_excess', $this->openTypes($project));
    }

    public function test_une_moins_value_nest_pas_une_derive(): void
    {
        $project = $this->project(100000000);
        ProjectAmendment::create(['pdu_project_id' => $project->id, 'number' => 'AV-01', 'amount' => -30000000]);

        $this->generate($project);

        $this->assertNotContains('amendments_excess', $this->openTypes($project));
    }

    // ------------------------------------------------------ décompte en attente

    public function test_un_decompte_non_regle_depuis_plus_de_30_jours_est_signale(): void
    {
        $project = $this->project();
        ProjectPayment::create([
            'pdu_project_id' => $project->id,
            'number' => 'D-01',
            'payment_date' => now()->subDays(45)->toDateString(),
            'gross_amount' => 5000000,
            'net_paid' => 0,
            'is_paid' => false,
        ]);

        $this->generate($project);

        $this->assertContains('payment_pending', $this->openTypes($project));
        $alert = Alert::where('type', 'payment_pending')->firstOrFail();
        $this->assertSame('info', $alert->severity);
        $this->assertSame(1, $alert->context['count']);
        $this->assertSame(45, $alert->context['oldest_days']);
    }

    public function test_un_decompte_regle_ou_recent_nest_pas_signale(): void
    {
        $project = $this->project();
        ProjectPayment::create([
            'pdu_project_id' => $project->id, 'number' => 'D-01',
            'payment_date' => now()->subDays(60)->toDateString(),
            'gross_amount' => 5000000, 'net_paid' => 5000000, 'is_paid' => true,
        ]);
        ProjectPayment::create([
            'pdu_project_id' => $project->id, 'number' => 'D-02',
            'payment_date' => now()->subDays(10)->toDateString(),
            'gross_amount' => 3000000, 'net_paid' => 0, 'is_paid' => false,
        ]);

        $this->generate($project);

        $this->assertNotContains('payment_pending', $this->openTypes($project));
    }

    // ---------------------------------------------------------------- fermeture

    public function test_lalerte_se_referme_quand_le_decompte_est_regle(): void
    {
        $project = $this->project();
        $payment = ProjectPayment::create([
            'pdu_project_id' => $project->id, 'number' => 'D-01',
            'payment_date' => now()->subDays(45)->toDateString(),
            'gross_amount' => 5000000, 'net_paid' => 0, 'is_paid' => false,
        ]);

        $this->generate($project);
        $this->assertContains('payment_pending', $this->openTypes($project));

        $payment->update(['is_paid' => true, 'net_paid' => 5000000]);
        $this->generate($project);

        $this->assertNotContains('payment_pending', $this->openTypes($project));
    }

    public function test_les_trois_types_sont_au_catalogue(): void
    {
        foreach (['cost_drift', 'amendments_excess', 'payment_pending', 'work_start_delay'] as $type) {
            $this->assertArrayHasKey($type, Alert::TYPES);
        }
        $this->assertCount(11, Alert::TYPES, 'Le module compte désormais onze règles de détection.');
    }
}
