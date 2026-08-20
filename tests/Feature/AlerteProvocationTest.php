<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\FinancialProgress;
use App\Models\PduProject;
use App\Models\ProjectAmendment;
use App\Models\ProjectMilestone;
use App\Models\University;
use App\Models\User;
use App\Services\AlerteService;
use App\Services\Import\BaseDeCalculImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scénario 8 du protocole de validation — provocation délibérée des alertes.
 *
 * Cinq des onze règles se déclenchent sur les seules données réelles du
 * chantier d'Odienné. Les six autres supposent des situations que le chantier
 * ne présente pas à la date d'arrêté : elles sont ici provoquées une à une,
 * puis la situation est rétablie afin de vérifier que l'alerte se referme
 * d'elle-même. Chaque essai porte sur le jeu de données réel, non sur un
 * projet fabriqué : c'est la seule façon de vérifier qu'une règle ne se
 * déclenche pas déjà pour une autre raison.
 */
class AlerteProvocationTest extends TestCase
{
    use RefreshDatabase;

    private PduProject $projet;

    private User $auteur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auteur = User::factory()->create(['is_active' => true]);
        $this->auteur->assignRole('chef_projet');

        $universite = University::create([
            'name' => 'Université d’Odienné', 'acronym' => 'UODN', 'region' => 'Kabadougou',
        ]);

        $this->projet = PduProject::create([
            'code' => 'PRJ-ODN',
            'title' => 'Travaux de construction — tranche 1',
            'university_id' => $universite->id,
            'created_by' => $this->auteur->id,
            'start_date' => '2024-11-04',
            'end_date' => '2026-11-03',
            'planned_completion_date' => '2026-11-03',
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => 68_879_636_067,
            'startup_advance_amount' => 13_775_927_213,
            'supply_advance_amount' => 1_607_160_969,
        ]);

        $donnees = json_decode(file_get_contents(database_path('data/odienne.json')), true, 512, JSON_THROW_ON_ERROR);
        app(BaseDeCalculImporter::class)->import($this->projet, $donnees, $this->auteur, true);
        $this->projet->refresh();
    }

    private function service(): AlerteService
    {
        return app(AlerteService::class);
    }

    /** Rejoue la détection sur un projet rechargé, relations comprises. */
    private function detecter(): void
    {
        $this->service()->generateForProject(
            PduProject::with(['milestones', 'financialProgresses', 'physicalProgresses', 'buildingWorks', 'amendments', 'payments', 'lots'])
                ->find($this->projet->id)
        );
    }

    private function ouverte(string $type): bool
    {
        return Alert::where('pdu_project_id', $this->projet->id)
            ->where('type', $type)->where('is_resolved', false)->exists();
    }

    /**
     * Provoque, vérifie l'ouverture, rétablit, vérifie la fermeture.
     *
     * @param  callable():void  $provoquer
     * @param  callable():void  $retablir
     */
    private function eprouver(string $type, callable $provoquer, callable $retablir): void
    {
        $this->detecter();
        $this->assertFalse($this->ouverte($type), "La règle « {$type} » était déjà ouverte avant provocation.");

        $provoquer();
        $this->projet->refresh();
        $this->detecter();
        $this->assertTrue($this->ouverte($type), "La règle « {$type} » ne s'est pas déclenchée.");

        $retablir();
        $this->projet->refresh();
        $this->detecter();
        $this->assertFalse($this->ouverte($type), "La règle « {$type} » ne s'est pas refermée après rétablissement.");
    }

    // ─────────────────────────────────────────── les cinq règles déjà actives

    public function test_les_cinq_regles_du_chantier_reel_sont_ouvertes(): void
    {
        $this->detecter();

        foreach (['progress_gap', 'no_update', 'forecast_delay', 'payment_pending', 'work_start_delay'] as $type) {
            $this->assertTrue($this->ouverte($type), "La règle « {$type} » aurait dû s'ouvrir sur les données réelles.");
        }

        $this->assertSame(5, Alert::where('pdu_project_id', $this->projet->id)->where('is_resolved', false)->count());
    }

    // ───────────────────────────────────────────────── les six provoquées

    public function test_jalon_manque(): void
    {
        $jalon = null;

        $this->eprouver('milestone_missed',
            function () use (&$jalon) {
                $jalon = ProjectMilestone::create([
                    'pdu_project_id' => $this->projet->id,
                    'name' => 'Réception provisoire du bâtiment UFR',
                    'planned_date' => '2026-05-31',
                    'status' => 'pending',
                ]);
            },
            function () use (&$jalon) {
                $jalon->update(['status' => 'reached', 'actual_date' => '2026-05-31']);
            },
        );
    }

    public function test_depassement_du_delai_contractuel(): void
    {
        // Un ouvrage achevé au-delà de l'échéance : l'échéance est ramenée en
        // deçà de la dernière période mesurée, puis rétablie.
        $this->eprouver('delay',
            fn () => $this->projet->update(['end_date' => '2026-01-31', 'planned_completion_date' => '2026-01-31']),
            fn () => $this->projet->update(['end_date' => '2026-11-03', 'planned_completion_date' => '2026-11-03']),
        );
    }

    public function test_depassement_budgetaire(): void
    {
        $initial = $this->projet->budget_spent;

        // Seuil : neuf dixièmes du marché actualisé décaissés.
        $this->eprouver('budget_overrun',
            fn () => $this->projet->update(['budget_spent' => 63_000_000_000]),
            fn () => $this->projet->update(['budget_spent' => $initial]),
        );
    }

    public function test_decalage_physico_financier(): void
    {
        $initial = $this->projet->budget_spent;

        // Décaissement porté à 60 % pour un avancement physique de 32,13 % :
        // vingt-huit points d'avance du paiement sur la réalisation.
        $this->eprouver('physical_financial_gap',
            fn () => $this->projet->update(['budget_spent' => 41_300_000_000]),
            fn () => $this->projet->update(['budget_spent' => $initial]),
        );
    }

    public function test_derive_de_cout(): void
    {
        $releve = FinancialProgress::where('pdu_project_id', $this->projet->id)
            ->orderByDesc('period')->first();
        $initial = (float) $releve->actual_cost;

        // Coût réel gonflé de six milliards : le CPI passe sous 0,95.
        $this->eprouver('cost_drift',
            fn () => $releve->update(['actual_cost' => $initial + 6_000_000_000]),
            fn () => $releve->update(['actual_cost' => $initial]),
        );
    }

    public function test_avenants_cumules_excessifs(): void
    {
        $avenant = null;

        // Seuil : quinze pour cent du montant initial, soit 10,33 milliards.
        $this->eprouver('amendments_excess',
            function () use (&$avenant) {
                $avenant = ProjectAmendment::create([
                    'pdu_project_id' => $this->projet->id,
                    'number' => 'AV-01',
                    'object' => 'Travaux supplémentaires de voirie',
                    'signed_date' => '2026-06-15',
                    'amount' => 12_000_000_000,
                    'recorded_by' => $this->auteur->id,
                ]);
            },
            function () use (&$avenant) {
                $avenant->delete();
            },
        );
    }
}
