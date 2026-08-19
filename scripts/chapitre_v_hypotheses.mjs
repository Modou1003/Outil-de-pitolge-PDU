/**
 * Chapitre V — V.3 Vérification des hypothèses et appréciation des résultats.
 *
 * Les éléments de preuve invoqués sont ceux effectivement constatés sur le
 * chantier d'Odienné chargé dans l'application à partir de la base de calcul
 * arrêtée au 30 juin 2026.
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
    spacing: { after: 120, line: 276 },
});

const Titre = (t, n = 2) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: n === 2 ? 26 : 24, bold: true })],
    heading: n === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3,
    spacing: { before: 260, after: 150 },
});

const Puce = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    bullet: { level: 0 },
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 90, line: 276 },
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
        children: [new TextRun({ text: t, font: POLICE, size: 20, bold: entete, highlight: jaune ? 'yellow' : undefined })],
        alignment: entete ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 240 },
    })],
});

const Tableau = (entetes, lignes, largeurs) => new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({ tableHeader: true, children: entetes.map((e, i) => Cel(e, { entete: true, largeur: largeurs[i] })) }),
        ...lignes.map((l) => new TableRow({
            children: l.map((c, i) => Cel(c, { largeur: largeurs[i], jaune: c.startsWith('[') })),
        })),
    ],
});

// ─────────────────────────────── éléments de preuve, hypothèse 1

const PREUVES_1 = [
    ['Unicité du dépôt',
        'Trente ouvrages, six cents relevés d’avancement physique, six cents relevés de valeur acquise, quatorze décomptes et le calendrier contractuel de chaque ouvrage résident dans une base unique, interrogeable en un point',
        'Établi'],
    ['Origine unique de la donnée',
        'Un seul document, la base de calcul mensuelle de la mission de contrôle, alimente six sections de l’application : avancement physique, avancement financier, décomptes, calendriers d’ouvrages, indicateurs et alertes',
        'Établi'],
    ['Absence de ressaisie',
        'L’alimentation se fait par import du classeur, sans transcription manuelle des valeurs. Un import rejoué à l’identique n’ajoute aucun enregistrement',
        'Établi'],
    ['Traçabilité des écritures',
        'Chaque création, modification et suppression est attribuée à son auteur et horodatée au journal d’activité, consultable par le seul profil administrateur',
        'Établi'],
    ['Habilitation différenciée',
        'Quatorze permissions réparties entre les profils reproduisent la séparation des compétences de l’unité de gestion : la section des marchés instruit les avenants sans accéder aux décomptes, la section financière l’inverse',
        'Établi'],
    ['Approbation formelle de la donnée',
        'La saisie alimente les indicateurs dès son enregistrement, sans transiter par un circuit d’approbation hiérarchique intégré à l’outil',
        'Non établi'],
];

// ─────────────────────────────── éléments de preuve, hypothèse 2

const PREUVES_2 = [
    ['Synthèse du portefeuille',
        'Le tableau de bord restitue les agrégats du portefeuille, la répartition par étape du cycle de vie et la localisation des sites, en une seule vue',
        'Établi'],
    ['Exactitude de la consolidation',
        'L’avancement consolidé restitué, 32,13 pour cent, coïncide exactement avec celui du rapport mensuel de la mission de contrôle, établi indépendamment',
        'Établi'],
    ['Indicateurs de performance',
        'Valeur planifiée, valeur acquise, coût réel, indices de performance, valeur acquise temporelle et fin projetée sont produits sans intervention ; le décalage en délais calculé, neuf mois et six dixièmes, retrouve les neuf mois lus par la mission de contrôle',
        'Établi'],
    ['Désignation des causes',
        'Le retard au démarrage est mesuré ouvrage par ouvrage : vingt-trois des trente ouvrages sont signalés, ce que l’avancement consolidé seul ne permettait pas de voir',
        'Établi'],
    ['Signalement autonome',
        'Onze règles de détection réexaminées à chaque saisie ouvrent et referment les alertes d’elles-mêmes ; cinq se déclenchent sur les seules données réelles du chantier',
        'Établi'],
    ['Accessibilité',
        'L’information est consultable de tout poste connecté par tout agent habilité, sans transmission de document, ce qui importe pour un chantier situé à quelque huit cent soixante-dix kilomètres du siège',
        'Établi'],
    ['Actualisation',
        'L’actualisation dépend de la transmission du classeur mensuel : à la date d’arrêté des essais, la donnée avait quarante-huit jours. L’outil signale lui-même cette ancienneté par son indicateur de fraîcheur, mais ne l’abrège pas',
        'Partiellement établi'],
];

// ─────────────────────────────── limites

const LIMITES = [
    ['Absence de circuit d’approbation',
        'La donnée est saisie par un agent habilité et tracée, non validée par un supérieur au sein de l’outil',
        'Introduire un circuit brouillon, soumission, validation, avec interdiction pour l’auteur de valider sa propre saisie'],
    ['Suivi par source de financement',
        'Le projet porte une enveloppe unique ; le cofinancement associant le prêt concessionnel et la contrepartie de l’État n’est pas distingué, et aucun rapport ne peut être restreint à une convention',
        'Rattacher les décomptes à une source de financement et filtrer les restitutions en conséquence'],
    ['Dépendance au modèle de classeur',
        'L’import repose sur la structure du classeur de la mission de contrôle ; une refonte de ce document interromprait la lecture',
        'L’écran de contrôle rend la rupture visible au lieu de la laisser produire des données fausses ; une correspondance paramétrable des feuilles et colonnes lèverait la dépendance'],
    ['Périmètre de la validation',
        'Un seul projet a été éprouvé en profondeur, et le comportement de l’outil en charge réelle n’a pas été mesuré',
        'Étendre l’alimentation complète à deux ou trois projets supplémentaires, puis mesurer les temps de réponse à volumétrie croissante'],
    ['Portée de l’indice de performance de coût',
        'Sur un marché à prix unitaires, l’entreprise facture la valeur des travaux exécutés : le coût réel suit la valeur acquise et l’indice reste voisin de l’unité, sans porter d’information sur la maîtrise de la dépense',
        'Chercher le signal de coût dans les avenants et dans l’écart entre le marché et le coût final estimé, plutôt que dans cet indice'],
    ['Reconstitution de la ventilation mensuelle du coût',
        'La facturation n’est connue que cumulée par ouvrage : sa répartition entre les mois est reconstituée au rythme de la valeur acquise. Les totaux sont exacts, le profil temporel approché',
        'Obtenir de la mission de contrôle la facturation mensuelle par ouvrage, qu’elle établit déjà pour ses propres besoins'],
    ['Précision des dates de démarrage',
        'Le planning ne donne le démarrage effectif qu’au mois : la convention retenue place l’événement au premier jour, d’où une incertitude pouvant atteindre trente jours',
        'Sans incidence sur des retards qui se comptent en mois ; à préciser si le suivi devenait hebdomadaire'],
    ['Avenants en instruction',
        'La prolongation de huit mois soumise au bailleur n’est pas représentable : seul un avenant approuvé peut être enregistré, sous peine d’afficher une échéance qui n’existe pas',
        'Prévoir un état « en instruction » distinct, sans effet sur les indicateurs'],
    ['Indicateurs de la valeur acquise non produits',
        'Le reste à engager et l’écart à l’achèvement ne sont pas restitués',
        'Les déduire du coût final estimé, déjà calculé'],
    ['Absence de restitution tabulaire',
        'Les restitutions sont produites au format de document portable, non exploitable pour un retraitement',
        'Ajouter un export tabulaire des relevés et des indicateurs'],
];

// ─────────────────────────────────────────────────────────── document

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

            P('La validation conduite au point précédent portait sur le fonctionnement de l’outil. Il reste à confronter ses résultats aux hypothèses formulées en introduction, ce qui suppose d’examiner non seulement ce que l’application fait, mais ce qu’elle ne fait pas.'),

            P('Une convention de lecture est nécessaire. Une hypothèse n’est ici tenue pour établie que si les éléments de preuve invoqués sont constatables sur les données réelles du chantier ; elle est dite partiellement établie lorsque l’outil apporte l’essentiel de la réponse mais qu’une part échappe à sa portée ; elle n’est pas établie lorsque la fonction supposée fait défaut. Cette gradation vaut mieux qu’un verdict binaire, qui obligerait à forcer la conclusion dans un sens ou dans l’autre.'),

            // ────────────────────────────────────────────── hypothèse 1
            Titre('V.3.1. Hypothèse 1 : la base centralisée résout-elle la dispersion des données de suivi ?', 3),

            P([
                AReprendre('[ Reprendre ici la formulation exacte de l’hypothèse telle qu’elle figure en introduction. ]'),
            ]),

            P('L’examen porte sur six éléments, dont cinq sont établis et un ne l’est pas.'),

            Legende('Tableau V.9 — Éléments de vérification de la première hypothèse', true),
            Tableau(['Élément', 'Constat', 'Appréciation'], PREUVES_1, [22, 62, 16]),
            Legende('Source : essais réalisés sur le projet d’Odienné'),

            P('La dispersion visée par l’hypothèse était de deux ordres : les données d’un même projet résidaient en des lieux différents, et la même information circulait sous plusieurs versions. Le premier point est résolu sans réserve : la base est unique et un seul document l’alimente. Le second l’est également, mais par un mécanisme qui mérite d’être souligné, car il n’était pas prévu au départ : l’import du classeur de la mission de contrôle supprime la transcription, et avec elle la possibilité que deux agents obtiennent deux chiffres différents à partir du même rapport.'),

            P('Une réserve doit cependant être portée, et elle touche à la formulation de l’hypothèse. Si celle-ci qualifie la donnée centralisée de « validée », le terme ne peut être retenu au sens d’une approbation hiérarchique : l’application ne comporte pas de circuit d’approbation, et un relevé alimente les indicateurs dès son enregistrement. Deux garanties subsistent néanmoins, qui ne sont pas de pure forme. La première est l’habilitation préalable : seul un agent titulaire de la compétence correspondante peut écrire. La seconde, plus décisive, est l’origine de la donnée : elle provient du rapport mensuel visé par la mission de contrôle, document contradictoire établi hors de l’outil. Le contrôle existe donc, mais il se situe en amont de la saisie et non en aval.'),

            P([
                T('L’hypothèse est en conséquence tenue pour '),
                T('établie', { bold: true }),
                T(' quant à la centralisation et à l’unicité de la donnée, sous la réserve exposée quant à son approbation formelle. '),
                AReprendre('[ Si l’hypothèse est formulée avec le terme « validée », il est préférable de la reformuler en « issue d’un rapport mensuel visé » : la garantie porte alors sur le document source, ce qui est exact, plutôt que sur un mécanisme de l’outil, ce qui ne l’est pas. ]'),
            ]),

            // ────────────────────────────────────────────── hypothèse 2
            Titre('V.3.2. Hypothèse 2 : l’outil intégré fournit-il une vision synthétique et actualisée du programme ?', 3),

            P([
                AReprendre('[ Reprendre ici la formulation exacte de l’hypothèse telle qu’elle figure en introduction. ]'),
            ]),

            P('L’hypothèse comporte deux termes qu’il convient de traiter séparément, car ils ne reçoivent pas la même réponse : le caractère synthétique de la vision, et son actualité.'),

            Legende('Tableau V.10 — Éléments de vérification de la seconde hypothèse', true),
            Tableau(['Élément', 'Constat', 'Appréciation'], PREUVES_2, [22, 62, 16]),
            Legende('Source : essais réalisés sur le projet d’Odienné'),

            P('Le caractère synthétique est établi, et il l’est de deux manières distinctes. Par agrégation d’abord : le tableau de bord réunit en une vue ce qu’il fallait auparavant rassembler projet par projet, et la consolidation restituée coïncide exactement avec celle de la mission de contrôle. Par explication ensuite, ce qui n’était pas attendu : l’avancement consolidé de 32,13 pour cent dit qu’un retard existe, mais non pourquoi. Le retard au démarrage mesuré ouvrage par ouvrage y répond en désignant vingt-trois ouvrages concernés. Une synthèse qui se contenterait d’agréger masquerait cette information ; celle-ci la fait apparaître.'),

            P('L’actualité, en revanche, n’est établie qu’en partie, et la nuance est importante. L’application actualise ses indicateurs à chaque saisie, sans délai ni recalcul manuel. Mais elle ne dispose de données nouvelles que lorsque le classeur mensuel lui est transmis : à la date d’arrêté des essais, la donnée la plus récente avait quarante-huit jours. La périodicité de l’information demeure donc celle du rapport mensuel, que l’outil ne peut abréger puisqu’il n’en est pas l’auteur. Ce qui a changé n’est pas la fraîcheur de la donnée, mais deux choses : sa disponibilité immédiate dès qu’elle existe, sans transmission ni demande, et le fait que l’outil affiche lui-même son ancienneté au lieu de la laisser ignorer.'),

            P('Cette distinction mérite d’être assumée plutôt qu’escamotée. Un outil de pilotage ne rend pas l’information plus fraîche que sa source ; il en rend l’accès immédiat et le vieillissement visible. C’est précisément ce que l’indicateur de fraîcheur mesure, et c’est aussi ce que signale la règle d’alerte relative à l’absence de mise à jour, ouverte sur le chantier d’Odienné à la date des essais.'),

            P([
                T('L’hypothèse est donc tenue pour '),
                T('établie', { bold: true }),
                T(' quant au caractère synthétique de la vision restituée, et pour '),
                T('partiellement établie', { bold: true }),
                T(' quant à son actualité, laquelle demeure liée à la périodicité de la source.'),
            ]),

            // ────────────────────────────────────────────── limites
            Titre('V.3.3. Limites identifiées et points d’amélioration', 3),

            P('Les limites qui suivent ont été relevées au fil des essais. Certaines relèvent du périmètre volontairement retenu, d’autres de contraintes tenant à la donnée disponible, d’autres enfin de fonctions manquantes. Les énoncer n’affaiblit pas le travail : une limite reconnue et située se corrige, une limite passée sous silence se découvre à l’usage.'),

            Legende('Tableau V.11 — Limites identifiées et voies de correction', true),
            Tableau(['Limite', 'Nature de la limite', 'Voie de correction'], LIMITES, [22, 44, 34]),
            Legende('Source : élaboration personnelle, à partir des essais conduits'),

            P('Trois de ces limites appellent un commentaire, car elles pèsent davantage que les autres sur la portée des résultats.'),

            P('La première est l’absence de circuit d’approbation. Elle est la seule à toucher une hypothèse, et c’est la première correction à apporter si le travail devait être poursuivi. Le modèle de données s’y prête déjà, la colonne de statut existant sans être exploitée.'),

            P('La deuxième est le périmètre de la validation. Un projet unique, si complètement alimenté soit-il, ne dit rien du comportement de l’outil sur un portefeuille en charge. La validation en largeur, conduite sur les données de référence des autres opérations, atténue cette limite sans la lever.'),

            P('La troisième, la plus contre-intuitive, est la portée de l’indice de performance de coût. Un lecteur pressé conclurait d’un indice à 1,012 que la dépense est parfaitement maîtrisée. Il n’en est rien : cette valeur résulte du mode de facturation d’un marché à prix unitaires, où le coût réel suit par construction la valeur acquise. L’indicateur reste utile comme contrôle de cohérence — un écart signalerait une facturation déconnectée de l’avancement — mais il ne renseigne pas sur la maîtrise des coûts. Le signal se trouve dans les avenants et dans l’écart entre le marché et le coût final estimé.'),

            P('Aucune de ces limites ne remet en cause le résultat central des essais : à partir du même document source, l’application retrouve exactement les valeurs établies par la mission de contrôle, en désigne les causes ouvrage par ouvrage, et les signale d’elle-même. Les corrections proposées relèvent de l’extension du champ couvert, non de la rectification de ce qui a été mesuré.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
