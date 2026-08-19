/**
 * III.2.3 — Organisation de la base de données centrale.
 *
 * Décrit le schéma effectivement en place, y compris ses vestiges.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (t, o = {}) => new TextRun({ text: t, font: POLICE, size: 24, ...o });

const P = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 110, line: 264 },
});

const F = (t) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 24, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: { before: 100, after: 130 },
});

const Titre = (t, n) => new Paragraph({
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

const Legende = (t, avant = false) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: avant ? { before: 170, after: 90 } : { before: 70, after: 170 },
});

const Figure = (t) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true, highlight: 'yellow' })],
    alignment: AlignmentType.CENTER,
    spacing: { before: 200, after: 60 },
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

const TABLES = [
    ['universities', 'Universités de rattachement : dénomination, localisation, coordonnées géographiques', 'Alimente la cartographie du portefeuille'],
    ['pdu_projects', 'Projets : code, intitulé, dates contractuelles, montant du marché, avances, statut, responsables', 'Table pivot de tout le modèle'],
    ['building_works', 'Ouvrages : pondération, statut, durée et quatre dates du calendrier contractuel', 'Maille de saisie et de restitution'],
    ['project_lots', 'Lots de planning rattachés à un ouvrage', 'Construction du diagramme de Gantt'],
    ['project_milestones', 'Jalons contractuels : date prévue, date atteinte, criticité', 'Règle d’alerte « jalon manqué »'],
    ['physical_progresses', 'Relevés mensuels d’avancement : taux planifié et taux constaté, par ouvrage et par période', 'Source de l’avancement consolidé'],
    ['financial_progresses', 'Valeur planifiée, valeur acquise et coût réel, par ouvrage et par période, avec leurs cumuls', 'Source des indices de performance'],
    ['project_payments', 'Décomptes : montant brut, récupérations d’avance, net payé, état du règlement', 'Suivi de la facturation et de l’exposition'],
    ['project_amendments', 'Avenants approuvés : objet, montant, prolongation de délai', 'Détermine le marché et l’échéance actualisés'],
    ['alerts, alert_comments', 'Anomalies détectées, leur gravité, leur contexte et leur traitement', 'Module d’alertes'],
    ['settings', 'Seuils de déclenchement paramétrables', 'Écran d’administration des seuils'],
    ['activity_logs', 'Journal des écritures : auteur, action, objet, horodatage', 'Traçabilité'],
    ['users, roles, permissions', 'Comptes, rôles et droits, selon le modèle de contrôle d’accès fondé sur les rôles', 'Séparation des compétences'],
    ['indicators, indicator_trackings', 'Indicateurs de suivi définis librement et leurs relevés successifs', 'Suivi d’objectifs propres au programme'],
];

const REGLES = [
    ['Unicité du relevé', 'Un seul relevé d’avancement par ouvrage et par période mensuelle', 'Empêche qu’un même mois soit compté deux fois dans les cumuls'],
    ['Bornes des taux', 'Les taux planifiés et constatés sont compris entre zéro et cent', 'Écarte les saisies aberrantes'],
    ['Somme des pondérations', 'La somme des pondérations des ouvrages d’un projet ne peut excéder cent pour cent', 'Garantit que l’avancement consolidé reste interprétable'],
    ['Appartenance', 'Un relevé ne peut être rattaché qu’à un ouvrage du projet concerné', 'Interdit les rattachements croisés entre projets'],
    ['Cohérence des dates', 'Une date de fin ne peut précéder la date de début correspondante', 'Vaut pour le calendrier contractuel comme pour les lots'],
    ['Suppression en cascade', 'La suppression d’un ouvrage entraîne celle de ses relevés, lots et jalons', 'Évite les enregistrements orphelins'],
    ['Suppression réversible', 'Projets et ouvrages sont marqués comme supprimés sans être effacés', 'Permet le rétablissement et conserve l’historique'],
    ['Conservation de l’auteur', 'La suppression d’un compte laisse subsister les écritures qu’il a produites', 'La donnée survit à son auteur ; le lien est simplement dénoué'],
];

const DERIVEES = [
    ['Marché actualisé', 'montant initial + Σ des avenants approuvés'],
    ['Échéance actualisée', 'date de fin initiale + Σ des prolongations accordées'],
    ['Avancement consolidé', 'moyenne des avancements d’ouvrages, pondérée par leur poids'],
    ['Retard au démarrage', 'début réel − début prévu, borné à zéro, l’ouvrage non démarré étant comparé à la date du jour'],
    ['Indices de performance', 'rapports des cumuls de valeur acquise à ceux de valeur planifiée et de coût réel'],
    ['Reste à facturer, encaissé, exposition', 'combinaisons du marché actualisé, des décomptes et des avances'],
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
            Titre('III.2.3. Organisation de la base de données centrale : relations et règles de gestion', 2),

            P('La base de données constitue le point unique où réside l’information de suivi. Son organisation détermine ce que l’application peut restituer et ce qu’elle ne pourra jamais produire ; elle mérite donc d’être exposée avant les écrans qui s’y adossent.'),

            Titre('1. Trois principes d’organisation', 2),

            P('Le schéma obéit à trois principes, dont découlent la plupart des choix de détail.'),

            Puce('L’ouvrage est la maille de référence. Ni le projet, trop grossier pour désigner une cause, ni le lot, trop fin pour être renseigné mensuellement : c’est l’ouvrage qui porte la pondération, le calendrier contractuel, les relevés d’avancement et la valeur acquise. Cette maille est aussi celle du document de la mission de contrôle, ce qui permet la comparaison directe.'),
            Puce('Rien de ce qui se déduit n’est saisi. Toute valeur calculable à partir d’une autre est dérivée et non stockée, de sorte qu’aucune divergence ne peut s’installer entre une donnée et son résumé.'),
            Puce('Toute écriture est attribuée. Chaque enregistrement porte son auteur et son horodatage, la traçabilité étant la contrepartie de l’absence de circuit d’approbation formel.'),

            Titre('2. Le noyau du modèle', 2),

            P('Cinq tables forment l’ossature, reliées en cascade depuis l’université jusqu’au relevé mensuel.'),

            F('université → projet → ouvrage → relevé mensuel (physique et financier)'),

            P('Une université porte plusieurs projets ; un projet se décompose en ouvrages ; chaque ouvrage reçoit, mois après mois, un relevé d’avancement physique et un relevé de valeur acquise. Deux tables se rattachent en outre directement au projet, parce qu’elles relèvent du marché et non d’un ouvrage particulier : les décomptes et les avenants.'),

            P('Les lots et les jalons se greffent sur l’ouvrage. Les premiers construisent son diagramme de Gantt, les seconds jalonnent son exécution contractuelle. Ni les uns ni les autres ne portent de relevé d’avancement : cette responsabilité appartient à l’ouvrage seul.'),

            Figure('[ FIGURE à insérer — Diagramme de classes de l’application ]'),
            Legende('Figure III.x — Modèle de données de l’application'),
            Legende('Source : élaboration personnelle'),

            Titre('3. Inventaire des tables métier', 2),

            P('Aux tables techniques nécessaires au fonctionnement du cadriciel — sessions, files d’attente, cache, jetons — s’ajoutent les tables métier suivantes.'),

            Legende('Tableau 1 — Tables métier et leur rôle', true),
            Tableau(['Table', 'Contenu', 'Usage principal'], TABLES, [22, 48, 30]),
            Legende('Source : élaboration personnelle'),

            Titre('4. Les règles de gestion', 2),

            P('Les règles ci-après sont appliquées à l’écriture. Elles ne relèvent pas du confort d’interface : une saisie qui les enfreint est refusée par le serveur, y compris lorsqu’elle est adressée directement à l’adresse de la fonction sans passer par l’écran.'),

            Legende('Tableau 2 — Règles de gestion appliquées à l’écriture', true),
            Tableau(['Règle', 'Énoncé', 'Motif'], REGLES, [22, 40, 38]),
            Legende('Source : élaboration personnelle'),

            P('Deux d’entre elles appellent un commentaire. La suppression réversible des projets et des ouvrages répond à une exigence de sécurité autant que de commodité : une suppression accidentelle ne détruit rien, l’enregistrement étant seulement marqué et retiré des listes. La conservation des écritures après suppression d’un compte procède du même souci : le départ d’un agent ne doit pas effacer les relevés qu’il a produits, faute de quoi l’historique du chantier dépendrait de la carrière de ceux qui l’ont suivi.'),

            P('Une précision de mise en œuvre doit être apportée. L’unicité du relevé par ouvrage et par période est contrôlée par l’application avant toute écriture, et non par une contrainte portée par la base elle-même. Ce choix, dicté par la nécessité de renvoyer un message explicite à l’utilisateur plutôt qu’une erreur technique, a pour contrepartie qu’une écriture pratiquée hors de l’application échapperait au contrôle. L’ajout d’une contrainte d’unicité au niveau de la base constituerait une ceinture de sécurité utile.'),

            Titre('5. Les valeurs jamais stockées', 2),

            P('Le deuxième principe d’organisation mérite d’être détaillé, car il explique pourquoi certaines grandeurs pourtant centrales sont absentes du schéma.'),

            Legende('Tableau 3 — Grandeurs dérivées à la lecture', true),
            Tableau(['Grandeur', 'Détermination'], DERIVEES, [34, 66]),
            Legende('Source : élaboration personnelle'),

            P('Le marché actualisé illustre bien l’intérêt du procédé. En le déduisant du montant initial et des avenants plutôt qu’en le stockant, on garantit qu’il ne peut jamais s’écarter des pièces contractuelles : l’enregistrement d’un avenant met à jour, du même geste, le montant de référence, les taux qui s’y rapportent et le coût final estimé. Aucune reprise manuelle n’est nécessaire, et aucune n’est possible.'),

            P('Deux exceptions ont été consenties, pour des raisons de performance. L’avancement consolidé du projet et les cumuls de la valeur acquise sont enregistrés en même temps qu’ils sont calculés, afin que le tableau de bord du portefeuille n’ait pas à recalculer cinquante projets à chaque affichage. Ces valeurs ne sont jamais saisies : elles sont recalculées à chaque écriture par le service d’agrégation, et une commande permet de les reconstruire intégralement en cas de doute.'),

            Titre('6. Traçabilité et contrôle des accès', 2),

            P('Chaque relevé porte l’identifiant de l’agent qui l’a enregistré et la date de l’enregistrement, y compris lorsqu’il provient de l’import d’un classeur : l’import est attribué à celui qui le déclenche, et non au créateur du projet. Le journal d’activité conserve en outre, indépendamment des tables métier, la trace de chaque création, modification et suppression, avec son auteur et son horodatage.'),

            P('Les droits d’écriture reposent sur un modèle de contrôle d’accès fondé sur les rôles : un agent reçoit un rôle, le rôle porte des permissions, et chaque opération vérifie la permission correspondante. La séparation des compétences de l’unité de gestion s’y trouve reproduite — la section des marchés instruit les avenants sans accéder aux décomptes, la section financière l’inverse — et cette séparation vaut jusque dans l’import, où le coût réel n’est inscrit que par qui a la charge des finances.'),

            Titre('7. Limites du schéma actuel', 2),

            P('Quatre points doivent être signalés, moins comme des défauts que comme l’état d’un travail en cours.'),

            P('Le code d’un ouvrage est unique sur l’ensemble de la base et non à l’intérieur de son projet. Deux projets ne peuvent donc pas nommer chacun leur premier ouvrage de la même manière, ce qui oblige à préfixer les codes par un trigramme de projet. Une unicité restreinte au projet serait plus naturelle.'),

            P('Le rattachement des relevés au lot subsiste dans le schéma, vestige d’une organisation antérieure où le lot était la maille de saisie. La colonne demeure, désormais inutilisée, et devrait être retirée pour éviter qu’un développeur ultérieur ne la croie signifiante.'),

            P('Une table d’audit distincte du journal d’activité subsiste également sans être alimentée. Deux dispositifs de traçabilité coexistent ainsi dans le schéma, dont un seul fonctionne ; la suppression du second lèverait l’ambiguïté.'),

            P('Le projet ne porte enfin qu’une enveloppe unique. Le cofinancement, qui associe un prêt concessionnel et une contrepartie de l’État, ne peut donc pas être suivi par source. Cette limite tient au schéma et non à l’interface : la lever suppose d’introduire une table des conventions de financement et de rattacher chaque décompte à l’une d’elles.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
