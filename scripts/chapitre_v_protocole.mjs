/**
 * Chapitre V — V.1 Protocole de validation, version corrigée.
 *
 * Le texte est repris de la rédaction de l'étudiant, amendé pour ne décrire
 * que des essais que l'application sait réellement conduire.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (texte, opts = {}) => new TextRun({ text: texte, font: POLICE, size: 24, ...opts });

const P = (texte, opts = {}) => new Paragraph({
    children: Array.isArray(texte) ? texte : [T(texte)],
    alignment: opts.alignment ?? AlignmentType.JUSTIFIED,
    spacing: { after: opts.after ?? 120, line: 276 },
    ...opts.paragraph,
});

const Titre = (texte, niveau) => new Paragraph({
    children: [new TextRun({ text: texte, font: POLICE, size: niveau === 1 ? 28 : 26, bold: true })],
    heading: niveau === 1 ? HeadingLevel.HEADING_1 : HeadingLevel.HEADING_2,
    spacing: { before: 240, after: 160 },
});

const Puce = (texte) => new Paragraph({
    children: [T(texte)],
    bullet: { level: 0 },
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 100, line: 276 },
});

const Legende = (texte, avant = false) => new Paragraph({
    children: [new TextRun({ text: texte, font: POLICE, size: 20, italics: true })],
    spacing: avant ? { before: 200, after: 100 } : { before: 80, after: 200 },
});

const BORDURE = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDURES = { top: BORDURE, bottom: BORDURE, left: BORDURE, right: BORDURE };

const Cellule = (texte, { entete = false, largeur } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDURES,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 60, bottom: 60, left: 80, right: 80 },
    children: [new Paragraph({
        children: [new TextRun({ text: texte, font: POLICE, size: 20, bold: entete })],
        alignment: entete ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 240 },
    })],
});

const Tableau = (entetes, lignes, largeurs) => new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({
            tableHeader: true,
            children: entetes.map((e, i) => Cellule(e, { entete: true, largeur: largeurs[i] })),
        }),
        ...lignes.map((ligne) => new TableRow({
            children: ligne.map((c, i) => Cellule(c, { largeur: largeurs[i] })),
        })),
    ],
});

// ─────────────────────────────────────────────────────────── Tableau V.1

const V1_ENTETES = ['Critère de sélection', 'Caractéristique du projet', 'Ce que cela permet d’éprouver'];
const V1_LIGNES = [
    [
        'Stade d’avancement',
        'Projet en phase de travaux, contrairement à la plupart des opérations récentes encore en études',
        'C’est la seule configuration où avancement physique et avancement financier coexistent, condition nécessaire à l’application de la méthode de la valeur acquise et de sa transposition temporelle',
    ],
    [
        'Structure des ouvrages',
        'Trente-huit bâtiments, ouvrages de voirie et réseaux, château d’eau, galerie piétonne',
        'La décomposition en ouvrages pondérés et la consolidation d’un avancement global représentatif',
    ],
    [
        'Structure contractuelle',
        'Marché de travaux ayant donné lieu à des avenants portant sur le montant comme sur le délai',
        'Le module marché : conservation du montant initial, calcul du montant actualisé et report de l’échéance contractuelle',
    ],
    [
        'Décalages de démarrage constatés',
        'Plusieurs ouvrages dont le démarrage effectif accuse plusieurs mois d’écart avec la date portée au planning contractuel',
        'La mesure du retard au démarrage par ouvrage et le déclenchement de l’alerte correspondante',
    ],
    [
        'Enjeu financier',
        'Montant global du financement le plus élevé du portefeuille',
        'La fiabilité des calculs sur des ordres de grandeur réels, où une erreur d’arrondi devient significative',
    ],
    [
        'Accessibilité des données',
        'Projet suivi directement par la section opérations, rapports mensuels de l’entreprise exécutante disponibles et visite de site réalisée',
        'La saisie de données réelles et non simulées, seule susceptible de révéler les difficultés d’usage',
    ],
];

// ─────────────────────────────────────────────────────────── Tableau V.2

const V2_ENTETES = ['Catégorie', 'Données requises', 'Source'];
const V2_LIGNES = [
    [
        'Contractuelles',
        'Marché de travaux : objet, entreprise titulaire, montant initial, délai, date d’ordre de service de démarrage ; avenants en montant et en délai, ordres de service éventuels',
        'Section des marchés et affaires juridiques',
    ],
    [
        'Techniques',
        'Décomposition en ouvrages puis en lots ; jalons contractuels et dates prévues',
        'Section opérations et suivi-évaluation ; maîtrise d’œuvre',
    ],
    [
        'Clé de pondération',
        'Pondération retenue pour chacun des trente-huit ouvrages et justification de son origine : montants du devis quantitatif estimatif ou surfaces bâties. La somme doit atteindre cent pour cent, contrainte que l’application signale',
        'Marché de travaux ; section opérations et suivi-évaluation',
    ],
    [
        'Calendrier contractuel',
        'Pour chaque ouvrage : durée contractuelle, dates de début et de fin prévues au planning du marché, dates de début et de fin réellement constatées',
        'Planning contractuel annexé au marché ; rapports mensuels de l’entreprise exécutante',
    ],
    [
        'Avancement physique',
        'Historique mensuel des taux planifiés et réalisés, par ouvrage, sur la période retenue',
        'Rapports mensuels de l’entreprise exécutante ; attachements visés par la maîtrise d’œuvre',
    ],
    [
        'Avancement financier',
        'Historique mensuel des décomptes validés et des paiements ; valeurs cumulées permettant d’établir la valeur planifiée, la valeur acquise et le coût réel',
        'Section des affaires administratives et financières',
    ],
    [
        'Situation antérieure',
        'Temps de production d’une situation consolidée, d’un rapport et d’une recherche de pièce, selon la pratique en vigueur avant l’outil',
        'Entretiens avec les agents des sections concernées et reconstitution de la pratique',
    ],
];

// ─────────────────────────────────────────────────────────── Document

const document = new Document({
    styles: {
        default: {
            document: { run: { font: POLICE, size: 24 } },
        },
    },
    sections: [{
        properties: {
            page: {
                size: { width: 11906, height: 16838 },
                margin: { top: 1134, right: 1134, bottom: 1134, left: 1418 },
            },
        },
        children: [
            Titre('CHAPITRE V — TEST DE L’OUTIL SUR LE PORTEFEUILLE PDU', 1),
            Titre('V.1. Protocole de validation', 2),
            Titre('V.1.1. Sélection de l’échantillon représentatif de projets PDU', 2),

            P('La validation ne peut porter avec la même profondeur sur l’ensemble du portefeuille. Les projets s’y trouvent à des stades trop différents : certains sont achevés et en service, d’autres encore au stade des études techniques. Une validation étalée uniformément serait superficielle partout.'),
            P('Le protocole retient donc deux niveaux complémentaires.'),

            Puce('Une validation en profondeur, sur le projet de construction et d’équipement de l’université d’Odienné. Ce projet sert de cas d’étude complet : création de la fiche, enregistrement du marché, de ses ouvrages et de leurs lots, saisie des avancements physique et financier, calcul des indicateurs, mesure du retard au démarrage des ouvrages, déclenchement des alertes et production des rapports.'),
            Puce('Une validation en largeur, sur le reste du portefeuille. Les autres opérations sont enregistrées au niveau de leurs données de référence — localisation, emprise, étape du cycle de vie, financement — afin d’éprouver la consolidation multi-projets et la cartographie, qu’un projet unique ne permettrait pas de tester.'),

            P('Le choix d’Odienné comme cas d’étude principal se justifie par six caractéristiques.'),

            Legende('Tableau V.1 — Justification du choix du projet d’Odienné', true),
            Tableau(V1_ENTETES, V1_LIGNES, [22, 34, 44]),
            Legende('Source : élaboration personnelle'),

            P('Ce choix comporte deux limites qu’il convient d’énoncer dès le protocole plutôt que de les découvrir aux conclusions.'),
            P('La première tient à l’échantillon : un projet unique, si complet soit-il, ne permet pas d’éprouver le comportement de l’outil sur un portefeuille en charge réelle. La validation en largeur atténue cette limite sans la lever entièrement.'),
            P('La seconde tient au montage financier. Le projet d’Odienné associe un prêt concessionnel et une contrepartie de l’État, alors que l’application rattache à chaque projet une enveloppe unique. Le suivi de la consommation par source de financement et la production d’un rapport restreint à une convention ne peuvent donc pas être éprouvés ici : ils relèvent des perspectives d’évolution. Ces deux limites sont reprises au point V.3.3.'),

            P('Les données nécessaires à la validation sont recensées ci-après. Leur collecte complète conditionne le déroulement des essais.'),

            Legende('Tableau V.2 — Données à collecter pour la validation', true),
            Tableau(V2_ENTETES, V2_LIGNES, [20, 47, 33]),
            Legende('Source : élaboration personnelle'),

            P('L’alimentation de l’application repose principalement sur le rapport mensuel établi par l’entreprise exécutante. Ce choix mérite d’être souligné : la donnée provient de celui qui conduit les travaux, et non d’une ressaisie opérée par l’unité de gestion à partir de documents seconds. La saisie consiste alors à reporter dans l’outil des valeurs déjà arrêtées et visées, ce qui limite le risque d’écart entre le suivi et la réalité du chantier.'),

            P([
                T('L’historique retenu couvre les '),
                T('N', { italics: true }),
                T(' mois de '),
                T('[mois année]', { highlight: 'yellow' }),
                T(' à '),
                T('[mois année]', { highlight: 'yellow' }),
                T(', les données étant arrêtées au '),
                T('[date]', { highlight: 'yellow' }),
                T(', date du dernier rapport mensuel disponible à la rédaction. Une série d’au moins six relevés mensuels est nécessaire pour que la courbe en S prenne forme et que l’indice de performance des délais calculé selon la méthode de la valeur acquise temporelle conserve un sens ; en deçà, les résultats sont présentés comme indicatifs.'),
            ]),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(document).then((buffer) => {
    writeFileSync(chemin, buffer);
    console.log('Écrit : ' + chemin);
});
