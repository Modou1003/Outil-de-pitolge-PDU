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
                'debut_prevu' => $o['debut_prevu'] ?? null,
                'debut_reel' => $o['debut_reel'] ?? null,
                'retard_source' => $o['retard_mois_source'] ?? null,
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
        $avancementLu = $this->dernier($lecture['projet']['reel'] ?? []);
        $prevuLu = $this->dernier($lecture['projet']['prevu'] ?? []);

        // Chaque ouvrage porte sa propre valeur acquise : c'est à cette maille
        // que l'écran financier lit les données.
        $enveloppes = round(array_sum(array_map(
            fn ($o) => (float) ($o['enveloppe'] ?? 0) ?: $marche * (float) $o['poids'] / 100,
            $lecture['ouvrages'],
        )), 2);
        $facture = round(array_sum(array_map(
            fn ($o) => (float) ($o['facture_cumulee'] ?? 0),
            $lecture['ouvrages'],
        )), 2);

        return [
            'arrete_au' => $lecture['arrete_au'],
            'ponderation_totale' => $lecture['ponderation_totale'],
            'anomalies' => $lecture['anomalies'],
            'financier' => [
                'lignes_nouvelles' => $periodesNouvelles->count() * count($lecture['ouvrages']),
                'enveloppes' => $enveloppes,
                'valeur_planifiee' => $prevuLu !== null ? round($enveloppes * $prevuLu / 100, 2) : null,
                'valeur_acquise' => $avancementLu !== null ? round($enveloppes * $avancementLu / 100, 2) : null,
                'cout_reel' => $facture ?: round(array_sum(array_column($lecture['decomptes'], 'brut')), 2),
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
            $compte['releves'] = $this->ajouterReleves($projet, $ouvrages, $lecture, $apercu['periodes_nouvelles'], $auteur);
            $compte['periodes_financieres'] = $this->ajouterAvancementFinancier(
                $projet, $ouvrages, $lecture, $apercu['periodes_nouvelles'], $avecDecomptes, $auteur, $compte,
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
                // Enveloppe contractuelle : base de la valeur acquise, et
                // budget à l'achèvement du périmètre suivi.
                'contract_amount' => ($d['enveloppe'] ?? null) ?: null,
                'duration_days' => $d['duree_jours'] ?? null,
                'planned_start_date' => $d['debut_prevu'] ?? null,
                'planned_end_date' => $d['fin_prevue'] ?? null,
                'actual_start_date' => $d['debut_reel'] ?? null,
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
                    'description' => $d['observations'] ?? null,
                    'sort_order' => $rang,
                ]));
                $compte['ouvrages_crees']++;
            }

            $resultat[$cle] = $ouvrage;
        }

        return $resultat;
    }

    /** Seuls les mois inconnus du projet donnent lieu à un relevé. */
    protected function ajouterReleves(PduProject $projet, array $ouvrages, array $lecture, array $periodes, User $auteur): int
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
                    'recorded_by' => $auteur->id,
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
        array $ouvrages,
        array $lecture,
        array $periodes,
        bool $avecCouts,
        User $auteur,
        array &$compte,
    ): int {
        if (! $projet->budget_allocated) {
            return 0;
        }

        $marche = (float) $projet->budget_allocated;
        $creees = 0;

        // La provision pour révision de prix est facturée au marché sans se
        // rattacher à un ouvrage. L'ignorer minorerait le coût réel ; elle est
        // donc répartie au prorata de la facturation de chaque ouvrage, ce qui
        // laisse le total exact et n'affecte pas la comparaison entre ouvrages.
        $revision = (float) ($lecture['revision_facturee'] ?? 0);
        $factureTotale = array_sum(array_map(
            fn ($o) => (float) ($o['facture_cumulee'] ?? 0),
            $lecture['ouvrages'],
        ));

        foreach ($lecture['ouvrages'] as $d) {
            $ouvrage = $ouvrages[$this->normaliser($d['nom'])] ?? null;
            if (! $ouvrage) {
                continue;
            }

            // Enveloppe de l'ouvrage : son montant contractuel s'il est connu,
            // à défaut sa part du marché au prorata de sa pondération.
            $enveloppe = (float) ($d['enveloppe'] ?? 0) ?: $marche * (float) $d['poids'] / 100;
            $connues = $ouvrage->financialProgresses()->get()->keyBy('period');

            $facture = (float) ($d['facture_cumulee'] ?? 0);
            $quotePart = ($revision > 0 && $factureTotale > 0)
                ? $revision * $facture / $factureTotale
                : 0.0;
            $couts = $this->repartirCout($d, $lecture['periodes'], $quotePart);

            $prevuPrec = 0.0;
            $reelPrec = 0.0;

            foreach ($lecture['periodes'] as $periode) {
                $p = (float) ($d['prevu'][$periode] ?? $prevuPrec);
                $r = (float) ($d['reel'][$periode] ?? $reelPrec);
                $cout = round($couts[$periode] ?? 0, 2);

                if ($existante = $connues->get($periode)) {
                    // Période déjà connue : on ne complète que le coût manquant.
                    if ($avecCouts && $cout > 0 && (float) $existante->actual_cost <= 0) {
                        $existante->update(['actual_cost' => $cout]);
                        $compte['couts_completes'] = ($compte['couts_completes'] ?? 0) + 1;
                    }
                } elseif (in_array($periode, $periodes, true)) {
                    FinancialProgress::create([
                        'pdu_project_id' => $projet->id,
                        'building_work_id' => $ouvrage->id,
                        'period' => $periode,
                        'measurement_date' => Carbon::createFromFormat('Y-m', $periode)->endOfMonth()->toDateString(),
                        'planned_value' => round(($p - $prevuPrec) / 100 * $enveloppe, 2),
                        'earned_value' => round(($r - $reelPrec) / 100 * $enveloppe, 2),
                        'actual_cost' => $avecCouts ? $cout : 0,
                        'status' => 'validated',
                        'recorded_by' => $auteur->id,
                    ]);
                    $creees++;
                }

                $prevuPrec = $p;
                $reelPrec = $r;
            }
        }

        return $creees;
    }

    /**
     * Ventilation mensuelle du coût réel d'un ouvrage.
     *
     * La facturation n'est connue que cumulée par ouvrage, et mensuelle
     * seulement à l'échelle du marché. Le cumul de l'ouvrage est donc réparti
     * dans le temps au rythme de sa valeur acquise : le total de chaque ouvrage
     * et celui du marché restent exacts, seule la répartition entre les mois
     * est reconstituée. L'indice de performance des coûts en fin de période,
     * lui, est juste.
     *
     * @return array<string, float>
     */
    protected function repartirCout(array $ouvrage, array $periodes, float $quotePartRevision = 0.0): array
    {
        $total = (float) ($ouvrage['facture_cumulee'] ?? 0) + $quotePartRevision;
        if ($total <= 0) {
            return [];
        }

        $increments = [];
        $precedent = 0.0;
        $somme = 0.0;
        foreach ($periodes as $periode) {
            $reel = (float) ($ouvrage['reel'][$periode] ?? $precedent);
            $increments[$periode] = max(0.0, $reel - $precedent);
            $somme += $increments[$periode];
            $precedent = $reel;
        }

        // Ouvrage facturé sans avancement constaté : tout est porté au dernier mois.
        if ($somme <= 0) {
            return [end($periodes) => $total];
        }

        return array_map(fn ($part) => $total * $part / $somme, $increments);
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
                'is_advance' => $this->estUneAvance($d),
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

    /**
     * Une avance versée à l'entreprise est enregistrée comme décompte, à
     * l'exemple du tableau de facturation de la mission de contrôle. La
     * distinguer évite de la compter deux fois dans l'encaissement.
     */
    protected function estUneAvance(array $ligne): bool
    {
        return (bool) preg_match('/avance|acompte/i', $ligne['nature'] ?? '');
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
