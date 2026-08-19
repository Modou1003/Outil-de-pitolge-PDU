/**
 * Chapitre V — V.2.3 Comparaison avant / après.
 *
 * La colonne « avec l'application » décrit ce que l'outil fait effectivement,
 * et se vérifie en le manipulant. Les durées du circuit antérieur et les gains
 * chiffrés relèvent du relevé de terrain : ils restent à mesurer.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (t, o = {}) => new TextRun({ text: t, font: POLICE, size: 24, ...o });
const AMesurer = (t) => T(t, { highlight: 'yellow' });

const P = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 120, line: 276 },
});

const Titre = (t) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 26, bold: true })],
    heading: HeadingLevel.HEADING_2,
    spacing: { before: 260, after: 150 },
});

const Legende = (t, avant = false) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: avant ? { before: 200, after: 100 } : { before: 80, after: 200 },
});

const B = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDS = { top: B, bottom: B, left: B, right: B };

const Cel = (t, { entete = false, largeur, jaune = false } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDS,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 60, bottom: 60, left: 80, right: 80 },
    children: [new Paragraph({
        children: [new TextRun({
            text: t, font: POLICE, size: 20, bold: entete,
            highlight: jaune ? 'yellow' : undefined,
        })],
        alignment: entete ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 240 },
    })],
});

const Tableau = (entetes, lignes, largeurs) => new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({
            tableHeader: true,
            children: entetes.map((e, i) => Cel(e, { entete: true, largeur: largeurs[i] })),
        }),
        ...lignes.map((l) => new TableRow({
            children: l.map((c, i) => Cel(c, { largeur: largeurs[i], jaune: c.startsWith('[') })),
        })),
    ],
});

const COMPARAISON = [
    [
        'Alimenter le suivi d’un mois',
        '[ À mesurer ] Report manuel, dans un tableur ou un support de suivi, des taux d’avancement de chaque ouvrage relevés au rapport mensuel, puis des décomptes',
        'Dépôt du classeur mensuel de la mission de contrôle, contrôle du contenu reconnu à l’écran, puis validation. Une opération, sans transcription',
        '[ À mesurer ] Suppression de la transcription et du risque d’erreur de recopie qu’elle porte',
    ],
    [
        'Produire une situation consolidée du portefeuille',
        '[ À mesurer ] Rassemblement des situations transmises par chaque projet, puis compilation manuelle',
        'Affichage du tableau de bord : agrégats du portefeuille, répartition par étape du cycle de vie et localisation des sites sur la carte',
        '[ À mesurer ]',
    ],
    [
        'Connaître l’avancement d’un projet donné',
        '[ À mesurer ] Recherche puis lecture du dernier rapport mensuel disponible, dont la date d’arrêté peut remonter à plusieurs semaines',
        'Consultation de la fiche projet : avancement pondéré des trente ouvrages, recalculé à chaque saisie, avec l’ancienneté de la donnée affichée',
        '[ À mesurer ]',
    ],
    [
        'Calculer les indicateurs de performance d’un marché',
        '[ À mesurer ] Calcul sur tableur à partir des valeurs élémentaires, refait à chaque situation ; en pratique, lecture graphique du seul décalage en délais',
        'Restitution immédiate de la valeur planifiée, de la valeur acquise, du coût réel, des indices de performance, de la valeur acquise temporelle et de la fin projetée',
        '[ À mesurer ]',
    ],
    [
        'Produire un rapport destiné au comité de pilotage',
        '[ À mesurer ] Rédaction d’un document à partir de sources dispersées, avec ressaisie des chiffres et risque de discordance entre les documents',
        'Génération du rapport au format de document portable, sections choisies au moment du téléchargement, valeurs identiques à celles du tableau de bord',
        '[ À mesurer ]',
    ],
    [
        'Retrouver une pièce contractuelle',
        '[ À mesurer ] Recherche dans les répertoires partagés ou les archives de la section détentrice',
        'Consultation des documents rattachés au projet, accessibles depuis sa fiche',
        '[ À mesurer ]',
    ],
    [
        'Identifier un projet en dérive',
        '[ À mesurer ] Constat au fil des réunions périodiques tenues avec la maîtrise d’œuvre et l’entreprise, ou à la lecture des rapports',
        'Alertes ouvertes d’elles-mêmes par onze règles réexaminées à chaque saisie, hiérarchisées par gravité et refermées dès le rétablissement',
        '[ À mesurer ]',
    ],
];

const doc = new Document({
    styles: { default: { document: { run: { font: POLICE, size: 24 } } } },
    sections: [{
        properties: {
            page: {
                size: { width: 16838, height: 11906, orientation: 'landscape' },
                margin: { top: 1134, right: 1134, bottom: 1134, left: 1134 },
            },
        },
        children: [
            Titre('V.2.3. Comparaison avant / après : gains en temps, en qualité et en accessibilité'),

            P('L’appréciation de l’apport de l’outil suppose de disposer d’un point de comparaison. Les durées et modalités du circuit antérieur ont donc été relevées avant la mise en service, dans le cadre du diagnostic conduit au chapitre II.'),

            P('La comparaison porte sur sept tâches courantes du travail de suivi, retenues parce qu’elles se répètent et que leur coût cumulé est significatif. La première d’entre elles, l’alimentation mensuelle, ne figurait pas au diagnostic initial : elle s’est imposée en cours de développement comme la tâche déterminante, puisque toutes les autres en dépendent.'),

            Legende('Tableau V.8 — Comparaison des situations antérieure et actuelle', true),
            Tableau(['Tâche', 'Situation antérieure', 'Avec l’application', 'Gain observé'],
                COMPARAISON, [17, 30, 32, 21]),
            Legende('Source : chronométrages et entretiens réalisés avant et après la mise en service'),

            P('La colonne décrivant l’usage de l’application se vérifie en manipulant l’outil ; celle du circuit antérieur et celle des gains supposent en revanche un relevé auprès des agents, qui reste à conduire.'),

            P([
                AMesurer('[ À rédiger après remplissage : commenter les gains en distinguant ce qui relève du temps économisé, de la qualité de l’information et de son accessibilité. Rester mesuré : un gain annoncé sans mesure est une affirmation, non un résultat. ]'),
            ]),

            P('Trois observations peuvent toutefois être avancées dès à présent, car elles ne dépendent d’aucun chronométrage.'),

            P('La première tient à la nature du gain sur l’alimentation. Reporter à la main trente taux d’avancement chaque mois ne demande qu’une vingtaine de minutes : le temps économisé y est donc modeste. Ce qui change, c’est la suppression d’une transcription, et avec elle du risque qu’un chiffre soit recopié de travers. Sur un ouvrage pesant vingt et un pour cent du marché, une erreur de report se propage à l’avancement consolidé sans que rien ne la signale. Le gain est ici de fiabilité, non de temps, et c’est le plus important des deux.'),

            P('La deuxième tient à l’accessibilité. Le chantier d’Odienné se trouve à quelque huit cent soixante-dix kilomètres d’Abidjan, ce qui rend les visites de contrôle coûteuses et espacées. L’information sur son état ne circulait donc qu’au rythme des documents transmis et des réunions tenues. Elle est désormais consultable de n’importe où, par tout agent habilité, sans qu’aucun document n’ait à être demandé ni transmis.'),

            P('La troisième tient à la détection. Le rapport de la mission de contrôle signale les retards, mais un lecteur doit le lire pour les connaître, et le lire à temps. L’application, elle, ouvre ses alertes d’elle-même à chaque saisie et les hiérarchise : sur les données réelles du chantier, cinq règles se déclenchent sans qu’aucune situation ait été provoquée. La différence ne porte pas sur la disponibilité de l’information — elle existait — mais sur le fait qu’elle se signale au lieu d’attendre d’être cherchée.'),

            P('Une précaution de lecture s’impose enfin. Les durées relevées après mise en service correspondent à un usage encore récent, antérieur à la pleine maîtrise de l’outil par les agents. Elles constituent donc une estimation prudente : l’écart réel est vraisemblablement supérieur à celui mesuré. Inversement, elles portent sur un périmètre restreint et ne sauraient être extrapolées sans prudence à l’ensemble du portefeuille.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
