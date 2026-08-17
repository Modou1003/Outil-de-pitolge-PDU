<?php

namespace Database\Seeders;

use App\Models\BuildingWork;
use App\Models\FinancialProgress;
use App\Models\PduProject;
use App\Models\PhysicalProgress;
use App\Models\ProjectPayment;
use App\Models\University;
use App\Models\User;
use App\Services\AlerteService;
use App\Services\ProjectAggregationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Chantier de l'Université d'Odienné — données réelles.
 *
 * Rien n'est ici inventé : les ouvrages, leurs pondérations, leurs calendriers
 * contractuels, les vingt relevés mensuels et les quatorze décomptes
 * proviennent du rapport mensuel d'activité n° 14 du groupement TAEP/IETF,
 * arrêté au 30 juin 2026, et de sa base de calcul d'avancement physique.
 *
 * L'extraction du classeur vers database/data/odienne.json est faite par
 * scripts/extraire_odienne.py ; ce seeder ne fait que charger ce fichier.
 *
 *   php artisan db:seed --class=OdienneProjectSeeder
 *
 * Seul le projet portant le code PRJ-ODN est touché : il est recréé à chaque
 * exécution, le reste du portefeuille reste intact.
 */
class OdienneProjectSeeder extends Seeder
{
    private const CODE = 'PRJ-ODN';

    /** Montant du marché de travaux, hors taxes et hors douane. */
    private const MARCHE = 68_879_636_067;

    /** Avance de démarrage effectivement versée (décompte n° 0). */
    private const AVANCE_DEMARRAGE = 13_775_927_213;

    public function run(): void
    {
        $donnees = $this->donnees();
        if ($donnees === null) {
            return;
        }

        $auteur = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
            ?? User::query()->first();

        if (! $auteur) {
            $this->command?->error('Aucun utilisateur en base : lancez d’abord le seeder des rôles.');

            return;
        }

        PduProject::withTrashed()->where('code', self::CODE)->forceDelete();

        $projet = $this->projet($auteur);
        $ouvrages = $this->ouvrages($projet, $donnees['ouvrages']);
        $this->avancementPhysique($projet, $ouvrages, $donnees['ouvrages']);
        $this->avancementFinancier($projet, $donnees);
        $this->decomptes($projet, $auteur, $donnees['decomptes']);

        $agg = app(ProjectAggregationService::class);
        $agg->recomputeProjectProgress($projet);
        $agg->recomputeFinancialCumulatives($projet);
        $agg->recomputeProjectBudgetSpent($projet);

        app(AlerteService::class)->generateForProject(
            $projet->fresh(['physicalProgresses', 'financialProgresses', 'buildingWorks', 'lots', 'milestones', 'amendments', 'payments'])
        );

        $this->rapport($projet->refresh(), $donnees);
    }

    /** @return array<string, mixed>|null */
    private function donnees(): ?array
    {
        $chemin = database_path('data/odienne.json');

        if (! is_file($chemin)) {
            $this->command?->error("Fichier introuvable : {$chemin}");
            $this->command?->line('Produisez-le avec : python scripts/extraire_odienne.py <classeur.xlsx> database/data/odienne.json');

            return null;
        }

        return json_decode(file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);
    }

    private function projet(User $auteur): PduProject
    {
        $universite = University::firstOrCreate(
            ['acronym' => 'UODN'],
            [
                'name' => 'Université d’Odienné',
                'location' => 'Odienné',
                'region' => 'Kabadougou',
                'status' => 'active',
                'latitude' => 9.5052,
                'longitude' => -7.5640,
            ],
        );

        return PduProject::create([
            'code' => self::CODE,
            'title' => 'Travaux de construction de l’Université d’Odienné — tranche 1',
            'description' => "Marché n° 2024-0-00-00-2-0503/05-333. Construction de la première tranche du campus : bâtiments d'enseignement et de recherche, hébergement, logements de fonction, ouvrages de voirie et réseaux divers, sur une emprise de 400 hectares. Financement de la Banque islamique de développement avec contrepartie de l'État. Entreprise titulaire : PFO CONSTRUCTION. Assistance à maîtrise d'ouvrage : groupement TAEP/IETF.",
            'university_id' => $universite->id,
            'created_by' => $auteur->id,
            'start_date' => '2024-11-04',
            'end_date' => '2026-11-03',
            'planned_completion_date' => '2026-11-03',
            'status' => 'in_progress',
            'type' => 'construction',
            'budget_allocated' => self::MARCHE,
            'startup_advance_amount' => self::AVANCE_DEMARRAGE,
            'currency' => 'XOF',
            'objectives' => [
                'Ouvrir quatre composantes universitaires pour trois mille étudiants',
                'Doter la région du Kabadougou d’un campus complet',
                'Achever la première tranche dans le délai contractuel de vingt-quatre mois',
            ],
        ]);
    }

    /**
     * Un ouvrage par poste suivi, avec sa pondération et son calendrier
     * contractuel. Le statut se déduit de l'avancement constaté.
     *
     * @return array<int, BuildingWork>
     */
    private function ouvrages(PduProject $projet, array $definitions): array
    {
        $ouvrages = [];

        foreach ($definitions as $i => $d) {
            $dernier = $this->dernier($d['reel']);

            $ouvrages[] = BuildingWork::create([
                'pdu_project_id' => $projet->id,
                // Le code d'ouvrage est unique sur toute la base : il porte
                // donc le trigramme du projet.
                'code' => 'ODN-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'name' => $this->nomPropre($d['nom']),
                'description' => $d['observations'] ?? null,
                'status' => match (true) {
                    $dernier === null || $dernier <= 0 => 'not_started',
                    $dernier >= 100 => 'completed',
                    default => 'in_progress',
                },
                'weight_percentage' => $d['poids'],
                'duration_days' => $d['duree_jours'] ?? null,
                'planned_start_date' => $d['debut_prevu'] ?? $d['debut_planning'] ?? null,
                'planned_end_date' => $d['fin_prevue'] ?? $d['fin_planning'] ?? null,
                'actual_start_date' => $d['debut_reel'] ?? null,
                'sort_order' => $i + 1,
            ]);
        }

        return $ouvrages;
    }

    /** Vingt relevés mensuels par ouvrage, prévu et réalisé cumulés. */
    private function avancementPhysique(PduProject $projet, array $ouvrages, array $definitions): void
    {
        foreach ($ouvrages as $i => $ouvrage) {
            $d = $definitions[$i];
            $periodes = array_keys($d['reel'] + $d['prevu']);
            sort($periodes);

            // Un mois sans mesure ne signifie pas un retour à zéro : le cumul
            // reste à sa dernière valeur connue.
            $prevu = 0.0;
            $reel = 0.0;

            foreach ($periodes as $periode) {
                $prevu = (float) ($d['prevu'][$periode] ?? $prevu);
                $reel = (float) ($d['reel'][$periode] ?? $reel);

                PhysicalProgress::create([
                    'pdu_project_id' => $projet->id,
                    'building_work_id' => $ouvrage->id,
                    'period' => $periode,
                    'measurement_date' => Carbon::createFromFormat('Y-m', $periode)->endOfMonth()->toDateString(),
                    'planned_percentage' => min(100, $prevu),
                    'actual_percentage' => min(100, $reel),
                    'status' => 'validated',
                    'recorded_by' => $projet->created_by,
                ]);
            }
        }
    }

    /**
     * Valeur planifiée et valeur acquise déduites de la courbe physique du
     * marché ; coût réel pris sur la facturation de l'entreprise, seule
     * dépense réellement engagée dont l'unité de gestion ait connaissance.
     */
    private function avancementFinancier(PduProject $projet, array $donnees): void
    {
        $prevu = $donnees['projet']['prevu'];
        $reel = $donnees['projet']['reel'];
        $decomptes = collect($donnees['decomptes'])
            ->groupBy(fn ($d) => substr($d['date'], 0, 7))
            ->map(fn ($groupe) => $groupe->sum('brut'));

        $prevuPrec = 0.0;
        $reelPrec = 0.0;

        foreach ($donnees['periodes'] as $periode) {
            $p = (float) ($prevu[$periode] ?? $prevuPrec);
            $r = (float) ($reel[$periode] ?? $reelPrec);

            FinancialProgress::create([
                'pdu_project_id' => $projet->id,
                'period' => $periode,
                'measurement_date' => Carbon::createFromFormat('Y-m', $periode)->endOfMonth()->toDateString(),
                'planned_value' => round(($p - $prevuPrec) / 100 * self::MARCHE, 2),
                'earned_value' => round(($r - $reelPrec) / 100 * self::MARCHE, 2),
                'actual_cost' => round((float) $decomptes->get($periode, 0), 2),
                'status' => 'validated',
                'recorded_by' => $projet->created_by,
            ]);

            $prevuPrec = $p;
            $reelPrec = $r;
        }
    }

    /** Décomptes de l'entreprise, avance de démarrage comprise. */
    private function decomptes(PduProject $projet, User $auteur, array $lignes): void
    {
        foreach ($lignes as $ligne) {
            $date = Carbon::parse($ligne['date']);

            ProjectPayment::create([
                'pdu_project_id' => $projet->id,
                'number' => str_pad($ligne['numero'], 3, '0', STR_PAD_LEFT),
                'period' => $date->format('Y-m'),
                'payment_date' => $date->toDateString(),
                'gross_amount' => $ligne['brut'],
                'startup_advance_recovery' => $ligne['recuperation_avance'],
                'supply_advance_recovery' => 0,
                'net_paid' => $ligne['net_paye'] ?? ($ligne['brut'] - $ligne['recuperation_avance']),
                'is_paid' => $ligne['regle'],
                'observations' => $ligne['nature'] !== 'Travaux' ? $ligne['nature'] : null,
                'recorded_by' => $auteur->id,
            ]);
        }
    }

    /** Les intitulés du classeur sont en capitales et parfois accolés. */
    private function nomPropre(string $nom): string
    {
        $nom = preg_replace('/\bENVIRONNEMENTAMENAGEMENT\b/u', 'ENVIRONNEMENT — AMENAGEMENT', $nom);

        return mb_convert_case(trim($nom), MB_CASE_TITLE, 'UTF-8');
    }

    private function dernier(array $serie): ?float
    {
        if ($serie === []) {
            return null;
        }
        ksort($serie);

        return (float) end($serie);
    }

    private function rapport(PduProject $projet, array $donnees): void
    {
        $retards = $projet->buildingWorks
            ->filter(fn ($o) => $o->start_delay_days > 15)
            ->sortByDesc('start_delay_days');

        $this->command?->info('Chantier d’Odienné chargé : ' . $projet->code . ' (arrêté au ' . $donnees['arrete_au'] . ')');
        $this->command?->table(['Indicateur', 'Valeur'], [
            ['Avancement physique consolidé', number_format((float) $projet->progress_percentage, 2) . ' %'],
            ['Attendu au rapport n° 14', '32,13 %'],
            ['Marché', number_format(self::MARCHE, 0, ',', ' ') . ' XOF'],
            ['Décaissé', number_format((float) $projet->budget_spent, 0, ',', ' ') . ' XOF'],
            ['Ouvrages', $projet->buildingWorks()->count()],
            ['Somme des pondérations', number_format((float) $projet->buildingWorks()->sum('weight_percentage'), 2) . ' %'],
            ['Relevés physiques', $projet->physicalProgresses()->count()],
            ['Relevés financiers', $projet->financialProgresses()->count()],
            ['Décomptes', $projet->payments()->count()],
            ['Ouvrages en retard de démarrage', $retards->count()],
            ['Alertes ouvertes', $projet->alerts()->where('is_resolved', false)->count()],
        ]);

        if ($retards->isNotEmpty()) {
            $this->command?->line('');
            $this->command?->info('Retards au démarrage relevés par l’application :');
            $this->command?->table(
                ['Ouvrage', 'Début prévu', 'Début réel', 'Retard (j)', 'Retard AMO'],
                $retards->take(12)->map(fn ($o) => [
                    mb_strimwidth($o->name, 0, 34, '…'),
                    $o->planned_start_date?->format('d/m/Y') ?? '—',
                    $o->actual_start_date?->format('d/m/Y') ?? 'non démarré',
                    $o->start_delay_days,
                    $this->retardSource($donnees, $o->name),
                ])->all(),
            );
        }
    }

    /** Retard annoncé par l'assistance à maîtrise d'ouvrage, pour comparaison. */
    private function retardSource(array $donnees, string $nom): string
    {
        foreach ($donnees['ouvrages'] as $d) {
            if ($this->nomPropre($d['nom']) === $nom) {
                return ($d['retard_mois_source'] ?? null) ? $d['retard_mois_source'] . ' mois' : '—';
            }
        }

        return '—';
    }
}
