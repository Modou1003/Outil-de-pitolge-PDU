<?php

namespace Database\Seeders;

use App\Models\BuildingWork;
use App\Models\FinancialProgress;
use App\Models\IndicatorTracking;
use App\Models\PduProject;
use App\Models\PhysicalProgress;
use App\Models\ProjectAmendment;
use App\Models\ProjectLot;
use App\Models\ProjectMilestone;
use App\Models\ProjectPayment;
use App\Models\ProjectTeamMember;
use App\Models\University;
use App\Models\User;
use App\Services\AlerteService;
use App\Services\ProjectAggregationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Projet de démonstration entièrement alimenté, destiné aux captures d'écran
 * du mémoire et aux présentations.
 *
 * Les données sont construites pour être crédibles et cohérentes entre elles :
 * l'avancement physique suit une courbe en S, les valeurs de la méthode de la
 * valeur acquise en découlent, et les décomptes se raccordent aux avances
 * contractuelles. Le chantier est volontairement en léger retard afin que les
 * indicateurs et les alertes aient quelque chose à montrer.
 *
 *   php artisan db:seed --class=DemoProjectSeeder
 *
 * Le seeder ne touche qu'au projet portant le code PRJ-DEMO : il le recrée à
 * chaque exécution et laisse le reste du portefeuille intact.
 */
class DemoProjectSeeder extends Seeder
{
    private const CODE = 'PRJ-DEMO';

    /** Marché initial, avant avenants. */
    private const BUDGET = 2_450_000_000;

    /**
     * Courbe en S des quatorze premiers mois, calée sur une durée
     * contractuelle de vingt mois : démarrage lent, accélération au gros
     * œuvre, puis tassement. Le réel décroche légèrement du prévu, ce qui
     * place le chantier « à surveiller » sans le rendre catastrophique.
     */
    private const COURBE = [
        // mois, prévu %, réel %
        [0, 1, 1], [1, 3, 3], [2, 6, 5], [3, 10, 9], [4, 16, 15],
        [5, 22, 21], [6, 29, 28], [7, 37, 35], [8, 44, 42], [9, 51, 48],
        [10, 57, 54], [11, 63, 59], [12, 68, 64], [13, 72, 68],
    ];

    public function run(): void
    {
        $auteur = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
            ?? User::query()->first();

        if (! $auteur) {
            $this->command?->error('Aucun utilisateur en base : lancez d’abord le seeder des rôles.');

            return;
        }

        // Repartir d'un état propre sans toucher au reste du portefeuille.
        PduProject::withTrashed()->where('code', self::CODE)->forceDelete();

        $universite = University::firstOrCreate(
            ['acronym' => 'UMAN'],
            [
                'name' => 'Université de Man',
                'location' => 'Man',
                'region' => 'Tonkpi',
                'status' => 'active',
                'latitude' => 7.4125,
                'longitude' => -7.5539,
            ],
        );

        $debut = Carbon::now()->startOfMonth()->subMonthsNoOverflow(14);
        $finInitiale = (clone $debut)->addMonthsNoOverflow(20);

        $projet = PduProject::create([
            'code' => self::CODE,
            'title' => 'Construction du bloc pédagogique et du laboratoire de génie civil',
            'description' => "Édification d'un bloc pédagogique de huit salles, d'un laboratoire de génie civil et des voiries associées, dans le cadre de la décentralisation universitaire.",
            'university_id' => $universite->id,
            'created_by' => $auteur->id,
            'start_date' => $debut->toDateString(),
            'end_date' => $finInitiale->toDateString(),
            'planned_completion_date' => $finInitiale->toDateString(),
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => self::BUDGET,
            'startup_advance_amount' => self::BUDGET * 0.10,
            'supply_advance_amount' => self::BUDGET * 0.05,
            'currency' => 'XOF',
            'objectives' => [
                "Accroître la capacité d'accueil de 800 étudiants",
                'Doter la filière génie civil d’un laboratoire d’essais',
                'Viabiliser l’emprise du campus',
            ],
        ]);

        $ouvrages = $this->ouvrages($projet);
        $this->avancementPhysique($projet, $ouvrages);
        $this->avancementFinancier($projet, $ouvrages);
        $this->avenants($projet, $auteur);
        $this->decomptes($projet, $auteur);
        $this->jalons($projet, $ouvrages, $debut);
        $this->equipe($projet);
        $this->indicateurs($projet, $auteur);

        // Recalculs, puis détection des alertes sur des données désormais complètes.
        $agg = app(ProjectAggregationService::class);
        $agg->recomputeProjectProgress($projet);
        $agg->recomputeFinancialCumulatives($projet);
        $agg->recomputeProjectBudgetSpent($projet);

        app(AlerteService::class)->generateForProject(
            $projet->fresh(['physicalProgresses', 'financialProgresses', 'buildingWorks', 'lots', 'milestones', 'amendments', 'payments'])
        );

        $projet->refresh();
        $this->command?->info('Projet de démonstration créé : ' . $projet->code);
        $this->command?->table(
            ['Indicateur', 'Valeur'],
            [
                ['Avancement physique', number_format((float) $projet->progress_percentage, 1) . ' %'],
                ['Marché initial', number_format(self::BUDGET, 0, ',', ' ') . ' XOF'],
                ['Marché actualisé', number_format($projet->budget_revised, 0, ',', ' ') . ' XOF'],
                ['Décaissé', number_format((float) $projet->budget_spent, 0, ',', ' ') . ' XOF'],
                ['Taux d’exécution', $projet->budget_execution_rate . ' %'],
                ['Ouvrages', $projet->buildingWorks()->count()],
                ['Relevés physiques', $projet->physicalProgresses()->count()],
                ['Relevés financiers', $projet->financialProgresses()->count()],
                ['Décomptes', $projet->payments()->count()],
                ['Avenants', $projet->amendments()->count()],
                ['Alertes ouvertes', $projet->alerts()->where('is_resolved', false)->count()],
            ],
        );
    }

    /** Trois ouvrages pondérés au prorata de leur poids dans le marché. */
    private function ouvrages(PduProject $projet): array
    {
        $definitions = [
            ['OUV-01', 'Bloc pédagogique R+2 (8 salles)', 55],
            ['OUV-02', 'Laboratoire de génie civil', 30],
            ['OUV-03', 'Voiries, réseaux divers et clôture', 15],
        ];

        $ouvrages = [];
        foreach ($definitions as $i => [$code, $nom, $poids]) {
            $ouvrages[] = BuildingWork::create([
                'pdu_project_id' => $projet->id,
                'code' => $code,
                'name' => $nom,
                'status' => 'in_progress',
                'weight_percentage' => $poids,
                'sort_order' => $i + 1,
            ]);
        }

        // Un lot de planning par ouvrage, pour alimenter l'onglet Planning.
        foreach ($ouvrages as $i => $ouvrage) {
            ProjectLot::create([
                'pdu_project_id' => $projet->id,
                'building_work_id' => $ouvrage->id,
                'kind' => 'planning',
                'code' => 'LOT-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'name' => 'Exécution — ' . $ouvrage->name,
                'planned_start_date' => $projet->start_date->toDateString(),
                'planned_end_date' => $projet->end_date->toDateString(),
                'actual_start_date' => $projet->start_date->toDateString(),
                'status' => 'in_progress',
                'sort_order' => $i + 1,
            ]);
        }

        return $ouvrages;
    }

    /**
     * Relevés mensuels par ouvrage. Chaque ouvrage suit la courbe commune,
     * décalée d'un coefficient : le bloc pédagogique a démarré en premier,
     * les voiries accusent un retard marqué.
     */
    private function avancementPhysique(PduProject $projet, array $ouvrages): void
    {
        $rythmes = [1.05, 0.98, 0.78]; // avance relative de chaque ouvrage

        foreach ($ouvrages as $i => $ouvrage) {
            foreach (self::COURBE as [$mois, $prevu, $reel]) {
                $date = $projet->start_date->copy()->addMonthsNoOverflow($mois)->endOfMonth();
                if ($date->isFuture()) {
                    break;
                }

                PhysicalProgress::create([
                    'pdu_project_id' => $projet->id,
                    'building_work_id' => $ouvrage->id,
                    'period' => $date->format('Y-m'),
                    'measurement_date' => $date->toDateString(),
                    'planned_percentage' => min(100, $prevu),
                    'actual_percentage' => min(100, round($reel * $rythmes[$i], 1)),
                    'status' => 'validated',
                    'recorded_by' => $projet->created_by,
                ]);
            }
        }
    }

    /**
     * Valeurs de la méthode de la valeur acquise, dérivées de la courbe
     * physique : la valeur acquise suit le réalisé, le coût réel le dépasse
     * légèrement — d'où un indice de performance des coûts autour de 0,96.
     */
    private function avancementFinancier(PduProject $projet, array $ouvrages): void
    {
        $poids = [0.55, 0.30, 0.15];

        foreach ($ouvrages as $i => $ouvrage) {
            $enveloppe = self::BUDGET * $poids[$i];
            $prevuPrec = 0.0;
            $reelPrec = 0.0;

            foreach (self::COURBE as [$mois, $prevu, $reel]) {
                $date = $projet->start_date->copy()->addMonthsNoOverflow($mois)->endOfMonth();
                if ($date->isFuture()) {
                    break;
                }

                // Incréments de la période, et non cumuls : le service d'agrégation cumule ensuite.
                $pv = ($prevu - $prevuPrec) / 100 * $enveloppe;
                $ev = ($reel - $reelPrec) / 100 * $enveloppe;
                $ac = $ev * 1.042; // léger surcoût d'exécution

                FinancialProgress::create([
                    'pdu_project_id' => $projet->id,
                    'building_work_id' => $ouvrage->id,
                    'period' => $date->format('Y-m'),
                    'measurement_date' => $date->toDateString(),
                    'planned_value' => round($pv, 2),
                    'earned_value' => round($ev, 2),
                    'actual_cost' => round($ac, 2),
                    'status' => 'validated',
                    'recorded_by' => $projet->created_by,
                ]);

                $prevuPrec = $prevu;
                $reelPrec = $reel;
            }
        }
    }

    /** Deux avenants : une plus-value avec prolongation, une moins-value. */
    private function avenants(PduProject $projet, User $auteur): void
    {
        ProjectAmendment::create([
            'pdu_project_id' => $projet->id,
            'number' => '01',
            'object' => 'Travaux supplémentaires de fondations spéciales et reprise en sous-œuvre',
            'signed_date' => $projet->start_date->copy()->addMonthsNoOverflow(6)->toDateString(),
            'amount' => 168_000_000,
            'duration_days' => 60,
            'observations' => 'Sujétions géotechniques imprévues révélées par les sondages complémentaires.',
            'recorded_by' => $auteur->id,
        ]);

        ProjectAmendment::create([
            'pdu_project_id' => $projet->id,
            'number' => '02',
            'object' => 'Suppression du poste de clôture grillagée, reporté hors marché',
            'signed_date' => $projet->start_date->copy()->addMonthsNoOverflow(10)->toDateString(),
            'amount' => -42_000_000,
            'duration_days' => 0,
            'recorded_by' => $auteur->id,
        ]);
    }

    /** Quatre décomptes, dont le dernier reste en attente de règlement. */
    private function decomptes(PduProject $projet, User $auteur): void
    {
        $avanceDemarrage = self::BUDGET * 0.10;
        $avanceAppro = self::BUDGET * 0.05;

        $lignes = [
            [1, 3, 245_000_000, 0.20],
            [2, 6, 310_000_000, 0.25],
            [3, 9, 288_000_000, 0.25],
            [4, 12, 265_000_000, 0.20],
        ];

        foreach ($lignes as $index => [$numero, $mois, $brut, $partRecup]) {
            $recupDemarrage = $avanceDemarrage * $partRecup;
            $recupAppro = $avanceAppro * $partRecup;
            $date = $projet->start_date->copy()->addMonthsNoOverflow($mois)->endOfMonth();

            ProjectPayment::create([
                'pdu_project_id' => $projet->id,
                'number' => str_pad((string) $numero, 3, '0', STR_PAD_LEFT),
                'period' => $date->format('Y-m'),
                'payment_date' => $date->toDateString(),
                'gross_amount' => $brut,
                'startup_advance_recovery' => round($recupDemarrage, 2),
                'supply_advance_recovery' => round($recupAppro, 2),
                'net_paid' => round($brut - $recupDemarrage - $recupAppro, 2),
                // Le dernier décompte est transmis mais pas encore réglé.
                'is_paid' => $index < 3,
                'recorded_by' => $auteur->id,
            ]);
        }
    }

    /** Jalons contractuels : les premiers atteints, les suivants à venir. */
    private function jalons(PduProject $projet, array $ouvrages, Carbon $debut): void
    {
        $jalons = [
            ['Ordre de service de démarrage', 0, 'reached', true],
            ['Achèvement des fondations', 4, 'reached', true],
            ['Hors d’eau du bloc pédagogique', 9, 'reached', false],
            // Échu et non atteint : déclenche l'alerte « jalon dépassé ».
            ['Hors d’air et second œuvre', 12, 'pending', true],
            ['Réception provisoire', 20, 'pending', true],
        ];

        foreach ($jalons as $i => [$nom, $mois, $statut, $critique]) {
            $prevue = (clone $debut)->addMonthsNoOverflow($mois)->endOfMonth();

            ProjectMilestone::create([
                'pdu_project_id' => $projet->id,
                'building_work_id' => $ouvrages[0]->id,
                'name' => $nom,
                'planned_date' => $prevue->toDateString(),
                'actual_date' => $statut === 'reached' ? $prevue->copy()->addDays(rand(2, 25))->toDateString() : null,
                'status' => $statut,
                'is_critical' => $critique,
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function equipe(PduProject $projet): void
    {
        $membres = [
            ['directeur', 'Directeur de projet', 'Kouassi N’Guessan', 'UGP-PDU'],
            ['chef_projet', 'Chef de projet', 'Aya Traoré', 'UGP-PDU'],
            ['agent_financier', 'Agent financier', 'Ibrahim Coulibaly', 'UGP-PDU'],
            ['maitre_oeuvre', 'Maître d’œuvre', 'BET Ingénierie Ivoire', 'BET-II'],
            ['entreprise', 'Entreprise titulaire', 'SOGEC Travaux Publics', 'SOGEC-TP'],
        ];

        foreach ($membres as $i => [$cle, $libelle, $nom, $organisation]) {
            ProjectTeamMember::create([
                'pdu_project_id' => $projet->id,
                'role_key' => $cle,
                'role_label' => $libelle,
                'name' => $nom,
                'organization' => $organisation,
                'email' => strtolower(str_replace(' ', '.', $nom)) . '@pdu-ci.example',
                'sort_order' => $i + 1,
            ]);
        }
    }

    /** L'observateur a créé des suivis vides à la création : on les renseigne. */
    private function indicateurs(PduProject $projet, User $auteur): void
    {
        $suivis = IndicatorTracking::where('pdu_project_id', $projet->id)->with('indicator')->get();

        foreach ($suivis as $i => $suivi) {
            $cible = (float) ($suivi->target_value ?: $suivi->indicator?->target_value ?: 100);
            if ($cible <= 0) {
                $cible = 100;
            }

            // Taux d'atteinte échelonnés de 55 % à 95 %, pour un rendu varié.
            $taux = [0.62, 0.78, 0.55, 0.91, 0.71, 0.84, 0.95][$i % 7];

            $suivi->update([
                'target_value' => $cible,
                'actual_value' => round($cible * $taux, 2),
                'measurement_date' => now()->subMonthsNoOverflow(1)->endOfMonth()->toDateString(),
                'period' => now()->subMonthsNoOverflow(1)->format('Y-m'),
                // Statut de workflow de la mesure, distinct du taux d'atteinte
                // qui, lui, se déduit du rapport valeur réelle / valeur cible.
                'status' => 'validated',
                'recorded_by' => $auteur->id,
            ]);
        }
    }
}
