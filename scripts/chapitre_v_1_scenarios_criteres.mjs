/**
 * Chapitre V — V.1.3 Scénarios de validation et V.1.4 Critères d'évaluation.
 *
 * Les seuils de performance ne sont plus proposés au jugé : ils s'appuient sur
 * les temps de traitement serveur effectivement mesurés sur le jeu de données
 * d'Odienné (tests/Feature/PerformanceMesureTest.php), et sur les seuils
 * classiques de la littérature relative aux temps de réponse.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (t, o = {}) => new TextRun({ text: t, font: POLICE, size: 24, ...o });

const P = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 120, line: 276 },
});

const Titre = (t, n) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: n === 2 ? 26 : 24, bold: true })],
    heading: n === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3,
    spacing: { before: 260, after: 150 },
});

const Legende = (t, avant = false) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: avant ? { before: 200, after: 100 } : { before: 80, after: 200 },
});

const B = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDS = { top: B, bottom: B, left: B, right: B };

const Cel = (t, { entete = false, largeur, centre = false, jaune = false } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDS,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 60, bottom: 60, left: 80, right: 80 },
    children: [new Paragraph({
        children: [new TextRun({
            text: t, font: POLICE, size: 20, bold: entete,
            highlight: jaune ? 'yellow' : undefined,
        })],
        alignment: (entete || centre) ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 240 },
    })],
});

const Tableau = (entetes, lignes, largeurs, centrees = []) => new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({
            tableHeader: true,
            children: entetes.map((e, i) => Cel(e, { entete: true, largeur: largeurs[i] })),
        }),
        ...lignes.map((l) => new TableRow({
            children: l.map((c, i) => Cel(c, {
                largeur: largeurs[i],
                centre: centrees.includes(i),
                jaune: c === '…' || c.startsWith('[ '),
            })),
        })),
    ],
});

// ═══════════════════════════════════════════ V.1.3 — scénarios

const SCENARIOS = [
    ['1', 'Création du projet et de son marché',
        'Enregistrement de la fiche projet, de l’université de rattachement, de la localisation et du marché de travaux avec son montant initial et son délai',
        'Le projet apparaît au portefeuille et sur la carte ; le montant contractuel de référence est correctement constitué'],
    ['2', 'Décomposition en ouvrages pondérés',
        'Saisie des ouvrages, de leur calendrier contractuel et de leur poids relatif, puis tentative d’attribution d’un poids portant le total au-delà de cent pour cent',
        'La saisie excédentaire est refusée par le serveur avec indication du solde disponible ; tant que le total reste inférieur à cent pour cent, un avertissement signale que l’avancement pondéré n’est pas encore représentatif'],
    ['3', 'Saisie d’un relevé d’avancement par l’agent chargé du suivi',
        'Connexion sous un profil habilité à la saisie physique, puis report, pour une période mensuelle, du taux planifié et du taux réalisé de chaque ouvrage tels qu’ils figurent au rapport de l’entreprise exécutante',
        'Le relevé est enregistré, attribué nominativement à son auteur, inscrit au journal d’activité, et alimente immédiatement les indicateurs du projet'],
    ['4', 'Rejet des saisies non conformes',
        'Tentatives d’enregistrement d’un taux hors de l’intervalle de zéro à cent, d’un second relevé pour une période et un ouvrage déjà renseignés, et d’un relevé rattaché à un ouvrage étranger au projet',
        'Les trois tentatives sont refusées avec un message explicite ; aucune donnée irrégulière n’est enregistrée'],
    ['5', 'Traçabilité des écritures',
        'Consultation du journal d’activité depuis la section d’administration, après une série de créations, de modifications et de suppressions opérées sous des profils différents',
        'Chaque opération y figure avec son auteur, son horodatage et l’objet concerné ; le journal n’est accessible qu’au profil administrateur'],
    ['6', 'Calcul des indicateurs de performance',
        'Consultation de la fiche projet après enregistrement de la série mensuelle des relevés physiques et financiers',
        'Les indicateurs restitués — avancement pondéré, valeur planifiée, valeur acquise, coût réel, indices de performance des délais et des coûts, valeur acquise temporelle et fin projetée — coïncident avec les valeurs calculées manuellement'],
    ['7', 'Détection d’un retard au démarrage',
        'Saisie du calendrier contractuel d’un ouvrage dont la date de début prévue est dépassée sans démarrage effectif, puis enregistrement ultérieur de la date de début réelle',
        'Le retard est affiché dans la fiche de l’ouvrage et l’alerte se déclenche au-delà de la tolérance paramétrée ; l’enregistrement du démarrage effectif clôt l’alerte'],
    ['8', 'Déclenchement des alertes',
        'Provocation délibérée des conditions de chacune des onze règles de détection, puis rétablissement de la situation',
        'L’alerte attendue apparaît, n’est pas dupliquée, voit sa gravité ajustée selon l’ampleur de l’écart, et se clôt automatiquement au rétablissement'],
    ['9', 'Contrôle des droits d’accès',
        'Tentatives d’accès et d’écriture non autorisées sous chacun des profils, y compris par saisie directe de l’adresse de la fonction, puis tentative de connexion au moyen d’un compte désactivé',
        'Toutes les tentatives sont refusées côté serveur et non par simple masquage de l’interface ; le compte désactivé est écarté dès l’authentification'],
    ['10', 'Génération et export d’un rapport',
        'Production de la fiche d’avancement du projet au format de document portable, en faisant varier les sections retenues au moment du téléchargement',
        'Les valeurs du rapport coïncident avec celles du tableau de bord ; seules les sections retenues figurent au document produit'],
    ['11', 'Consolidation du portefeuille',
        'Consultation du tableau de bord après enregistrement de l’ensemble des projets',
        'Les agrégats et la répartition par étape du cycle correspondent à la somme des situations individuelles'],
    ['12', 'Alimentation par import du classeur mensuel',
        'Import de la base de calcul d’avancement de la mission de contrôle, contrôle du contenu reconnu avant intégration, puis répétition de l’import à l’identique',
        'Le contenu reconnu est présenté avant toute écriture ; les relevés, décomptes et calendriers sont intégrés et les indicateurs actualisés ; le second import n’ajoute aucun enregistrement'],
];

// ═══════════════════════════════════════════ V.1.4 — critères

const CRITERES = [
    ['Fiabilité', 'Écart entre l’indicateur restitué et sa valeur calculée manuellement',
        'Calcul de référence établi sur tableur, indépendamment et préalablement à la consultation de l’application',
        'Écart nul, hors différence d’arrondi à la troisième décimale'],
    ['Intégrité', 'Nombre de saisies non conformes acceptées',
        'Scénario 4 du protocole', 'Zéro'],
    ['Sécurité', 'Nombre d’actions non autorisées abouties',
        'Scénario 9 du protocole', 'Zéro'],
    ['Traçabilité', 'Part des écritures rattachables à un auteur identifié et à un horodatage',
        'Scénario 5 du protocole', 'Totalité des écritures'],
    ['Détection', 'Part des règles d’alerte correctement déclenchées et correctement closes',
        'Scénarios 7 et 8 du protocole', 'Totalité des onze règles, à l’ouverture comme à la fermeture'],
    ['Reproductibilité', 'Écart entre deux imports successifs du même classeur',
        'Scénario 12 : import du classeur mensuel répété à l’identique, puis comparaison des enregistrements',
        'Aucun enregistrement ajouté au second import'],
    ['Performance', 'Délai d’affichage du tableau de bord consolidé et d’une fiche projet',
        'Chronométrage répété en conditions réelles de connexion, du clic à l’affichage complet',
        'Trois secondes en connexion mobile courante ; cinq secondes en connexion dégradée'],
    ['Performance', 'Délai de production d’un rapport exporté',
        'Chronométrage, de la demande à la mise à disposition du document',
        'Dix secondes'],
    ['Apport opérationnel', 'Durée de saisie d’un relevé mensuel complet',
        'Chronométrage auprès d’un agent, après prise en main',
        'Inférieure à la durée du circuit antérieur'],
    ['Couverture', 'Part des besoins recensés en II.3 effectivement couverts',
        'Confrontation ligne à ligne des tableaux de besoins et des fonctions livrées',
        'Totalité des besoins jugés essentiels et au moins quatre cinquièmes de l’ensemble des besoins recensés'],
    ['Utilisabilité', 'Appréciation des agents après prise en main',
        'Questionnaire structuré et entretiens (annexe)',
        'Appréciation majoritairement favorable, et aucune fonction jugée inutilisable'],
];

const MESURES = [
    ['Tableau de bord consolidé', '65 ms', '62 ms', '67 ms'],
    ['Fiche du projet d’Odienné', '1 394 ms', '1 316 ms', '1 613 ms'],
    ['Rapport de projet exporté', '2 677 ms', '2 625 ms', '2 852 ms'],
    ['Rapport global du portefeuille exporté', '935 ms', '808 ms', '1 031 ms'],
];

// ═══════════════════════════════════════════ document

const doc = new Document({
    styles: { default: { document: { run: { font: POLICE, size: 24 } } } },
    sections: [{
        properties: { page: { margin: { top: 1134, bottom: 1134, left: 1417, right: 1134 } } },
        children: [
            Titre('V.1.3. Scénarios de validation', 3),

            P('Chaque scénario reproduit une situation réelle d’utilisation et se conclut par un résultat attendu, défini avant l’essai. Les scénarios couvrent l’ensemble de la chaîne, de la saisie à la restitution, ainsi que les contrôles qui doivent empêcher une opération irrégulière.'),

            P('Ils se répartissent en trois familles. Les scénarios 1 à 3 et 12 portent sur l’alimentation de la base, par saisie directe comme par import du classeur mensuel. Les scénarios 4, 5 et 9 portent sur les contrôles : ce que l’application doit refuser, et ce dont elle doit garder trace. Les scénarios 6 à 8, 10 et 11 portent sur la restitution, c’est-à-dire sur ce que l’outil produit à partir de ce qu’il a reçu. Cette répartition n’est pas d’agrément : un dispositif d’essais qui ne vérifierait que la restitution laisserait sans preuve la moitié des garanties attendues d’un système de suivi.'),

            Legende('Tableau V.3 — Scénarios de validation', true),
            Tableau(['N°', 'Scénario', 'Mode opératoire', 'Résultat attendu'],
                SCENARIOS, [5, 20, 38, 37], [0]),
            Legende('Source : élaboration personnelle'),

            P('Le douzième scénario a été ajouté au protocole initial. L’import de la base de calcul mensuelle de la mission de contrôle constitue en effet le mode d’alimentation ordinaire de l’application, et non un utilitaire accessoire : c’est par lui que transitent les relevés d’avancement de trente ouvrages sur vingt périodes. Sa vérification porte sur deux exigences distinctes — que le contenu reconnu soit présenté avant toute écriture, afin qu’aucune intégration ne se fasse à l’aveugle, et qu’un import répété n’ajoute rien, faute de quoi une manœuvre banale suffirait à dédoubler l’historique.'),

            Titre('V.1.4. Critères d’évaluation de la performance et de la fiabilité de l’outil', 3),

            P('Un essai n’a de valeur que si le seuil au-delà duquel il est réputé concluant a été fixé par avance. Les critères retenus relèvent de six dimensions : la fiabilité des traitements, l’intégrité et la sécurité des données, la traçabilité des écritures, la performance technique, la reproductibilité de l’alimentation et l’apport opérationnel.'),

            Legende('Tableau V.4 — Critères d’évaluation et seuils d’acceptation', true),
            Tableau(['Dimension', 'Indicateur', 'Méthode de mesure', 'Seuil d’acceptation'],
                CRITERES, [15, 27, 30, 28]),
            Legende('Source : élaboration personnelle'),

            P('Trois de ces seuils appellent une justification, faute de quoi ils resteraient arbitraires.'),

            P('Les seuils de performance s’appuient sur les limites classiques de la littérature relative aux temps de réponse, établies par Miller en 1968 et reprises depuis lors : en deçà d’une seconde, l’enchaînement de la pensée de l’utilisateur n’est pas interrompu ; au-delà de dix secondes, son attention se détourne de la tâche en cours. Une consultation d’écran, qui s’insère dans un travail continu, doit donc viser la première limite ; un export, qui constitue une action délibérée dont l’utilisateur attend le résultat, peut s’accommoder de la seconde. C’est de là que procèdent les seuils retenus : trois secondes pour un affichage en connexion mobile courante — la seconde de traitement, augmentée du délai de réseau qu’il serait irréaliste d’ignorer en Côte d’Ivoire —, cinq secondes en connexion dégradée, et dix secondes pour un document exporté.'),

            P('Ces seuils ont été confrontés aux temps de traitement effectivement mesurés sur le jeu de données d’Odienné, réseau exclu, afin de vérifier qu’ils laissent au réseau une marge réaliste. La mesure porte sur cinq exécutions successives de chaque restitution, dont la médiane est retenue. Le tableau de bord consolidé s’assemble en 65 millisecondes ; la fiche du projet d’Odienné, la plus lourde du portefeuille avec ses six cents relevés physiques et ses six cents relevés financiers, en 1 394 ; le rapport de projet exporté, qui comprend le tracé des courbes, en 2 677 ; le rapport global du portefeuille en 935. La part serveur consomme donc au plus la moitié du budget de trois secondes pour l’écran le plus lourd, et le quart du budget de dix secondes pour l’export le plus coûteux. Les seuils sont exigeants sans être hors d’atteinte, ce qui est la condition pour qu’un critère ait un sens.'),

            P('Le seuil de couverture distingue deux exigences de nature différente. La totalité des besoins jugés essentiels au chapitre II doit être couverte, sans quoi l’outil manquerait son objet ; l’ensemble des besoins recensés, en revanche, comprend des demandes de confort dont l’absence n’empêche pas l’usage, et exiger leur satisfaction intégrale reviendrait à confondre le nécessaire et le souhaitable. La proportion de quatre cinquièmes retenue pour l’ensemble constitue un seuil conventionnel, énoncé avant l’essai précisément pour qu’il ne puisse être ajusté après coup à la mesure du résultat obtenu.'),

            P('Le seuil d’utilisabilité, enfin, comporte deux volets dont le second importe davantage que le premier. Une appréciation majoritairement favorable est un résultat faible : elle se satisfait d’une majorité et laisse dans l’ombre les minorités empêchées. L’exigence qu’aucune fonction ne soit jugée inutilisable est plus contraignante et plus utile, car une seule fonction impraticable dans une chaîne de saisie suffit à rompre cette chaîne et à renvoyer l’agent à son tableur.'),

            Legende('Tableau V.4 bis — Temps de traitement serveur mesurés sur le jeu de données d’Odienné', true),
            Tableau(['Restitution', 'Médiane', 'Minimum', 'Maximum'],
                MESURES, [46, 18, 18, 18], [1, 2, 3]),
            Legende('Source : mesures conduites sur cinq exécutions successives, traitement serveur seul, réseau et rendu du navigateur exclus'),

            P('Ces mesures ne se substituent pas au chronométrage en conditions réelles prévu au protocole, qui seul rend compte de ce que perçoit l’utilisateur. Elles en établissent la part incompressible et permettent d’imputer un dépassement éventuel : au-delà des seuils retenus, ce serait au réseau ou au poste de travail qu’il faudrait l’attribuer, non au traitement.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
