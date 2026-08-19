/**
 * III.2.2 — point 5.3 : les seuils, nature et fondement.
 *
 * Version sourcée. Les références portées sans réserve sont celles dont le
 * contenu est établi ; celles qui doivent être vérifiées sur le document
 * original sont signalées comme telles.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (t, o = {}) => new TextRun({ text: t, font: POLICE, size: 24, ...o });
const AVerifier = (t) => T(t, { highlight: 'yellow' });

const P = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 110, line: 264 },
});

const Titre = (t, n = 3) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: n === 2 ? 26 : 24, bold: true })],
    heading: n === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3,
    spacing: { before: n === 2 ? 280 : 210, after: 130 },
});

const Puce = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    bullet: { level: 0 },
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 90, line: 264 },
});

const Ref = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    alignment: AlignmentType.JUSTIFIED,
    indent: { left: 567, hanging: 567 },
    spacing: { after: 100, line: 264 },
});

const Legende = (t, avant = false) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: avant ? { before: 170, after: 90 } : { before: 70, after: 170 },
});

const B = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDS = { top: B, bottom: B, left: B, right: B };

const Cel = (t, { entete = false, largeur, centre = false, jaune = false } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDS,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 50, bottom: 50, left: 60, right: 60 },
    children: [new Paragraph({
        children: [new TextRun({ text: t, font: POLICE, size: 20, bold: entete, highlight: jaune ? 'yellow' : undefined })],
        alignment: (entete || centre) ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 230 },
    })],
});

const Tableau = (entetes, lignes, largeurs, centrees = []) => new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({ tableHeader: true, children: entetes.map((e, i) => Cel(e, { entete: true, largeur: largeurs[i] })) }),
        ...lignes.map((l) => new TableRow({
            children: l.map((c, i) => Cel(c, {
                largeur: largeurs[i],
                centre: centrees.includes(i),
                jaune: c.startsWith('[') || c.includes('à vérifier'),
            })),
        })),
    ],
});

const CATEGORIES = [
    ['Seuil réglementaire',
        'Fixé par un texte en vigueur, qui s’impose et n’est pas négociable',
        'Le paramétrage reproduit la valeur du texte, et la source est citée dans la table de paramètres'],
    ['Seuil de référence professionnelle',
        'Issu d’un référentiel documenté mais non contraignant, ou d’une pratique établie',
        'Il fournit un ordre de grandeur défendable, à condition que sa source soit indiquée'],
    ['Convention interne',
        'Définie par l’unité de gestion au vu de son expérience',
        'Elle doit être assumée comme telle et revue périodiquement au vu du taux de déclenchement observé'],
];

const SEUILS = [
    ['Donnée de terrain à rafraîchir', '30 jours', 'Obligation interne',
        'Périodicité mensuelle des rapports d’activité fixée par le manuel de procédures du programme'],
    ['Avenants cumulés excessifs', '15 %', 'À vérifier',
        '[ Vérifier si le Code des marchés publics plafonne le cumul des avenants ; à défaut, requalifier en convention interne ]'],
    ['Décompte en attente de règlement', '30 jours', 'À vérifier',
        '[ Vérifier l’existence d’un délai réglementaire de paiement des décomptes dans le marché ou le Code ]'],
    ['Dérive de coût (indice de performance des coûts)', '0,95', 'Référence professionnelle',
        'Écart de cinq pour cent usuellement retenu comme seuil d’investigation dans la pratique de la valeur acquise'],
    ['Donnée de terrain périmée', '60 jours', 'Convention interne', 'Deux échéances mensuelles manquées'],
    ['Écart d’avancement critique', '15 points', 'Convention interne', '—'],
    ['Décalage physico-financier', '20 points, critique à 35', 'Convention interne', '—'],
    ['Écart physique-budget aligné', '10 points', 'Convention interne', '—'],
    ['Retard de livraison projeté', '60 jours, critique à 120', 'Convention interne', '—'],
    ['Fin projetée — vigilance', '15 jours', 'Convention interne', '—'],
    ['Démarrage d’ouvrage en retard', '15 jours', 'Convention interne', '—'],
    ['Dépassement budgétaire imminent', '90 %', 'Convention interne', '—'],
    ['Fraîcheur — fraîche puis à rafraîchir', '30 puis 60 jours', 'Convention interne', 'Alignées sur les seuils de donnée de terrain'],
];

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
            Titre('5.3. Les seuils : nature et fondement', 2),

            Titre('a) Ce que les référentiels prescrivent, et ce qu’ils ne prescrivent pas', 3),

            P('Un point de méthode doit être énoncé avant toute discussion des valeurs retenues, car il commande la manière dont l’outil a été conçu : aucun référentiel de management de projet ne fixe de valeur numérique de déclenchement d’alerte.'),

            P('Le Guide du corpus des connaissances en management de projet du Project Management Institute introduit la notion de seuil de contrôle, défini comme l’écart admis par rapport au référentiel avant qu’une action ne soit engagée. Le guide prescrit que ces seuils soient arrêtés et consignés dans le plan de management du projet, et qu’ils soient exprimés en écart au référentiel de coût ou de délai. Il ne donne aucune valeur, et n’en donne pour aucun secteur d’activité.'),

            P('La norme du même institut consacrée à la méthode de la valeur acquise procède de la même façon. Elle décrit l’analyse des écarts et l’investigation qui doit suivre le franchissement d’un seuil, mais laisse à l’organisation le soin de déterminer à partir de quel écart l’investigation s’impose.'),

            P([
                T('Les normes internationales relatives au management de projet convergent vers cette position : elles sont rédigées sous forme de lignes directrices et non de prescriptions chiffrées. '),
                AVerifier('[ Si les normes ISO 21502 et ISO 10006 sont citées, vérifier la formulation exacte du passage relatif à la maîtrise du projet sur le texte original avant d’en faire état. ]'),
            ]),

            P('Il en résulte une conséquence de conception qu’il importe de ne pas présenter comme une faiblesse. Le caractère paramétrable des seuils est précisément ce que le référentiel attend : inscrire des valeurs fixes dans le code reviendrait à imposer à l’organisation un jugement qui lui appartient. Les seuils sont en conséquence rassemblés dans un écran d’administration, où ils sont modifiables sans intervention sur le code, chacun étant borné et documenté.'),

            Titre('b) Trois catégories de seuils', 3),

            P('Les seuils employés ne sont pas de même nature, et les confondre exposerait à présenter comme normative une valeur qui ne l’est pas.'),

            Legende('Tableau 8 — Nature des seuils employés', true),
            Tableau(['Catégorie', 'Définition', 'Conséquence pour le paramétrage'], CATEGORIES, [24, 36, 40]),
            Legende('Source : élaboration personnelle'),

            P('Il est essentiel, dans toute présentation de l’outil, de ne pas présenter une convention interne comme une norme. Un seuil affirmé comme normatif sans fondement est une affirmation vérifiable, et donc contestable devant un contrôle.'),

            Titre('c) Classement des seuils effectivement paramétrés', 3),

            P('Seize seuils sont paramétrables dans l’application. Leur répartition entre les trois catégories est déséquilibrée, et il vaut mieux l’exposer que le laisser découvrir.'),

            Legende('Tableau 9 — Seuils paramétrés, valeur par défaut et fondement', true),
            Tableau(['Seuil', 'Valeur', 'Catégorie', 'Fondement'], SEUILS, [26, 16, 18, 40], [1, 2]),
            Legende('Source : élaboration personnelle'),

            P('Un seul seuil s’adosse à une obligation documentée : les trente jours de fraîcheur de la donnée, qui reprennent la périodicité mensuelle des rapports d’activité prévue par le manuel de procédures du programme. Une donnée de plus d’un mois signale donc un manquement à une obligation existante, et non une simple préférence de l’outil.'),

            P('Un seuil relève d’une référence professionnelle : l’indice de performance des coûts à 0,95, l’écart de cinq pour cent étant usuellement retenu comme seuil d’investigation dans la pratique de la valeur acquise. Cette valeur est défendable, mais elle ne s’impose pas.'),

            P([
                T('Deux seuils sont susceptibles de relever d’un texte réglementaire, et cette question doit être tranchée avant la soutenance. '),
                AVerifier('[ Vérifier auprès de l’Autorité nationale de régulation des marchés publics, ou dans le Code des marchés publics en vigueur, si le cumul des avenants est plafonné et si un délai de règlement des décomptes est imposé. Si un texte existe, citer le décret et l’article, paramétrer le seuil à la valeur du texte et placer un second seuil d’anticipation en deçà, afin que l’alerte se déclenche avant que le plafond ne soit atteint. Si aucun texte n’est trouvé, requalifier ces deux seuils en conventions internes. ]'),
            ]),

            P('Les douze seuils restants sont des conventions internes. Cette proportion n’a rien d’anormal : elle est la conséquence directe de l’absence de valeurs normatives constatée plus haut. Elle impose en contrepartie une discipline, exposée au point suivant.'),

            Titre('d) Recommandations de paramétrage', 3),

            Puce('Les seuils relevant d’un texte réglementaire doivent être paramétrés à la valeur du texte, et un second seuil d’anticipation placé en deçà afin que l’alerte se déclenche avant que le plafond ne soit atteint. Le franchissement d’un plafond réglementaire n’est pas un signal de gestion : c’est déjà une irrégularité.'),
            Puce('Les seuils de référence professionnelle doivent être documentés avec leur source dans la table de paramètres, de sorte que la justification survive au départ de celui qui les a fixés.'),
            Puce('Les conventions internes doivent être revues périodiquement au vu du taux de déclenchement observé. Un seuil qui déclenche sur tous les projets ne discrimine rien ; un seuil qui ne déclenche jamais ne sert à rien. Une revue annuelle, adossée au relevé des alertes de l’exercice écoulé, suffirait à corriger les valeurs les plus mal calibrées.'),

            P('Cette dernière recommandation n’est pas une précaution de style. Sur le projet retenu comme cas d’étude, cinq des onze règles se déclenchent simultanément, ce qui tient à l’état réel du chantier ; mais si la même configuration se répétait sur l’ensemble du portefeuille, elle signalerait un défaut de calibrage plutôt qu’une dérive générale.'),

            Titre('e) Références citées', 3),

            Ref('PROJECT MANAGEMENT INSTITUTE. Guide du corpus des connaissances en management de projet (Guide PMBOK). Newtown Square : Project Management Institute. [ Préciser l’édition et l’année consultées, ainsi que la page ou la section relative aux seuils de contrôle. ]'),
            Ref('PROJECT MANAGEMENT INSTITUTE. The Standard for Earned Value Management. Newtown Square : Project Management Institute. [ Préciser l’édition et l’année consultées. ]'),
            Ref([
                AVerifier('[ Le cas échéant : ORGANISATION INTERNATIONALE DE NORMALISATION. ISO 21502 — Management de projet, de programme et de portefeuille : lignes directrices sur le management de projet ; et ISO 10006 — Systèmes de management de la qualité : lignes directrices pour le management de la qualité dans les projets. Ne citer qu’après vérification du passage invoqué. ]'),
            ]),
            Ref([
                AVerifier('[ Le cas échéant : RÉPUBLIQUE DE CÔTE D’IVOIRE. Code des marchés publics en vigueur, décret et article relatifs au plafonnement des avenants et au délai de règlement des décomptes. ]'),
            ]),
            Ref('PROGRAMME DE DÉCENTRALISATION DES UNIVERSITÉS. Manuel de procédures — périodicité des rapports d’activité. [ Préciser la référence exacte du document et la section invoquée. ]'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
