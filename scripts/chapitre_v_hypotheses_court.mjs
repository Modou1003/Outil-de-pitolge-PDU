/**
 * Chapitre V — V.3 Vérification des hypothèses, version resserrée.
 *
 * Même contenu que la version longue, ramené à l'essentiel : un tableau de
 * synthèse par hypothèse fondu en un seul, les limites en tableau, et le
 * commentaire réduit aux trois points qui pèsent réellement.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (t, o = {}) => new TextRun({ text: t, font: POLICE, size: 24, ...o });
const AReprendre = (t) => T(t, { highlight: 'yellow' });

const P = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 110, line: 264 },
});

const Titre = (t, n = 2) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: n === 2 ? 26 : 24, bold: true })],
    heading: n === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3,
    spacing: { before: 220, after: 120 },
});

const Legende = (t, avant = false) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: avant ? { before: 160, after: 90 } : { before: 70, after: 160 },
});

const B = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDS = { top: B, bottom: B, left: B, right: B };

const Cel = (t, { entete = false, largeur, centre = false } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDS,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 50, bottom: 50, left: 70, right: 70 },
    children: [new Paragraph({
        children: [new TextRun({ text: t, font: POLICE, size: 20, bold: entete })],
        alignment: (entete || centre) ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 230 },
    })],
});

const Tableau = (entetes, lignes, largeurs, centrees = []) => new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({ tableHeader: true, children: entetes.map((e, i) => Cel(e, { entete: true, largeur: largeurs[i] })) }),
        ...lignes.map((l) => new TableRow({
            children: l.map((c, i) => Cel(c, { largeur: largeurs[i], centre: centrees.includes(i) })),
        })),
    ],
});

const SYNTHESE = [
    ['1', 'Unicité du dépôt et de la source', 'Base unique ; un seul document alimente six sections de l’application', 'Établi'],
    ['1', 'Absence de ressaisie', 'Alimentation par import du classeur mensuel, sans transcription', 'Établi'],
    ['1', 'Traçabilité et habilitation', 'Écritures attribuées et horodatées ; quatorze permissions réparties par profil', 'Établi'],
    ['1', 'Approbation formelle', 'Aucun circuit d’approbation : la saisie alimente les indicateurs sans visa hiérarchique', 'Non établi'],
    ['2', 'Synthèse du portefeuille', 'Agrégats, cycle de vie et cartographie en une vue ; consolidation à 32,13 %, identique à celle de la mission de contrôle', 'Établi'],
    ['2', 'Désignation des causes', 'Retard au démarrage mesuré par ouvrage : vingt-trois ouvrages signalés', 'Établi'],
    ['2', 'Signalement autonome', 'Onze règles réexaminées à chaque saisie ; cinq ouvertes sur les seules données réelles', 'Établi'],
    ['2', 'Actualité de la donnée', 'Indicateurs actualisés sans délai, mais donnée vieille de quarante-huit jours faute de transmission plus fréquente', 'Partiellement établi'],
];

const LIMITES = [
    ['Absence de circuit d’approbation', 'Introduire brouillon, soumission et validation, l’auteur ne validant pas sa propre saisie'],
    ['Enveloppe unique, sans source de financement', 'Rattacher les décomptes à une convention et filtrer les restitutions'],
    ['Dépendance au modèle de classeur importé', 'Rendre paramétrable la correspondance des feuilles et des colonnes'],
    ['Un seul projet éprouvé en profondeur', 'Alimenter deux ou trois projets de plus, puis mesurer les temps de réponse'],
    ['Indice de performance de coût sans portée', 'Chercher le signal de coût dans les avenants et le coût final estimé'],
    ['Ventilation mensuelle du coût reconstituée', 'Obtenir de la mission de contrôle la facturation mensuelle par ouvrage'],
    ['Avenants en instruction non représentables', 'Prévoir un état distinct, sans effet sur les indicateurs'],
    ['Restitution au seul format de document portable', 'Ajouter un export tabulaire des relevés et des indicateurs'],
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
            Titre('V.3. Vérification des hypothèses et appréciation des résultats'),

            P('Une hypothèse est ici tenue pour établie lorsque les éléments de preuve sont constatables sur les données réelles du chantier, partiellement établie lorsqu’une part de la réponse échappe à la portée de l’outil, et non établie lorsque la fonction supposée fait défaut. Cette gradation vaut mieux qu’un verdict binaire, qui obligerait à forcer la conclusion.'),

            Legende('Tableau V.9 — Synthèse de la vérification des hypothèses', true),
            Tableau(['H.', 'Élément', 'Constat', 'Appréciation'], SYNTHESE, [5, 24, 50, 21], [0, 3]),
            Legende('Source : essais réalisés sur le projet d’Odienné'),

            Titre('V.3.1. Hypothèse 1 : la dispersion des données de suivi', 3),

            P([
                AReprendre('[ Reprendre la formulation exacte de l’hypothèse. ]'),
            ]),

            P('La dispersion visée était double : les données d’un même projet résidaient en des lieux différents, et la même information circulait sous plusieurs versions. Les deux points sont résolus — le second par un mécanisme non prévu au départ, l’import du classeur de la mission de contrôle, qui supprime la transcription et donc la possibilité que deux agents obtiennent deux chiffres différents du même rapport.'),

            P('Une réserve doit être portée. Si l’hypothèse qualifie la donnée de « validée », le terme ne peut être retenu au sens d’une approbation hiérarchique : l’application n’en comporte pas. Deux garanties subsistent néanmoins : l’habilitation préalable de l’agent, et surtout l’origine de la donnée, issue du rapport mensuel visé par la mission de contrôle. Le contrôle existe, mais il se situe en amont de la saisie et non en aval.'),

            P([
                T('L’hypothèse est donc '),
                T('établie', { bold: true }),
                T(' quant à la centralisation et à l’unicité de la donnée, sous cette réserve. '),
                AReprendre('[ Si l’hypothèse emploie le terme « validée », préférer « issue d’un rapport mensuel visé » : la garantie porte alors sur le document source, ce qui est exact. ]'),
            ]),

            Titre('V.3.2. Hypothèse 2 : une vision synthétique et actualisée du programme', 3),

            P([
                AReprendre('[ Reprendre la formulation exacte de l’hypothèse. ]'),
            ]),

            P('Le caractère synthétique est établi de deux manières. Par agrégation, le tableau de bord réunissant en une vue ce qu’il fallait rassembler projet par projet. Par explication ensuite, ce qui n’était pas attendu : l’avancement consolidé de 32,13 pour cent dit qu’un retard existe, non pourquoi ; le retard au démarrage mesuré ouvrage par ouvrage y répond en désignant vingt-trois ouvrages.'),

            P('L’actualité n’est établie qu’en partie. Les indicateurs sont recalculés à chaque saisie, mais l’application ne reçoit de données qu’à la transmission du classeur mensuel : à la date des essais, la plus récente avait quarante-huit jours. Un outil de pilotage ne rend pas l’information plus fraîche que sa source ; il en rend l’accès immédiat et le vieillissement visible, ce que mesure l’indicateur de fraîcheur et que signale la règle d’alerte relative à l’absence de mise à jour.'),

            P([
                T('L’hypothèse est '),
                T('établie', { bold: true }),
                T(' quant au caractère synthétique de la vision, et '),
                T('partiellement établie', { bold: true }),
                T(' quant à son actualité, laquelle demeure liée à la périodicité de la source.'),
            ]),

            Titre('V.3.3. Limites identifiées et points d’amélioration', 3),

            Legende('Tableau V.10 — Limites identifiées et voies de correction', true),
            Tableau(['Limite', 'Voie de correction'], LIMITES, [45, 55]),
            Legende('Source : élaboration personnelle, à partir des essais conduits'),

            P('Deux de ces limites pèsent davantage que les autres. L’absence de circuit d’approbation est la seule à toucher une hypothèse, et la première correction à apporter ; le modèle de données s’y prête, la colonne de statut existant sans être exploitée. Le périmètre de la validation, ensuite : un projet unique, si complètement alimenté soit-il, ne dit rien du comportement de l’outil sur un portefeuille en charge.'),

            P('Une troisième mérite d’être signalée parce qu’elle prête à contresens. Un indice de performance de coût établi à 1,012 pourrait faire conclure à une maîtrise parfaite de la dépense. Il n’en est rien : sur un marché à prix unitaires, l’entreprise facture la valeur des travaux exécutés, de sorte que le coût réel suit par construction la valeur acquise. L’indicateur reste utile comme contrôle de cohérence, mais le signal de coût se trouve dans les avenants et dans l’écart entre le marché et le coût final estimé.'),

            P('Aucune de ces limites ne remet en cause le résultat central : à partir du même document source, l’application retrouve exactement les valeurs établies par la mission de contrôle, en désigne les causes ouvrage par ouvrage, et les signale d’elle-même. Les corrections proposées relèvent de l’extension du champ couvert, non de la rectification de ce qui a été mesuré.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
