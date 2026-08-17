<?php

namespace App\Services\Import;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Lecture de la base de calcul d'avancement physique établie mensuellement par
 * la mission de contrôle.
 *
 * Le classeur n'est pas interprété : sa structure est connue et transposée.
 * Trois feuilles seulement sont exploitées.
 *
 *   « progress »        : la courbe planifiée et réalisée du marché, puis celle
 *                         de chaque poste, avec sa pondération ;
 *   « récap mois »      : le planning contractuel, ouvrage par ouvrage, et le
 *                         démarrage réellement constaté ;
 *   « facturation PFO » : les décomptes de l'entreprise et leur règlement.
 *
 * Toute évolution de ce modèle de classeur se traduira par une lecture vide
 * plutôt que par une donnée fausse : c'est l'écran de contrôle qui la signale.
 */
class BaseDeCalculReader
{
    /** Postes de premier niveau, dont la somme des poids fait le marché entier. */
    protected const GROUPES = [
        'INSTALLATIONSDECHANTIER',
        'BATIMENTS',
        'MOBILIERSETEQUIPEMENTS',
        'TERRASSEMENTSVRDAMENAGEMENTSEXTERIEURS',
        'EFFICACITEENERGETIQUE',
    ];

    /** Groupes suivis d'un seul bloc, sans descendre à leurs postes. */
    protected const GROUPES_FEUILLES = [
        'INSTALLATIONSDECHANTIER',
        'MOBILIERSETEQUIPEMENTS',
        'EFFICACITEENERGETIQUE',
    ];

    /** Postes dépourvus de pondération, hors périmètre de l'avancement. */
    protected const IGNORES = [
        'ETUDESDEXECUTION',
        'REPLIEMENTREMISEENETATDUCHANTIER',
    ];

    /**
     * Le planning ne nomme pas toujours l'ouvrage comme la feuille
     * d'avancement ; ces correspondances sont établies par lecture des deux.
     */
    protected const CORRESPONDANCES = [
        'PRESIDENCEADMINISTRATIONCENTRALECELODATCENTERPCSECURITE' => ['BATIMENTPRESIDENCE'],
        // Les trois logements de fonction ne forment qu'un poste d'avancement.
        'LOGEMENTSDEFONCTIONVILLADUPRESIDENTVILLASDESDRETVICEPRESIDENTLOGEMENTDASTREINTE' => [
            'LOGEMENTDUPRESIDENT', 'LOGEMENTSDUVICEPRETDIRECTEURS', 'LOGEMENTSDASTREINTE',
        ],
        'TERRASSEMENTS' => ['TERRASSEMENTSPLATEFORME'],
        'VOIRIESAIRESDESTATIONNEMENTETPARKING' => ['VOIRIESAIRESDESTATIONNEMENTET'],
    ];

    /**
     * La feuille de facturation par tâche nomme les ouvrages autrement encore.
     * Ces rapprochements sont établis par lecture comparée des deux feuilles.
     */
    protected const CORRESPONDANCES_FINANCIERES = [
        'EAUXUSEES' => 'ASSAINISSEMENENTEAUXUSEESSTEP',
        'ADDUCTIONENEAUXPOTABLEAEP' => 'AEPHORSCHATEAUDEAU',
        'EAUXPLUVIALE' => 'EAUPLUVIALE',
        'RESEAUFIBRE' => 'RESEAUTELECOMFIBREOPTIQUE',
        'SIGNALISATIONINTERIEURE' => 'SIGNALETIQUEINTERIEURE',
        'ENVIRONNEMENTAIREDEJEUX' => 'AIREDEJEUX',
        'ENVIRONNEMENTAMENAGEMENTPAYSAGER' => 'AMENAGEMENTPAYSAGER',
        // Postes suivis d'un bloc : c'est leur total qui fait l'enveloppe.
        'MOBILIERSETEQUIPEMENTS' => 'TOTALMOBILIEREQUIPEMENT',
        'EFFICACITEENERGETIQUE' => 'SOUSTOTALEFFICACITEENERGETIQUE',
    ];

    /** Lignes de synthèse de la feuille de facturation, à ne pas prendre pour des ouvrages. */
    protected const TOTAUX = ['SOUSTOTAL', 'TOTALGENERAL', 'TOTALVRD'];

    protected const MOIS = [
        'jan' => 1, 'fev' => 2, 'mar' => 3, 'avr' => 4, 'mai' => 5, 'jui' => 6,
        'jul' => 7, 'aou' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    /**
     * @return array{arrete_au: ?string, periodes: array<int, string>, projet: array, ouvrages: array<int, array>, decomptes: array<int, array>, anomalies: array<int, string>}
     */
    public function read(string $chemin): array
    {
        $classeur = IOFactory::createReader(IOFactory::identify($chemin));
        $classeur->setReadDataOnly(true);
        $classeur->setLoadSheetsOnly(['progress', 'récap mois', 'facturation PFO', 'détail facturation PFO tache']);
        $document = $classeur->load($chemin);

        $anomalies = [];
        $feuille = fn (string $nom) => $document->getSheetByName($nom);

        if (! $feuille('progress')) {
            throw new RuntimeException("La feuille « progress » est absente du classeur : ce n'est pas une base de calcul d'avancement.");
        }

        [$periodes, $projet, $ouvrages] = $this->lireAvancement($feuille('progress'));

        if ($ouvrages === []) {
            throw new RuntimeException('Aucun ouvrage pondéré n’a pu être lu : la structure du classeur a probablement changé.');
        }

        $calendriers = $feuille('récap mois') ? $this->lireCalendrier($feuille('récap mois')) : [];
        if ($calendriers === []) {
            $anomalies[] = 'Le planning contractuel n’a pas pu être lu : les dates des ouvrages ne seront pas mises à jour.';
        }

        $enveloppes = $feuille('détail facturation PFO tache')
            ? $this->lireEnveloppes($feuille('détail facturation PFO tache'))
            : [];
        if ($enveloppes === []) {
            $anomalies[] = 'La facturation par tâche n’a pas pu être lue : les enveloppes des ouvrages seront réparties au prorata de leur pondération.';
        }

        foreach ($ouvrages as &$ouvrage) {
            $trouves = $this->rapprocher($ouvrage['cle'], $calendriers);
            if ($trouves !== []) {
                $ouvrage = array_merge($ouvrage, $this->fusionner($trouves));
            } elseif ($calendriers !== []) {
                $anomalies[] = sprintf('Aucune tâche de planning pour « %s » : ses dates restent inchangées.', $ouvrage['nom']);
            }

            $enveloppe = $this->enveloppe($ouvrage['cle'], $enveloppes);
            if ($enveloppe !== null) {
                $ouvrage['enveloppe'] = $enveloppe['montant'];
                $ouvrage['facture_cumulee'] = $enveloppe['facture'];
            } elseif ($enveloppes !== []) {
                $anomalies[] = sprintf('Aucun montant contractuel pour « %s » : son enveloppe est déduite de sa pondération.', $ouvrage['nom']);
            }

            unset($ouvrage['cle']);
        }
        unset($ouvrage);

        $decomptes = $feuille('facturation PFO') ? $this->lireDecomptes($feuille('facturation PFO')) : [];
        if ($decomptes === []) {
            $anomalies[] = 'Aucun décompte n’a pu être lu dans la feuille de facturation.';
        }

        $total = round(array_sum(array_column($ouvrages, 'poids')), 2);
        if (abs($total - 100) > 0.5) {
            $anomalies[] = sprintf('La somme des pondérations lues atteint %s %% au lieu de 100 %%.', number_format($total, 2, ',', ' '));
        }

        return [
            'arrete_au' => $this->arreteAu($projet, $periodes),
            'periodes' => $periodes,
            'projet' => $projet,
            'ouvrages' => $ouvrages,
            'decomptes' => $decomptes,
            'ponderation_totale' => $total,
            'anomalies' => $anomalies,
        ];
    }

    // ───────────────────────────────────────────────── feuille « progress »

    /** @return array{0: array<int, string>, 1: array, 2: array<int, array>} */
    protected function lireAvancement(Worksheet $ws): array
    {
        $lignes = $this->grille($ws);

        // La ligne des mois porte les seules dates de l'en-tête.
        $entete = $lignes[2] ?? [];
        $colonne0 = null;
        $periodes = [];
        foreach ($entete as $i => $valeur) {
            $date = $this->date($valeur);
            if ($date === null) {
                continue;
            }
            $colonne0 ??= $i;
            $periodes[] = $date->format('Y-m');
        }

        if ($colonne0 === null) {
            throw new RuntimeException('La ligne des périodes mensuelles est introuvable dans la feuille « progress ».');
        }

        $ouvrages = [];
        $projet = ['prevu' => [], 'reel' => []];
        $groupe = null;
        $poidsGroupe = 100.0;

        foreach ($lignes as $i => $ligne) {
            $nom = $ligne[3] ?? null;
            $poids = $ligne[8] ?? null;
            if (! is_string($nom) || trim($nom) === '' || ! is_numeric($poids)) {
                continue;
            }

            $cle = $this->cle($nom);
            if (str_contains($cle, 'AVANCEMENTPHYSIQUEGLOBAL')) {
                if ($projet['reel'] === []) {
                    $projet = $this->series($lignes, $i, $colonne0, $periodes);
                }

                continue;
            }
            if (in_array($cle, self::IGNORES, true)) {
                continue;
            }

            $feuilleUnique = in_array($cle, self::GROUPES_FEUILLES, true);
            if (in_array($cle, self::GROUPES, true)) {
                $groupe = $cle;
                $poidsGroupe = (float) $poids * 100;
                if (! $feuilleUnique) {
                    continue; // ses postes seront lus un à un
                }
                $groupe = null;
            } elseif ($groupe === null) {
                continue; // poste d'un groupe suivi globalement
            }

            $ouvrages[] = array_merge([
                'nom' => $this->nomPropre($nom),
                'cle' => $cle,
                // Poids ramené au marché : celui du groupe multiplié par le sien.
                'poids' => round($feuilleUnique ? (float) $poids * 100 : $poidsGroupe * (float) $poids, 4),
                'duree_jours' => null,
                'debut_prevu' => $this->jour($ligne[4] ?? null),
                'fin_prevue' => $this->jour($ligne[5] ?? null),
                'debut_reel' => null,
                'retard_mois_source' => null,
                'observations' => null,
                'enveloppe' => null,
                'facture_cumulee' => null,
            ], $this->series($lignes, $i, $colonne0, $periodes));
        }

        return [$periodes, $projet, $ouvrages];
    }

    /** Les lignes suivant un poste portent ses cumuls mensuels. */
    protected function series(array $lignes, int $i, int $colonne0, array $periodes): array
    {
        $out = ['prevu' => [], 'reel' => []];

        for ($j = $i + 1; $j <= $i + 6; $j++) {
            $libelle = $this->sansAccent((string) ($lignes[$j][3] ?? ''));
            $libelle = mb_strtolower($libelle);
            $cible = str_starts_with($libelle, 'prevision cumul') ? 'prevu'
                : (str_starts_with($libelle, 'realisation cumul') ? 'reel' : null);
            if ($cible === null) {
                continue;
            }

            foreach ($periodes as $k => $periode) {
                $valeur = $lignes[$j][$colonne0 + $k] ?? null;
                if (is_numeric($valeur)) {
                    $out[$cible][$periode] = min(100.0, round((float) $valeur * 100, 2));
                }
            }
        }

        return $out;
    }

    /** Dernière période effectivement constatée : la situation y est arrêtée. */
    protected function arreteAu(array $projet, array $periodes): ?string
    {
        $constatees = array_keys($projet['reel'] ?? []);
        if ($constatees === []) {
            return null;
        }
        sort($constatees);

        return end($constatees);
    }

    // ─────────────────────────────────────────────── feuille « récap mois »

    protected function lireCalendrier(Worksheet $ws): array
    {
        $calendriers = [];

        foreach ($this->grille($ws) as $ligne) {
            $nom = $ligne[2] ?? null;
            $duree = $ligne[3] ?? null;
            // Une durée de tâche se compte en jours, pas en milliers : au-delà,
            // c'est une date exprimée en numéro de série qui a été lue.
            if (! is_string($nom) || trim($nom) === '' || ! is_numeric($duree)
                || $duree < 1 || $duree > 5000) {
                continue;
            }

            $cle = $this->cle($nom);
            // La feuille reprend plus bas les mêmes intitulés à d'autres fins :
            // seule la première occurrence, celle du planning, fait foi.
            if (isset($calendriers[$cle])) {
                continue;
            }

            $calendriers[$cle] = [
                'duree_jours' => (int) round((float) $duree),
                'debut_prevu' => $this->jour($ligne[4] ?? null),
                'fin_prevue' => $this->jour($ligne[5] ?? null),
                'debut_reel' => $this->moisTexte($ligne[6] ?? null),
                'retard_mois_source' => $this->entier($ligne[7] ?? null),
                'observations' => $this->texte($ligne[10] ?? null),
            ];
        }

        return $calendriers;
    }

    /** @return array<int, array> */
    protected function rapprocher(string $cle, array $calendriers): array
    {
        $candidats = self::CORRESPONDANCES[$cle] ?? [$cle];
        $trouves = [];
        foreach ($candidats as $candidat) {
            if (isset($calendriers[$candidat])) {
                $trouves[] = $calendriers[$candidat];
            }
        }
        if ($trouves !== []) {
            return $trouves;
        }

        // Rapprochement souple sur un préfixe commun. Le seuil de douze
        // caractères n'est pas cosmétique : un préfixe court rapprocherait
        // n'importe quel ouvrage de n'importe quelle tâche.
        if (mb_strlen($cle) < 12) {
            return [];
        }

        $debut = mb_substr($cle, 0, 12);
        foreach ($calendriers as $autre => $calendrier) {
            if (mb_strlen($autre) < 12) {
                continue;
            }
            if (str_starts_with($autre, $debut) || str_starts_with($cle, mb_substr($autre, 0, 12))) {
                return [$calendrier];
            }
        }

        return [];
    }

    /** Plusieurs tâches pour un même ouvrage : on retient l'enveloppe. */
    protected function fusionner(array $calendriers): array
    {
        $colonne = fn (string $clef) => array_values(array_filter(array_column($calendriers, $clef)));

        $debuts = $colonne('debut_prevu');
        $fins = $colonne('fin_prevue');
        $reels = $colonne('debut_reel');
        $durees = $colonne('duree_jours');
        $retards = $colonne('retard_mois_source');

        return [
            'duree_jours' => $durees === [] ? null : max($durees),
            'debut_prevu' => $debuts === [] ? null : min($debuts),
            'fin_prevue' => $fins === [] ? null : max($fins),
            'debut_reel' => $reels === [] ? null : min($reels),
            'retard_mois_source' => $retards === [] ? null : max($retards),
            'observations' => $calendriers[0]['observations'] ?? null,
        ];
    }

    // ────────────────────────── feuille « détail facturation PFO tache »

    /**
     * Montant contractuel et facturation cumulée de chaque ouvrage.
     *
     * Cette feuille est la seule à ventiler la dépense par ouvrage. Elle donne
     * ainsi à chacun son enveloppe — bien plus juste qu'une répartition du
     * marché au prorata de la pondération physique, les deux poids ne se
     * confondant pas — et son coût réel.
     *
     * @return array<string, array{montant: float, facture: float}>
     */
    protected function lireEnveloppes(Worksheet $ws): array
    {
        $enveloppes = [];

        foreach ($this->grille($ws) as $ligne) {
            $nom = $ligne[2] ?? null;
            $montant = is_numeric($ligne[3] ?? null) ? (float) $ligne[3] : 0.0;
            $facture = is_numeric($ligne[5] ?? null) ? (float) $ligne[5] : 0.0;
            // Un poste peut être facturé sans porter de montant contractuel
            // propre : sa dépense compte tout autant.
            if (! is_string($nom) || trim($nom) === '' || ($montant <= 0 && $facture <= 0)) {
                continue;
            }

            $cle = $this->cle($nom);
            // Les totaux intermédiaires ne sont pas des ouvrages — sauf ceux qui
            // servent d'enveloppe à un poste suivi globalement.
            $estTotal = false;
            foreach (self::TOTAUX as $prefixe) {
                $estTotal = $estTotal || str_starts_with($cle, $prefixe);
            }
            if ($estTotal && ! in_array($cle, self::CORRESPONDANCES_FINANCIERES, true)) {
                continue;
            }
            if (isset($enveloppes[$cle])) {
                continue;
            }

            $enveloppes[$cle] = [
                'montant' => round($montant, 2),
                'facture' => round($facture, 2),
            ];
        }

        return $enveloppes;
    }

    /** @return array{montant: float, facture: float}|null */
    protected function enveloppe(string $cle, array $enveloppes): ?array
    {
        if (isset(self::CORRESPONDANCES_FINANCIERES[$cle])) {
            return $enveloppes[self::CORRESPONDANCES_FINANCIERES[$cle]] ?? null;
        }
        if (isset($enveloppes[$cle])) {
            return $enveloppes[$cle];
        }

        // Rapprochement par préfixe : « Restaurant » et « Restaurant
        // universitaire » désignent le même ouvrage.
        if (mb_strlen($cle) < 7) {
            return null;
        }
        foreach ($enveloppes as $autre => $enveloppe) {
            if (mb_strlen($autre) >= 7
                && (str_starts_with($autre, mb_substr($cle, 0, 7)) || str_starts_with($cle, mb_substr($autre, 0, 7)))) {
                return $enveloppe;
            }
        }

        return null;
    }

    // ────────────────────────────────────────── feuille « facturation PFO »

    protected function lireDecomptes(Worksheet $ws): array
    {
        $decomptes = [];

        foreach ($this->grille($ws) as $ligne) {
            // Les lignes de totaux referment le tableau des décomptes.
            if (is_string($ligne[2] ?? null) && preg_match('/total|montant du march/iu', $ligne[2])) {
                break;
            }

            $date = $this->date($ligne[3] ?? null);
            $brut = $ligne[5] ?? null;
            // Un décompte de travaux ne se chiffre pas en milliers de francs :
            // ce seuil écarte les lignes de service placées sous le tableau.
            if ($date === null || ! is_numeric($brut) || $brut < 1_000_000) {
                continue;
            }

            $numero = $this->entier($ligne[2] ?? null);
            $recuperation = 0.0;
            foreach ([6, 7] as $colonne) {
                if (is_numeric($ligne[$colonne] ?? null)) {
                    $recuperation += (float) $ligne[$colonne];
                }
            }
            $net = is_numeric($ligne[8] ?? null) ? (float) $ligne[8] : (float) $brut - $recuperation;

            $decomptes[] = [
                'numero' => $numero !== null ? (string) $numero : 'ACOMPTE',
                'date' => $date->toDateString(),
                'nature' => $this->texte($ligne[4] ?? null) ?? 'Travaux',
                'brut' => round((float) $brut, 2),
                'recuperation_avance' => round($recuperation, 2),
                'net_paye' => round($net, 2),
                'regle' => str_starts_with(mb_strtolower($this->texte($ligne[9] ?? null) ?? ''), 'pay'),
            ];
        }

        return $decomptes;
    }

    // ─────────────────────────────────────────────────────────── utilitaires

    /**
     * Contenu d'une feuille sous forme de lignes indexées à partir de zéro.
     *
     * Le classeur est presque entièrement calculé : lire la cellule rendrait la
     * formule et non son résultat. On retient donc la valeur mise en cache par
     * le tableur, plutôt que de recalculer des formules dont certaines
     * référencent des feuilles que nous ne chargeons pas.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function grille(Worksheet $ws): array
    {
        $grille = [];

        foreach ($ws->getRowIterator() as $ligne) {
            $valeurs = [];
            $cellules = $ligne->getCellIterator();
            $cellules->setIterateOnlyExistingCells(true);

            foreach ($cellules as $cellule) {
                $valeur = $cellule->getValue();
                if (is_string($valeur) && str_starts_with($valeur, '=')) {
                    $valeur = $cellule->getOldCalculatedValue();
                }
                $valeurs[Coordinate::columnIndexFromString($cellule->getColumn()) - 1] = $valeur;
            }

            $grille[$ligne->getRowIndex() - 1] = $valeurs;
        }

        return $grille;
    }

    protected function cle(?string $texte): string
    {
        $texte = str_replace(['Œ', 'œ'], ['OE', 'oe'], (string) $texte);
        $texte = preg_replace('/[^A-Z]/', '', mb_strtoupper($this->sansAccent($texte)));

        return str_replace(
            ['HERBERGEMENT', 'ENSEINGNANTS', 'REPLEMENT'],
            ['HEBERGEMENT', 'ENSEIGNANTS', 'REPLIEMENT'],
            (string) $texte,
        );
    }

    protected function sansAccent(string $texte): string
    {
        return strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'À' => 'A', 'Â' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Î' => 'I',
            'Ô' => 'O', 'Û' => 'U', 'Ç' => 'C',
        ]);
    }

    protected function nomPropre(string $nom): string
    {
        $nom = preg_replace('/\s+/u', ' ', trim($nom));
        $nom = preg_replace('/\bENVIRONNEMENTAMENAGEMENT\b/u', 'ENVIRONNEMENT AMENAGEMENT', $nom);

        return mb_convert_case($nom, MB_CASE_TITLE, 'UTF-8');
    }

    protected function date(mixed $valeur): ?Carbon
    {
        if ($valeur instanceof \DateTimeInterface) {
            return Carbon::instance($valeur);
        }
        // Une date lue en mode « données seules » arrive en numéro de série Excel.
        if (is_numeric($valeur) && $valeur > 30000 && $valeur < 80000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $valeur));
        }

        return null;
    }

    protected function jour(mixed $valeur): ?string
    {
        return $this->date($valeur)?->toDateString();
    }

    /** « Nov. 2025 » : le classeur ne donne que le mois, on retient le 1er. */
    protected function moisTexte(mixed $valeur): ?string
    {
        if (($date = $this->date($valeur)) !== null) {
            return $date->toDateString();
        }
        if (! is_string($valeur) || ! preg_match('/([A-Za-zéûôàè]+)\.*\s*(\d{4})/u', $this->sansAccent($valeur), $m)) {
            return null;
        }

        $mois = self::MOIS[mb_strtolower(mb_substr($m[1], 0, 3))] ?? null;

        return $mois ? sprintf('%04d-%02d-01', (int) $m[2], $mois) : null;
    }

    protected function entier(mixed $valeur): ?int
    {
        if (is_numeric($valeur)) {
            return (int) round((float) $valeur);
        }
        if (is_string($valeur) && preg_match('/\d+/', $valeur, $m)) {
            return (int) $m[0];
        }

        return null;
    }

    protected function texte(mixed $valeur): ?string
    {
        if (! is_string($valeur)) {
            return null;
        }
        $texte = preg_replace('/\s+/u', ' ', trim($valeur));

        return $texte === '' ? null : $texte;
    }
}
