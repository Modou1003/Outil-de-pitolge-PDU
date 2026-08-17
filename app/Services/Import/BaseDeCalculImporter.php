<?php

namespace App\Services\Import;

use App\Models\BuildingWork;
use App\Models\FinancialProgress;
use App\Models\PduProject;
use App\Models\PhysicalProgress;
use App\Models\ProjectPayment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AlerteService;
use App\Services\ProjectAggregationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Écriture d'une base de calcul lue dans un projet existant.
 *
 * Le principe est celui d'une mise à jour et non d'un remplacement : un import
 * n'ajoute que ce que le projet ne connaît pas encore. Les relevés des mois
 * déjà saisis sont laissés en place — s'ils ont été corrigés à la main, la
 * correction survit —, seuls les mois nouveaux sont créés. Les décomptes déjà
 * enregistrés voient en revanche leur règlement suivi, puisqu'un décompte
 * déposé un mois est réglé le suivant.
 *
 * Un import se rejoue donc sans dommage : deux fois le même fichier ne produit
 * aucun doublon.
 */
class BaseDeCalculImporter
{
    public function __construct(
        protected ProjectAggregationService $agg,
        protected AlerteService $alertes,
        protected ActivityLogger $journal,
    ) {}

    /**
     * Confronte le classeur au projet sans rien écrire : c'est ce que l'écran
     * de contrôle présente à l'utilisateur avant qu'il ne valide.
     *
     * @param  array<string, mixed>  $lecture
     * @return array<string, mixed>
     */
    public function preview(PduProject $projet, array $lecture): array
    {
        $existantes = $projet->physicalProgresses()->pluck('period')->unique();
        $ouvrages = $projet->buildingWorks()->get()->keyBy(fn ($o) => $this->normaliser($o->name));
        $decomptes = $projet->payments()->get()->keyBy(fn ($d) => ltrim($d->number, '0') ?: '0');

        $periodesNouvelles = collect($lecture['periodes'])
            ->filter(fn ($p) => $lecture['arrete_au'] === null || $p <= $lecture['arrete_au'])
            ->reject(fn ($p) => $existantes->contains($p))
            ->values();

        $lignes = [];
        foreach ($lecture['ouvrages'] as $o) {
            $existant = $ouvrages->get($this->normaliser($o['nom']));
            $dernier = $this->dernier($o['reel']);

            $lignes[] = [
                'nom' => $o['nom'],
                'etat' => $existant ? 'connu' : 'nouveau',
                'poids' => $o['poids'],
                'poids_actuel' => $existant ? (float) $existant->weight_percentage : null,
                'avancement' => $dernier,
                'avancement_actuel' => $existant
                    ? (float) ($existant->physicalProgresses()->orderByDesc('period')->value('actual_percentage') ?? 0)
                    : null,
                'debut_prevu' => $o['debut_prevu'],
                'debut_reel' => $o['debut_reel'],
                'retard_source' => $o['retard_mois_source'],
            ];
        }

        $decomptesNouveaux = collect($lecture['decomptes'])->reject(
            fn ($d) => $decomptes->has(ltrim($d['numero'], '0') ?: '0'),
        )->values();

        $reglements = collect($lecture['decomptes'])->filter(function ($d) use ($decomptes) {
            $existant = $decomptes->get(ltrim($d['numero'], '0') ?: '0');

            return $existant && $d['regle'] && ! $existant->is_paid;
        })->values();

        // Effet sur la section financière : la courbe de la valeur acquise se
        // déduit de l'avancement physique et du montant du marché, le coût réel
        // de la facturation du mois.
        $marche = (float) ($projet->budget_allocated ?? 0);
        $financieres = $projet->financialProgresses()->pluck('period');
        $avancementLu = $this->dernier($lecture['projet']['reel'] ?? []);
        $prevuLu = $this->dernier($lecture['projet']['prevu'] ?? []);

        return [
            'arrete_au' => $lecture['arrete_au'],
            'ponderation_totale' => $lecture['ponderation_totale'],
            'anomalies' => $lecture['anomalies'],
            'financier' => [
                'periodes_nouvelles' => $periodesNouvelles->reject(fn ($p) => $financieres->contains($p))->count(),
                'valeur_planifiee' => $marche > 0 && $prevuLu !== null ? round($marche * $prevuLu / 100, 2) : null,
                'valeur_acquise' => $marche > 0 && $avancementLu !== null ? round($marche * $avancementLu / 100, 2) : null,
                'cout_reel' => round(array_sum(array_column($lecture['decomptes'], 'brut')), 2),
            ],
            'periodes_nouvelles' => $periodesNouvelles->all(),
            'periodes_connues' => $existantes->sort()->values()->all(),
            'ouvrages' => $lignes,
            'ouvrages_nouveaux' => collect($lignes)->where('etat', 'nouveau')->count(),
            'decomptes_nouveaux' => $decomptesNouveaux->all(),
            'decomptes_regles' => $reglements->pluck('numero')->all(),
            'avancement_lu' => $this->dernier($lecture['projet']['reel'] ?? []),
            'avancement_actuel' => round((float) $projet->progress_percentage, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $lecture
     * @return array<string, mixed> compte rendu de ce qui a été écrit
     */
    public function import(PduProject $projet, array $lecture, User $auteur, bool $avecDecomptes): array
    {
        $apercu = $this->preview($projet, $lecture);
        $compte = ['ouvrages_crees' => 0, 'ouvrages_maj' => 0, 'releves' => 0, 'decomptes' => 0, 'reglements' => 0];

        DB::transaction(function () use ($projet, $lecture, $auteur, $avecDecomptes, $apercu, &$compte) {
            $ouvrages = $this->synchroniserOuvrages($projet, $lecture['ouvrages'], $compte);
            $compte['releves'] = $this->ajouterReleves($projet, $ouvrages, $lecture, $apercu['periodes_nouvelles']);
            $compte['periodes_financieres'] = $this->ajouterAvancementFinancier(
                $projet, $lecture, $apercu['periodes_nouvelles'], $avecDecomptes, $compte,
            );

            if ($avecDecomptes) {
                $compte['decomptes'] = $this->ajouterDecomptes($projet, $lecture['decomptes'], $auteur);
                $compte['reglements'] = $this->marquerReglements($projet, $lecture['decomptes']);
            }
        });

        // Les indicateurs sont recalculés sur des données désormais complètes,
        // puis les alertes réexaminées.
        $this->agg->recomputeProjectProgress($projet);
        $this->agg->recomputeFinancialCumulatives($projet);
        $this->agg->recomputeProjectBudgetSpent($projet);

        $this->alertes->generateForProject(
            $projet->fresh(['physicalProgresses', 'financialProgresses', 'buildingWorks', 'lots', 'milestones', 'amendments', 'payments'])
        );

        $projet->refresh();

        $this->journal->created(
            'Import',
            sprintf(
                'Import de la base de calcul arrêtée au %s : %d relevé(s), %d ouvrage(s) créé(s), %d décompte(s)',
                $lecture['arrete_au'] ?? 'date inconnue',
                $compte['releves'],
                $compte['ouvrages_crees'],
                $compte['decomptes'],
            ),
            $projet,
            $projet->id,
        );

        return array_merge($compte, [
            'arrete_au' => $lecture['arrete_au'],
            'avancement' => round((float) $projet->progress_percentage, 2),
        ]);
    }

    /**
     * Les ouvrages absents sont créés ; les ouvrages connus voient leur
     * pondération et leur calendrier rafraîchis, la mission de contrôle
     * pouvant les avoir révisés.
     *
     * @return array<string, BuildingWork> indexés par nom normalisé
     */
    protected function synchroniserOuvrages(PduProject $projet, array $definitions, array &$compte): array
    {
        $existants = $projet->buildingWorks()->get()->keyBy(fn ($o) => $this->normaliser($o->name));
        $rang = (int) $projet->buildingWorks()->max('sort_order');
        $resultat = [];

        foreach ($definitions as $d) {
            $cle = $this->normaliser($d['nom']);
            $dernier = $this->dernier($d['reel']);

            $attributs = [
                'weight_percentage' => $d['poids'],
                'duration_days' => $d['duree_jours'],
                'planned_start_date' => $d['debut_prevu'],
                'planned_end_date' => $d['fin_prevue'],
                'actual_start_date' => $d['debut_reel'],
                'status' => match (true) {
                    $dernier === null || $dernier <= 0 => 'not_started',
                    $dernier >= 100 => 'completed',
                    default => 'in_progress',
                },
            ];

            if ($ouvrage = $existants->get($cle)) {
                // Une date saisie à la main ne doit pas être effacée par un
                // classeur qui, lui, ne la renseigne pas.
                $ouvrage->update(array_filter(
                    $attributs,
                    fn ($valeur, $champ) => $valeur !== null || ! str_contains($champ, '_date'),
                    ARRAY_FILTER_USE_BOTH,
                ));
                $compte['ouvrages_maj']++;
            } else {
                $ouvrage = BuildingWork::create(array_merge($attributs, [
                    'pdu_project_id' => $projet->id,
                    'code' => $this->codeLibre($projet, ++$rang),
                    'name' => $d['nom'],
                    'description' => $d['observations'],
                    'sort_order' => $rang,
                ]));
                $compte['ouvrages_crees']++;
            }

            $resultat[$cle] = $ouvrage;
        }

        return $resultat;
    }

    /** Seuls les mois inconnus du projet donnent lieu à un relevé. */
    protected function ajouterReleves(PduProject $projet, array $ouvrages, array $lecture, array $periodes): int
    {
        if ($periodes === []) {
            return 0;
        }

        $ecrits = 0;
        foreach ($lecture['ouvrages'] as $d) {
            $ouvrage = $ouvrages[$this->normaliser($d['nom'])] ?? null;
            if (! $ouvrage) {
                continue;
            }

            // Le cumul se reporte : un mois sans mesure n'est pas un retour à zéro.
            $prevu = 0.0;
            $reel = 0.0;
            foreach ($lecture['periodes'] as $periode) {
                $prevu = (float) ($d['prevu'][$periode] ?? $prevu);
                $reel = (float) ($d['reel'][$periode] ?? $reel);

                if (! in_array($periode, $periodes, true)) {
                    continue;
                }

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
                $ecrits++;
            }
        }

        return $ecrits;
    }

    /**
     * Valeur planifiée et valeur acquise déduites de la courbe du marché ; le
     * coût réel pris sur la facturation de l'entreprise.
     *
     * Les deux premières se déduisent de l'avancement physique et du montant du
     * marché : elles relèvent de qui suit le chantier. Le coût réel, lui, est
     * une donnée de la section financière et n'est inscrit que par qui en a la
     * charge — sans quoi la facturation entrerait en base par la porte de
     * derrière. Un import ultérieur mené par un agent financier complète les
     * périodes restées sans coût.
     */
    protected function ajouterAvancementFinancier(
        PduProject $projet,
        array $lecture,
        array $periodes,
        bool $avecCouts,
        array &$compte,
    ): int {
        if (! $projet->budget_allocated) {
            return 0;
        }

        $marche = (float) $projet->budget_allocated;
        $facture = collect($lecture['decomptes'])
            ->groupBy(fn ($d) => substr($d['date'], 0, 7))
            ->map(fn ($groupe) => $groupe->sum('brut'));

        $connues = $projet->financialProgresses()->get()->keyBy('period');
        $creees = 0;
        $prevuPrec = 0.0;
        $reelPrec = 0.0;

        foreach ($lecture['periodes'] as $periode) {
            $p = (float) ($lecture['projet']['prevu'][$periode] ?? $prevuPrec);
            $r = (float) ($lecture['projet']['reel'][$periode] ?? $reelPrec);
            $cout = round((float) $facture->get($periode, 0), 2);

            if ($existante = $connues->get($periode)) {
                // Période déjà connue : on ne complète que le coût manquant.
                if ($avecCouts && $cout > 0 && (float) $existante->actual_cost <= 0) {
                    $existante->update(['actual_cost' => $cout]);
                    $compte['couts_completes'] = ($compte['couts_completes'] ?? 0) + 1;
                }
            } elseif (in_array($periode, $periodes, true)) {
                FinancialProgress::create([
                    'pdu_project_id' => $projet->id,
                    'period' => $periode,
                    'measurement_date' => Carbon::createFromFormat('Y-m', $periode)->endOfMonth()->toDateString(),
                    'planned_value' => round(($p - $prevuPrec) / 100 * $marche, 2),
                    'earned_value' => round(($r - $reelPrec) / 100 * $marche, 2),
                    'actual_cost' => $avecCouts ? $cout : 0,
                    'status' => 'validated',
                    'recorded_by' => $projet->created_by,
                ]);
                $creees++;
            }

            $prevuPrec = $p;
            $reelPrec = $r;
        }

        return $creees;
    }

    protected function ajouterDecomptes(PduProject $projet, array $decomptes, User $auteur): int
    {
        $connus = $projet->payments()->get()->keyBy(fn ($d) => ltrim($d->number, '0') ?: '0');
        $ecrits = 0;

        foreach ($decomptes as $d) {
            if ($connus->has(ltrim($d['numero'], '0') ?: '0')) {
                continue;
            }

            $date = Carbon::parse($d['date']);
            ProjectPayment::create([
                'pdu_project_id' => $projet->id,
                'number' => str_pad($d['numero'], 3, '0', STR_PAD_LEFT),
                'period' => $date->format('Y-m'),
                'payment_date' => $date->toDateString(),
                'gross_amount' => $d['brut'],
                'startup_advance_recovery' => $d['recuperation_avance'],
                'supply_advance_recovery' => 0,
                'net_paid' => $d['net_paye'],
                'is_paid' => $d['regle'],
                'observations' => $d['nature'] !== 'Travaux' ? $d['nature'] : null,
                'recorded_by' => $auteur->id,
            ]);
            $ecrits++;
        }

        return $ecrits;
    }

    /** Un décompte déposé le mois dernier peut avoir été réglé depuis. */
    protected function marquerReglements(PduProject $projet, array $decomptes): int
    {
        $connus = $projet->payments()->get()->keyBy(fn ($d) => ltrim($d->number, '0') ?: '0');
        $maj = 0;

        foreach ($decomptes as $d) {
            $existant = $connus->get(ltrim($d['numero'], '0') ?: '0');
            if ($existant && $d['regle'] && ! $existant->is_paid) {
                $existant->update(['is_paid' => true]);
                $maj++;
            }
        }

        return $maj;
    }

    /** Le code d'ouvrage est unique sur toute la base. */
    protected function codeLibre(PduProject $projet, int $rang): string
    {
        $prefixe = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $projet->code), -3)) ?: 'OUV';

        do {
            $code = $prefixe . '-' . str_pad((string) $rang, 2, '0', STR_PAD_LEFT);
            $rang++;
        } while (BuildingWork::where('code', $code)->exists());

        return $code;
    }

    protected function normaliser(string $nom): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($nom));
    }

    protected function dernier(array $serie): ?float
    {
        if ($serie === []) {
            return null;
        }
        ksort($serie);

        return round((float) end($serie), 2);
    }
}
