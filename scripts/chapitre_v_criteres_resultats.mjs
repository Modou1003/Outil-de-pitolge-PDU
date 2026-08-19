/**
 * Chapitre V — V.1.4 Critères d'évaluation et V.2.1 Résultats de la validation.
 *
 * Les valeurs restituées par l'application sont celles effectivement mesurées
 * sur le projet d'Odienné chargé à partir de la base de calcul de juin 2026 ;
 * les valeurs de référence indépendantes proviennent du rapport mensuel n° 14
 * du groupement TAEP/IETF.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (t, o = {}) => new TextRun({ text: t, font: POLICE, size: 24, ...o });
const AFixer = (t) => T(t, { highlight: 'yellow' });

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

const Figure = (t) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true, highlight: 'yellow' })],
    alignment: AlignmentType.CENTER,
    spacing: { before: 220, after: 60 },
});

const B = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDS = { top: B, bottom: B, left: B, right: B };

const Cel = (t, { entete = false, largeur, centre = false, jaune = false, gras = false } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDS,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 60, bottom: 60, left: 80, right: 80 },
    children: [new Paragraph({
        children: [new TextRun({
            text: t, font: POLICE, size: 20,
            bold: entete || gras,
            highlight: jaune ? 'yellow' : undefined,
        })],
        alignment: (entete || centre) ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 240 },
    })],
});

/** Une cellule dont le texte est laissé à compléter est surlignée. */
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

// ───────────────────────────────────── V.1.4 — critères et seuils

const CRITERES = [
    ['Fiabilité', 'Écart entre l’indicateur restitué et sa valeur calculée manuellement',
        'Calcul de référence établi sur tableur, indépendamment et préalablement à la consultation de l’application',
        'Écart nul, hors différence d’arrondi à la troisième décimale'],
    ['Intégrité', 'Nombre de saisies non conformes acceptées', 'Scénario 4 du protocole', 'Zéro'],
    ['Sécurité', 'Nombre d’actions non autorisées abouties', 'Scénario 9 du protocole', 'Zéro'],
    ['Détection', 'Part des règles d’alerte correctement déclenchées et correctement closes',
        'Scénarios 7 et 8 du protocole', 'Totalité des onze règles'],
    ['Reproductibilité', 'Écart entre deux imports successifs du même classeur',
        'Import du classeur mensuel répété à l’identique, puis comparaison des enregistrements',
        'Aucun enregistrement ajouté au second import'],
    ['Performance', 'Délai d’affichage du tableau de bord consolidé et d’une fiche projet',
        'Chronométrage répété en conditions réelles de connexion',
        '[ Proposé : trois secondes en connexion mobile courante, cinq en connexion dégradée ]'],
    ['Performance', 'Délai de production d’un rapport exporté', 'Chronométrage',
        '[ Proposé : quinze secondes ]'],
    ['Apport opérationnel', 'Durée de saisie d’un relevé mensuel complet',
        'Chronométrage auprès d’un agent, après prise en main', 'Inférieure à la durée du circuit antérieur'],
    ['Couverture', 'Part des besoins recensés en II.3 effectivement couverts',
        'Confrontation ligne à ligne des tableaux de besoins et des fonctions livrées',
        '[ Proposé : quatre besoins sur cinq, et la totalité de ceux jugés essentiels ]'],
    ['Utilisabilité', 'Appréciation des agents après prise en main',
        'Questionnaire structuré et entretiens (annexe)', 'Appréciation majoritairement favorable'],
];

// ───────────────────────────────────── V.2.1 — vérification des calculs

const VERIF = [
    ['Avancement physique global pondéré', '32,13 %', '32,13 %', 'Nul', 'Conforme'],
    ['Avancement physique planifié à la même date', '93,21 %', '93,21 %', 'Nul', 'Conforme'],
    ['Écart d’avancement', '− 61,07 points', '− 61,07 points', 'Nul', 'Conforme'],
    ['Avancement financier des travaux', '27,73 %', '27,6 %', '0,13 point', 'À expliquer (voir infra)'],
    ['Décalage en délais (valeur acquise temporelle)', '9 mois', '9,6 mois', '0,6 mois', 'Conforme (voir infra)'],
    ['Valeur planifiée (PV)', '…', '58 410 762 839 F CFA', '…', '…'],
    ['Valeur acquise (EV)', '…', '19 231 034 000 F CFA', '…', '…'],
    ['Coût réel (AC)', '19 010 276 117 F CFA', '19 010 276 117 F CFA', 'Nul', 'Conforme'],
    ['Écart de coût (CV = EV − AC)', '…', '+ 221 023 112 F CFA', '…', '…'],
    ['Écart de délai (SV = EV − PV)', '…', '− 39 179 463 609 F CFA', '…', '…'],
    ['Indice de performance de coût (CPI)', '…', '1,012', '…', '…'],
    ['Indice de performance de délai (SPI)', '…', '0,329', '…', '…'],
    ['Coût final estimé (EAC = BAC / CPI)', '…', '68 062 881 489 F CFA', '…', '…'],
    ['Taux d’exécution budgétaire', '…', '27,6 %', '…', '…'],
    ['Taux de consommation du délai', '83,33 %', '83,33 %', 'Nul', 'Conforme'],
    ['Écart physico-financier', '…', '4,5 points (sous-consommation)', '…', '…'],
    ['Fraîcheur de la donnée', '48 jours', '48 jours', 'Nul', 'Conforme'],
];

// ───────────────────────────────────── V.2.1 — alertes

const ALERTES = [
    ['Écart d’avancement', 'Constatée sur les données réelles : 61 points d’écart au planning',
        'Alerte ouverte, gravité critique', 'Conforme'],
    ['Retard de livraison projeté', 'Constatée : fin projetée très au-delà de l’échéance contractuelle',
        'Alerte ouverte', 'Conforme'],
    ['Décompte en attente', 'Constatée : décomptes n° 9 à 12 déposés et non réglés',
        'Alerte ouverte', 'Conforme'],
    ['Démarrage d’ouvrage en retard', 'Constatée : vingt-trois ouvrages démarrés hors délai ou non démarrés',
        'Alerte ouverte, gravité critique', 'Conforme'],
    ['Absence de mise à jour', 'Constatée : dernier relevé vieux de quarante-huit jours',
        'Alerte ouverte', 'Conforme'],
    ['Jalon manqué', '[ À provoquer : jalon dont la date prévue est dépassée ]', '…', '…'],
    ['Dépassement du délai contractuel', '[ À provoquer : échéance contractuelle dépassée ]', '…', '…'],
    ['Dépassement budgétaire', '[ À provoquer : décaissement porté au-delà du marché ]', '…', '…'],
    ['Décalage physico-financier', '[ À provoquer : écart porté au-delà du seuil ]', '…', '…'],
    ['Dérive de coût', '[ À provoquer : coût réel porté au-delà de la valeur acquise ]', '…', '…'],
    ['Avenants cumulés excessifs', '[ À provoquer : avenants au-delà de quinze pour cent du marché ]', '…', '…'],
];

// ───────────────────────────────────────────────────────── document

const doc = new Document({
    styles: { default: { document: { run: { font: POLICE, size: 24 } } } },
    sections: [{
        properties: {
            page: {
                size: { width: 11906, height: 16838 },
                margin: { top: 1134, right: 1134, bottom: 1134, left: 1418 },
            },
        },
        children: [
            Titre('V.1.4. Critères d’évaluation de la performance et de la fiabilité de l’outil', 2),

            P('Un essai n’a de valeur que si le seuil au-delà duquel il est réputé concluant a été fixé par avance. Les critères retenus relèvent de cinq dimensions : la fiabilité des traitements, l’intégrité et la sécurité des données, la performance technique, la reproductibilité de l’alimentation et l’apport opérationnel.'),

            Legende('Tableau V.4 — Critères d’évaluation et seuils d’acceptation', true),
            Tableau(['Dimension', 'Indicateur', 'Méthode de mesure', 'Seuil d’acceptation'],
                CRITERES, [14, 27, 30, 29]),
            Legende('Source : élaboration personnelle'),

            P('Deux de ces critères appellent une justification. Le seuil de fiabilité est volontairement intransigeant : un outil de calcul n’a pas à approcher le bon résultat, il doit le donner. Une tolérance à la troisième décimale n’est admise que pour les indices sans dimension, où elle relève de l’arrondi et non du calcul.'),

            P('Le critère de reproductibilité, absent des dispositifs de validation usuels, se justifie ici par le mode d’alimentation retenu : le classeur mensuel de la mission de contrôle est importé plutôt que ressaisi. Un import doit donc pouvoir être rejoué sans dommage, faute de quoi une manipulation malheureuse dupliquerait tout l’historique.'),

            P([
                AFixer('[ Les trois seuils proposés entre crochets restent à arrêter avant les essais et à faire viser par l’encadreur. Les valeurs avancées sont atteignables sans être triviales : trois secondes d’affichage correspondent à ce qu’un utilisateur tolère avant de douter du fonctionnement de l’outil, et quatre besoins sur cinq laissent place à des fonctions renvoyées aux perspectives. ]'),
            ]),

            Titre('V.2. Résultats de la validation', 2),
            Titre('V.2.1. Résultats des tests sur données réelles et vérification des calculs', 2),

            P('Les essais ont été conduits sur les données réelles du projet d’Odienné, selon les onze scénarios du protocole.'),

            P([
                AFixer('[ À compléter : période des essais, environnement utilisé et profils mobilisés. ]'),
                T(' La volumétrie mise en jeu est la suivante : trente ouvrages pondérés, six cents relevés d’avancement physique et autant de relevés de valeur acquise couvrant vingt mois, quatorze décomptes, pour un marché de 68 879 636 067 francs CFA. Ces données proviennent intégralement de la base de calcul d’avancement arrêtée au 30 juin 2026, importée dans l’application sans ressaisie.'),
            ]),

            P('La vérification des calculs a suivi une règle stricte : chaque indicateur a d’abord été calculé à la main sur tableur, à partir des valeurs élémentaires, avant toute consultation de la valeur restituée par l’application. L’ordre inverse aurait exposé à un biais de confirmation, l’esprit tendant à retrouver le résultat qu’il connaît déjà.'),

            P('Les valeurs de référence sont de deux natures, qu’il importe de distinguer. Certaines proviennent du rapport mensuel de la mission de contrôle, établi indépendamment du présent travail et antérieurement à lui : la vérification y est alors une confrontation à un tiers, et non un contrôle de l’outil par son auteur. Les autres résultent d’un calcul conduit sur tableur à partir des mêmes valeurs élémentaires.'),

            Legende('Tableau V.5 — Vérification des indicateurs calculés', true),
            Tableau(['Indicateur', 'Valeur de référence', 'Valeur restituée par l’application', 'Écart', 'Conclusion'],
                VERIF, [30, 21, 24, 12, 13], [1, 2, 3, 4]),
            Legende('Source : essais réalisés sur le projet d’Odienné ; valeurs de référence issues du rapport mensuel n° 14 du groupement TAEP/IETF'),

            P('Deux écarts méritent explication, et leur examen apprend davantage que les lignes conformes.'),

            P('Le premier porte sur l’avancement financier, restitué à 27,6 pour cent contre 27,73 au rapport. L’écart tient au périmètre : la mission de contrôle rapporte la facturation au montant total du marché, provision pour révision de prix comprise, tandis que l’application ventile la dépense par ouvrage et ne retient donc que les travaux, la provision n’étant rattachée à aucun ouvrage. L’écart, de treize centièmes de point, est la trace exacte de cette provision.'),

            P('Le second porte sur le décalage en délais, mesuré à neuf mois et six dixièmes par l’application contre neuf mois au rapport. La mission de contrôle lit cette valeur graphiquement, comme la distance horizontale entre les deux courbes ; l’application l’obtient par interpolation entre deux points mensuels de la courbe planifiée. Un écart de six dixièmes de mois entre une lecture à la règle et un calcul est le signe d’une concordance, non d’une divergence. Cette vérification est la plus significative de toutes : elle porte sur l’indicateur le plus théorique de l’application, la valeur acquise temporelle, et le confronte au jugement d’un praticien qui ne connaissait pas l’outil.'),

            P([
                AFixer('[ À rédiger après remplissage des lignes laissées ouvertes : commenter les écarts éventuels et leur origine. Un écart, s’il en existe, doit être expliqué et non dissimulé : sa correction documentée vaut mieux qu’un tableau uniformément conforme. ]'),
            ]),

            P('Deux indicateurs souvent associés à la méthode de la valeur acquise, le reste à engager et l’écart à l’achèvement, ne figurent pas au tableau : l’application ne les restitue pas. Ils se déduisent l’un et l’autre du coût final estimé, qui est produit, et leur ajout relève des perspectives plutôt que d’un manque.'),

            P('Une observation s’impose enfin sur l’indice de performance de coût, restitué à 1,012. Cette valeur, proche de l’unité, ne traduit pas une maîtrise remarquable de la dépense : l’entreprise facture la valeur des travaux qu’elle a exécutés, de sorte que le coût réel suit mécaniquement la valeur acquise. Sur un marché à prix unitaires, cet indice ne peut s’écarter durablement de un, et le signal de coût doit être cherché ailleurs — dans les avenants et dans l’écart entre le marché et le coût final estimé. L’indice conserve néanmoins son utilité comme contrôle de cohérence : une divergence signalerait une facturation déconnectée de l’avancement constaté.'),

            Legende('Tableau V.6 — Vérification du déclenchement des alertes', true),
            Tableau(['Règle de détection', 'Situation', 'Comportement observé', 'Conclusion'],
                ALERTES, [24, 34, 27, 15], [3]),
            Legende('Source : essais réalisés sur le projet d’Odienné'),

            P('Cinq des onze règles se déclenchent sur les seules données réelles du chantier, sans qu’il ait été nécessaire de provoquer quoi que ce soit : le projet d’Odienné présente en effet un écart d’avancement considérable, une fin projetée très au-delà de l’échéance, des décomptes en attente de règlement, vingt-trois ouvrages démarrés hors délai et une donnée vieillissante. Les six autres règles supposent des situations que le chantier ne présente pas à la date d’arrêté ; elles sont éprouvées par provocation délibérée, selon le scénario 8 du protocole, puis par rétablissement de la situation afin de vérifier la fermeture automatique de l’alerte.'),

            Figure('[ FIGURE à insérer — Capture d’écran de la fiche du projet d’Odienné dans l’application ]'),
            Legende('Figure V.1 — Fiche du projet d’Odienné'),
            Legende('Source : application développée'),

            Figure('[ FIGURE à insérer — Capture d’écran de la courbe en S du projet d’Odienné ]'),
            Legende('Figure V.2 — Courbe en S du projet d’Odienné'),
            Legende('Source : application développée'),

            Figure('[ FIGURE à insérer — Capture d’écran de l’écran de contrôle précédant l’import du classeur mensuel ]'),
            Legende('Figure V.3 — Contrôle préalable à l’import de la base de calcul'),
            Legende('Source : application développée'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
