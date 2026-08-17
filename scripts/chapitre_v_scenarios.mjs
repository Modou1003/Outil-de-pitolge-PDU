/**
 * Chapitre V — V.1.2 Scénarios de validation, version corrigée.
 *
 * Chaque scénario a été confronté au code : ne subsistent que des essais que
 * l'application peut réellement conduire, et dont le résultat attendu
 * correspond au comportement effectivement programmé.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (texte, opts = {}) => new TextRun({ text: texte, font: POLICE, size: 24, ...opts });

const P = (texte) => new Paragraph({
    children: Array.isArray(texte) ? texte : [T(texte)],
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 120, line: 276 },
});

const Titre = (texte, niveau) => new Paragraph({
    children: [new TextRun({ text: texte, font: POLICE, size: niveau === 1 ? 28 : 26, bold: true })],
    heading: niveau === 1 ? HeadingLevel.HEADING_1 : HeadingLevel.HEADING_2,
    spacing: { before: 240, after: 160 },
});

const Legende = (texte, avant = false) => new Paragraph({
    children: [new TextRun({ text: texte, font: POLICE, size: 20, italics: true })],
    spacing: avant ? { before: 200, after: 100 } : { before: 80, after: 200 },
});

const BORDURE = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDURES = { top: BORDURE, bottom: BORDURE, left: BORDURE, right: BORDURE };

const Cellule = (texte, { entete = false, largeur, centre = false } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDURES,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 60, bottom: 60, left: 80, right: 80 },
    children: [new Paragraph({
        children: [new TextRun({ text: texte, font: POLICE, size: 20, bold: entete })],
        alignment: (entete || centre) ? AlignmentType.CENTER : AlignmentType.LEFT,
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
            children: ligne.map((c, i) => Cellule(c, { largeur: largeurs[i], centre: i === 0 })),
        })),
    ],
});

// ─────────────────────────────────────────────────────────── Tableau V.3

const ENTETES = ['N°', 'Scénario', 'Mode opératoire', 'Résultat attendu'];

const SCENARIOS = [
    [
        '1',
        'Création du projet et de son marché',
        'Enregistrement de la fiche projet, de l’université de rattachement, de la localisation et du marché de travaux avec son montant initial et son délai',
        'Le projet apparaît au portefeuille et sur la carte ; le montant contractuel de référence est correctement constitué',
    ],
    [
        '2',
        'Décomposition en ouvrages pondérés',
        'Saisie des ouvrages, de leur calendrier contractuel et de leur poids relatif, puis tentative d’attribution d’un poids portant le total au-delà de cent pour cent',
        'La saisie excédentaire est refusée par le serveur avec indication du solde disponible ; tant que le total reste inférieur à cent pour cent, un avertissement signale que l’avancement pondéré n’est pas encore représentatif',
    ],
    [
        '3',
        'Saisie d’un relevé d’avancement par l’agent chargé du suivi',
        'Connexion sous un profil habilité à la saisie physique, puis report, pour une période mensuelle, du taux planifié et du taux réalisé de chaque ouvrage tels qu’ils figurent au rapport de l’entreprise exécutante',
        'Le relevé est enregistré, attribué nominativement à son auteur, inscrit au journal d’activité, et alimente immédiatement les indicateurs du projet',
    ],
    [
        '4',
        'Rejet des saisies non conformes',
        'Tentatives d’enregistrement d’un taux hors de l’intervalle de zéro à cent, d’un second relevé pour une période et un ouvrage déjà renseignés, et d’un relevé rattaché à un ouvrage étranger au projet',
        'Les trois tentatives sont refusées avec un message explicite ; aucune donnée irrégulière n’est enregistrée',
    ],
    [
        '5',
        'Traçabilité des écritures',
        'Consultation du journal d’activité depuis la section d’administration, après une série de créations, de modifications et de suppressions opérées sous des profils différents',
        'Chaque opération y figure avec son auteur, son horodatage et l’objet concerné ; le journal n’est accessible qu’au profil administrateur',
    ],
    [
        '6',
        'Calcul des indicateurs de performance',
        'Consultation de la fiche projet après enregistrement de la série mensuelle des relevés physiques et financiers',
        'Les indicateurs restitués — avancement pondéré, valeur planifiée, valeur acquise, coût réel, indices de performance des délais et des coûts, valeur acquise temporelle et fin projetée — coïncident avec les valeurs calculées manuellement (tableau V.5)',
    ],
    [
        '7',
        'Détection d’un retard au démarrage',
        'Saisie du calendrier contractuel d’un ouvrage dont la date de début prévue est dépassée sans démarrage effectif, puis enregistrement ultérieur de la date de début réelle',
        'Le retard est affiché dans la fiche de l’ouvrage et l’alerte se déclenche au-delà de la tolérance paramétrée ; l’enregistrement du démarrage effectif clôt l’alerte',
    ],
    [
        '8',
        'Déclenchement des alertes',
        'Provocation délibérée des conditions de chacune des onze règles de détection, puis rétablissement de la situation',
        'L’alerte attendue apparaît, n’est pas dupliquée, voit sa gravité ajustée selon l’ampleur de l’écart, et se clôt automatiquement au rétablissement',
    ],
    [
        '9',
        'Contrôle des droits d’accès',
        'Tentatives d’accès et d’écriture non autorisées sous chacun des profils, y compris par saisie directe de l’adresse de la fonction, puis tentative de connexion au moyen d’un compte désactivé',
        'Toutes les tentatives sont refusées côté serveur et non par simple masquage de l’interface ; le compte désactivé est écarté dès l’authentification',
    ],
    [
        '10',
        'Génération et export d’un rapport',
        'Production de la fiche d’avancement du projet au format de document portable, en faisant varier les sections retenues au moment du téléchargement',
        'Les valeurs du rapport coïncident avec celles du tableau de bord ; seules les sections retenues figurent au document produit',
    ],
    [
        '11',
        'Consolidation du portefeuille',
        'Consultation du tableau de bord après enregistrement de l’ensemble des projets',
        'Les agrégats et la répartition par étape du cycle correspondent à la somme des situations individuelles',
    ],
];

// ─────────────────────────────────────────────────────────── Document

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
            Titre('V.1.2. Scénarios de validation', 2),

            P('Chaque scénario reproduit une situation réelle d’utilisation et se conclut par un résultat attendu, défini avant l’essai. Les scénarios couvrent l’ensemble de la chaîne, de la saisie à la restitution, ainsi que les contrôles qui doivent empêcher une opération irrégulière.'),

            Legende('Tableau V.3 — Scénarios de validation', true),
            Tableau(ENTETES, SCENARIOS, [5, 21, 37, 37]),
            Legende('Source : élaboration personnelle'),

            P('Deux précisions s’imposent sur la portée de ces scénarios.'),

            P('La première concerne le statut de la donnée saisie. L’application attribue chaque relevé à son auteur et en conserve la trace au journal d’activité, mais elle ne fait pas transiter la saisie par un circuit d’approbation formel : un relevé alimente les indicateurs dès son enregistrement. Le contrôle repose donc sur l’habilitation préalable de l’agent et sur la traçabilité a posteriori, non sur une validation hiérarchique intégrée à l’outil. La donnée reste néanmoins issue d’un document déjà visé, le rapport mensuel de l’entreprise exécutante, ce qui déplace le contrôle en amont de la saisie plutôt qu’en aval. L’introduction d’un circuit d’approbation figure parmi les perspectives d’évolution exposées au point V.3.3.'),

            P('La seconde concerne la pondération des ouvrages. Le contrôle programmé interdit de dépasser cent pour cent, mais n’impose pas d’atteindre ce total : un portefeuille d’ouvrages incomplètement pondéré demeure enregistrable, l’application se bornant à signaler que l’avancement consolidé n’est alors pas représentatif. Ce choix se justifie par la progressivité de la saisie, les ouvrages étant renseignés les uns après les autres, mais il suppose que l’essai vérifie l’atteinte effective des cent pour cent avant toute lecture des indicateurs.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(document).then((buffer) => {
    writeFileSync(chemin, buffer);
    console.log('Écrit : ' + chemin);
});
