/**
 * Chapitre V — V.1.2 Présentation du projet support de la validation.
 *
 * Le contexte et la programmation universitaire sont repris, reformulés, du
 * mémoire de KOUASSI (2025) consacré au même projet ; les données
 * contractuelles proviennent des pièces du marché détenues par l'UGP et
 * restent à confirmer par l'étudiant.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (texte, opts = {}) => new TextRun({ text: texte, font: POLICE, size: 24, ...opts });
const AConfirmer = (texte) => T(texte, { highlight: 'yellow' });

const P = (texte) => new Paragraph({
    children: Array.isArray(texte) ? texte : [T(texte)],
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 120, line: 276 },
});

const Titre = (texte, niveau) => new Paragraph({
    children: [new TextRun({ text: texte, font: POLICE, size: niveau === 2 ? 26 : 24, bold: true })],
    heading: niveau === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3,
    spacing: { before: 240, after: 140 },
});

const Puce = (enfants) => new Paragraph({
    children: Array.isArray(enfants) ? enfants : [T(enfants)],
    bullet: { level: 0 },
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 80, line: 276 },
});

const Legende = (texte, avant = false) => new Paragraph({
    children: [new TextRun({ text: texte, font: POLICE, size: 20, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: avant ? { before: 200, after: 100 } : { before: 80, after: 200 },
});

const Figure = (texte) => new Paragraph({
    children: [new TextRun({ text: texte, font: POLICE, size: 20, italics: true, highlight: 'yellow' })],
    alignment: AlignmentType.CENTER,
    spacing: { before: 200, after: 60 },
});

const BORDURE = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDURES = { top: BORDURE, bottom: BORDURE, left: BORDURE, right: BORDURE };

const Cellule = (texte, { entete = false, largeur, centre = false, jaune = false } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDURES,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 60, bottom: 60, left: 80, right: 80 },
    children: [new Paragraph({
        children: [new TextRun({
            text: texte, font: POLICE, size: 20,
            bold: entete, highlight: jaune ? 'yellow' : undefined,
        })],
        alignment: (entete || centre) ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 240 },
    })],
});

const Tableau = (entetes, lignes, largeurs, colonnesJaunes = []) => new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({
            tableHeader: true,
            children: entetes.map((e, i) => Cellule(e, { entete: true, largeur: largeurs[i] })),
        }),
        ...lignes.map((ligne) => new TableRow({
            children: ligne.map((c, i) => Cellule(c, {
                largeur: largeurs[i],
                centre: i > 0,
                jaune: colonnesJaunes.includes(i),
            })),
        })),
    ],
});

// ───────────────────────────────────────────── Tableau : programmation

const PHASES_ENTETES = ['Composante', 'Phase 1\n(2024-2026)', 'Phase 2', 'Phase 3', 'Phase 4'];
const PHASES = [
    ['UFR Sciences et technologies', '—', '1 400', '3 000', '4 000'],
    ['UFR Sciences biologiques', '1 000', '1 635', '3 503', '4 670'],
    ['UFR Agro-industrie et sciences des techniques alimentaires', '700', '1 168', '2 498', '3 330'],
    ['UFR Médecine', '—', '816', '1 748', '2 330'],
    ['Institut des sciences vétérinaires', '600', '1 050', '2 250', '3 000'],
    ['École de commerce et de gestion', '700', '935', '2 003', '2 670'],
    ['Total', '3 000', '7 000', '15 000', '20 000'],
];

// ───────────────────────────────────────────── Tableau : données contractuelles

const CONTRAT_ENTETES = ['Rubrique', 'Valeur'];
const CONTRAT = [
    ['Objet du marché', 'Travaux de construction de l’Université d’Odienné — tranche 1'],
    ['Numéro du marché', 'n° 2024-0-00-00-2-0503/05-333'],
    ['Maître d’ouvrage', 'Ministère de l’Enseignement Supérieur et de la Recherche Scientifique, représenté par le PDU'],
    ['Assistance à maîtrise d’ouvrage', 'Groupement TAEP (Koweït) — IETF (Côte d’Ivoire)'],
    ['Entreprise titulaire', 'PFO CONSTRUCTION'],
    ['Financement', 'Banque islamique de développement, avec contrepartie de l’État de Côte d’Ivoire'],
    ['Montant du marché (HT/HD)', '68 879 636 067 F CFA'],
    ['Signature du maître d’ouvrage', '8 juillet 2024'],
    ['Approbation du marché', '29 juillet 2024'],
    ['Démarrage contractuel', '4 novembre 2024'],
    ['Échéance contractuelle', '3 novembre 2026'],
    ['Délai contractuel', '24 mois'],
    ['Avenant de délai en instruction', 'Prolongation de 8 mois soumise à la Banque islamique de développement, portant le délai à 32 mois — non encore approuvée'],
    ['Nombre d’ouvrages suivis', '[à confirmer : treize bâtiments recensés au rapport de suivi, pour trente-huit ouvrages annoncés]'],
];

// ───────────────────────────────────────────────────────────── Document

const document = new Document({
    styles: { default: { document: { run: { font: POLICE, size: 24 } } } },
    sections: [{
        properties: {
            page: {
                size: { width: 11906, height: 16838 },
                margin: { top: 1134, right: 1134, bottom: 1134, left: 1418 },
            },
        },
        children: [
            Titre('V.1.2. Présentation du projet support de la validation', 2),

            P([
                T('Le projet retenu comme cas d’étude mérite d’être décrit avant que ne soient exposés les essais qui y sont conduits. La présente description s’appuie sur les pièces contractuelles du marché détenues par l’unité de gestion, complétées, pour le contexte et la programmation universitaire, par la monographie que '),
                T('KOUASSI (2025)'),
                T(' a consacrée au même projet.'),
            ]),

            Titre('a) Situation et emprise', 3),

            P('La ville d’Odienné est le chef-lieu de la région du Kabadougou, au nord-ouest de la Côte d’Ivoire, dans un relief de savane arborée. Elle se trouve à environ huit cent soixante-dix kilomètres d’Abidjan, éloignement qui n’est pas sans conséquence sur le suivi du chantier : il rend les déplacements de contrôle coûteux et espacés, et fait de la remontée d’information à distance une nécessité plutôt qu’un confort.'),

            P('Le site d’implantation couvre une emprise d’environ quatre cents hectares. Il est bordé au nord par le quartier d’Odienné Est, à quelque trois kilomètres et demi, au sud par le village d’Odienné-Sienso à cinq kilomètres et demi, à l’est par Denguélé à neuf kilomètres, et à l’ouest par le village de Zievaso à cinq kilomètres. Cette emprise, très supérieure aux besoins immédiats, s’explique par le caractère progressif de l’opération.'),

            Figure('[ Figure V.1 — Situation géographique de la ville d’Odienné. Source : KOUASSI (2025) ]'),
            Figure('[ Figure V.2 — Localisation du site universitaire. Source : KOUASSI (2025) ]'),

            Titre('b) Programmation et phasage', 3),

            P('L’université est conçue pour accueillir vingt mille étudiants à terme, capacité atteinte par étapes successives de trois mille, sept mille, quinze mille puis vingt mille places. Seule la première phase, couvrant la période 2024-2026, fait aujourd’hui l’objet de travaux ; les suivantes ne sont pas encore programmées dans le temps.'),

            P('Cette première phase ouvre quatre des six composantes prévues : l’unité de formation et de recherche en sciences biologiques, celle d’agro-industrie et sciences des techniques alimentaires, l’Institut des sciences vétérinaires et l’École de commerce et de gestion. Les unités de sciences et technologies et de médecine n’ouvriront qu’à la phase suivante. Le choix de ces filières n’est pas indifférent : il répond à la vocation agricole de la région du Denguélé.'),

            Legende('Tableau V.4 — Programmation des effectifs par composante et par phase', true),
            Tableau(PHASES_ENTETES, PHASES, [36, 16, 16, 16, 16]),
            Legende('Source : KOUASSI (2025)'),

            P('Ce phasage intéresse directement la validation. Le périmètre suivi dans l’application est celui du marché en cours, c’est-à-dire la première phase seule ; les indicateurs restitués mesurent donc l’avancement de cette phase et non celui de l’université dans son ensemble. La précision doit être faite, sous peine de laisser croire à une couverture qui n’est pas celle de l’outil.'),

            Titre('c) Intervenants et cadre contractuel', 3),

            P('La maîtrise d’ouvrage appartient au Ministère de l’Enseignement Supérieur et de la Recherche Scientifique, qui l’exerce par l’intermédiaire du Programme de décentralisation des universités. La maîtrise d’œuvre, chargée du contrôle et du suivi des travaux, est confiée à un groupement associant le bureau d’études koweïtien TAEP et le bureau d’études ivoirien IETF. L’exécution des travaux revient à l’entreprise PFO CONSTRUCTION.'),

            P('Cette répartition éclaire le circuit d’information que l’application vient outiller. L’avancement est relevé sur le chantier par l’entreprise, contrôlé et consolidé par le groupement chargé de l’assistance à maîtrise d’ouvrage, puis restitué au maître d’ouvrage sous la forme d’un rapport mensuel d’activité. C’est ce rapport qui alimente l’outil : la donnée saisie a donc déjà franchi un contrôle technique contradictoire avant d’entrer dans le système, ce qui déplace la garantie de fiabilité en amont de la saisie.'),

            P('Ce même rapport fournit la clé de pondération des ouvrages. Le poids physique de chaque bâtiment y est établi et maintenu d’un mois sur l’autre dans la base de calcul de l’avancement, document annexe du rapport. L’application reprend donc une pondération existante et validée par le contrôle, sans en construire une nouvelle.'),

            Legende('Tableau V.5 — Données contractuelles du marché de travaux', true),
            Tableau(CONTRAT_ENTETES, CONTRAT, [32, 68]),
            Legende('Source : marché de travaux et pièces annexes ; intervenants d’après KOUASSI (2025)'),

            Titre('d) Consistance des travaux', 3),

            P([
                T('La première phase comprend '),
                AConfirmer('trente-huit ouvrages'),
                T(' de nature très inégale : bâtiments d’enseignement et de recherche, locaux administratifs, ouvrages de voirie et réseaux divers, château d’eau et galerie piétonne. Cette hétérogénéité justifie la décomposition retenue dans l’application : chaque ouvrage y est enregistré séparément, reçoit une pondération représentative de son poids dans l’opération, et porte son propre calendrier contractuel. L’avancement global du projet résulte de la consolidation pondérée de ces avancements élémentaires.'),
            ]),

            P([
                T('La liste complète des ouvrages, leur pondération et le calendrier contractuel de chacun sont donnés en annexe '),
                AConfirmer('[ numéro d’annexe ]'),
                T('.'),
            ]),

            Titre('e) État d’avancement à la date d’arrêté', 3),

            P('Les données de validation sont arrêtées au 30 juin 2026, date du quatorzième rapport mensuel d’activité établi par l’assistance à maîtrise d’ouvrage. À cette date, le marché a consommé vingt de ses vingt-quatre mois de délai, soit 83,33 pour cent du temps contractuel, pour un avancement physique consolidé de 32,13 pour cent et un avancement financier des travaux de 27,73 pour cent. Le taux d’encaissement de l’entreprise, avances comprises, atteint 47,51 pour cent du montant du marché.'),

            P('L’écart au planning est considérable et documenté comme tel : la prévision contractuelle situait l’avancement à 93,21 pour cent à cette même date, soit un retard de 61,07 points. Lu horizontalement sur la courbe en S, ce même écart représente un décalage de neuf mois sur le calendrier initial. Une prolongation de huit mois a été soumise à l’instruction du bailleur à la suite de la réunion contradictoire des 12 et 13 mai 2026, qui porterait le délai d’exécution à trente-deux mois.'),

            Legende('Tableau V.6 — Situation du marché au 30 juin 2026', true),
            Tableau(
                ['Composante', 'Poids', 'Avancement'],
                [
                    ['Bâtiments', '66,19 %', '30,17 %'],
                    ['Voirie, réseaux divers, aménagements extérieurs et clôture', '22,87 %', '31,36 %'],
                    ['Mobiliers et équipements', '—', 'Non entamé'],
                    ['Efficacité énergétique', '—', 'Non entamé'],
                    ['Avancement physique consolidé', '—', '32,13 %'],
                ],
                [58, 21, 21],
            ),
            Legende('Source : rapport mensuel d’activité n° 14 (juin 2026), groupement TAEP/IETF'),

            P('Aucun ouvrage n’est achevé à la date d’arrêté. Les treize bâtiments recensés au rapport de suivi sont tous en cours d’exécution, dans une fourchette étroite comprise entre 17,62 pour cent pour l’amphithéâtre et 35,66 pour cent pour la présidence. Les ouvrages extérieurs sont plus avancés, la clôture atteignant 49,79 pour cent et les murs de soutènement 68,53 pour cent, tandis que les guérites restent à 4,62 pour cent. Deux composantes entières — les mobiliers et équipements d’une part, l’efficacité énergétique d’autre part — ne sont pas entamées.'),

            P('Parmi les quatre motifs de retard retenus par l’assistance à maîtrise d’ouvrage figure explicitement le non-respect des dates de démarrage : la majorité des bâtiments n’ont pas commencé aux dates prévues au planning, ce qui affecte directement le calendrier d’ensemble. Ce constat, formulé par le contrôle lui-même, justifie que l’application enregistre le calendrier contractuel de chaque ouvrage et mesure l’écart au démarrage, fonction dont la validation fait l’objet d’un scénario dédié.'),

            P('Ces valeurs constituent la situation de référence à partir de laquelle les essais du point V.1.3 sont conduits. Elles présentent en outre un intérêt méthodologique : un projet en dérive prononcée éprouve les indicateurs bien mieux qu’une opération conforme à sa prévision, où les écarts, tous proches de zéro, ne permettraient de déceler aucune erreur de calcul.'),

            P('Un dernier élément achève de justifier le choix de ce projet. Le diagnostic établi sur ce même chantier relevait déjà l’absence d’outil de suivi-évaluation à la disposition du maître d’ouvrage, dont l’information reposait pour l’essentiel sur des réunions bimestrielles tenues avec la maîtrise d’œuvre et l’entreprise (KOUASSI, 2025). Ce constat, formulé indépendamment du présent travail, recoupe le diagnostic conduit au chapitre II à l’échelle du programme. Le projet d’Odienné ne se contente donc pas d’offrir un terrain d’essai commode : il est l’une des situations qui ont rendu l’outil nécessaire.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(document).then((buffer) => {
    writeFileSync(chemin, buffer);
    console.log('Écrit : ' + chemin);
});
