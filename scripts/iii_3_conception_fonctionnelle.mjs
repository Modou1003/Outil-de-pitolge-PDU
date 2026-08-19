/**
 * III.3 — Conception fonctionnelle de l'application.
 *
 * Décrit les modules, les profils d'accès et les interfaces effectivement en
 * place, y compris les droits accordés plus largement qu'il ne serait souhaitable.
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
    margins: { top: 50, bottom: 50, left: 60, right: 60 },
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

const MODULES = [
    ['Tableau de bord', 'Agrégats du portefeuille, répartition par étape du cycle de vie, projets à surveiller', 'Direction et comité de pilotage'],
    ['Cartographie', 'Localisation des sites universitaires et état d’avancement de chacun', 'Tous profils'],
    ['Fiche projet', 'Sept onglets : informations générales, avancement physique, avancement financier, marché, planning, indicateurs, documents', 'Chef de projet et sections'],
    ['Saisie et import', 'Relevés mensuels par ouvrage, saisis un à un ou importés depuis le classeur de la mission de contrôle', 'Section opérations'],
    ['Module marché', 'Avenants en montant et en délai, dont se déduisent le marché et l’échéance actualisés', 'Section des marchés'],
    ['Module financier', 'Valeur acquise par ouvrage, décomptes, avances et récupérations', 'Section financière'],
    ['Alertes', 'Onze règles de détection, ouverture et clôture automatiques, commentaire et traitement', 'Tous profils habilités'],
    ['Rapports', 'Fiche d’avancement par projet et rapport de portefeuille, sections choisies au téléchargement', 'Direction et comité de pilotage'],
    ['Administration', 'Comptes et rôles, seuils de déclenchement, journal d’activité', 'Administrateur'],
];

const MATRICE = [
    ['Consulter le tableau de bord', '×', '×', '×', '×', '×', '×', '×'],
    ['Consulter une fiche projet', '×', '×', '×', '×', '×', '×', '×'],
    ['Consulter les rapports', '×', '×', '×', '×', '×', '×', '×'],
    ['Exporter un rapport', '×', '×', '×', '×', '—', '×', '—'],
    ['Créer un projet', '×', '×', '×', '×', '—', '—', '—'],
    ['Modifier un projet', '×', '×', '×', '×', '—', '—', '—'],
    ['Supprimer un projet', '×', '×', '×', '×', '—', '—', '—'],
    ['Saisir l’avancement physique', '×', '×', '×', '—', '—', '—', '—'],
    ['Saisir les décomptes et la valeur acquise', '×', '×', '—', '×', '—', '—', '—'],
    ['Instruire les avenants', '×', '×', '—', '×', '×', '—', '—'],
    ['Traiter les alertes', '×', '×', '×', '×', '—', '—', '—'],
    ['Gérer les comptes et les seuils', '×', '—', '—', '—', '—', '—', '—'],
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
            Titre('III.3. Conception fonctionnelle de l’application', 2),

            P('La structuration des données étant arrêtée, il reste à définir ce que l’application fait de ces données : quels modules elle offre, à qui, et sous quelle forme. Trois questions se posent dans cet ordre, car chacune commande la suivante.'),

            // ═════════════════════════════════════════ III.3.1
            Titre('III.3.1. Les modules fonctionnels retenus', 2),

            P('Les modules découlent directement des besoins recensés au chapitre II. Chacun répond à un usage identifié et s’adresse à un profil déterminé ; aucun n’a été retenu pour la seule raison qu’il était techniquement réalisable.'),

            Legende('Tableau 1 — Modules fonctionnels et destinataires', true),
            Tableau(['Module', 'Contenu', 'Destinataire principal'], MODULES, [20, 55, 25]),
            Legende('Source : élaboration personnelle'),

            Titre('a) L’organisation de la fiche projet', 3),

            P('La fiche projet constitue le cœur de l’application. Elle est organisée en sept onglets qui suivent la logique du travail de suivi plutôt que celle du modèle de données : on y entre par ce que l’on veut faire, non par la table que l’on veut renseigner.'),

            P('Les trois premiers onglets répondent aux trois questions du suivi. Les informations générales situent le projet ; l’avancement physique dit ce qui est construit ; l’avancement financier dit ce qui est payé. Le module marché rassemble ensuite les actes contractuels — les avenants — dont se déduisent le montant et l’échéance de référence. Le planning porte les lots et les jalons de chaque ouvrage. Les indicateurs restituent les grandeurs calculées. Les documents, enfin, rattachent les pièces au projet.'),

            P('Ce découpage reproduit celui de l’unité de gestion, et ce n’est pas un hasard : un agent de la section financière n’a que faire des lots de planning, et un chef de projet n’instruit pas les avenants. L’onglet est ainsi devenu l’unité d’habilitation naturelle.'),

            Titre('b) Le choix de l’import plutôt que de la saisie', 3),

            P('Le module de saisie a connu une évolution qui mérite d’être rapportée, car elle a modifié la conception d’ensemble. Il était initialement prévu que chaque section saisisse ses données dans les écrans correspondants. L’examen des documents réellement produits sur le chantier a conduit à un choix différent : la mission de contrôle établit déjà, chaque mois, un classeur qui contient l’avancement de chaque ouvrage, sa pondération, son calendrier et la facturation associée.'),

            P('Plutôt que de retranscrire ce document, l’application l’importe. Une seule opération alimente alors l’avancement physique, la valeur acquise, les décomptes, les calendriers d’ouvrages, les indicateurs et les alertes. La saisie manuelle demeure — elle reste nécessaire pour corriger une valeur ou renseigner un projet dépourvu de classeur — mais elle n’est plus le mode d’alimentation ordinaire.'),

            P('Ce choix a une conséquence de conception : l’import ne remplace jamais, il complète. Seules les périodes absentes sont ajoutées, les corrections apportées à la main survivent à un import ultérieur, et le même fichier déposé deux fois ne produit aucun doublon. Un écran de contrôle présente en outre, avant toute écriture, ce que le fichier contient et ce que l’application n’a pas su y reconnaître.'),

            Titre('c) Le module d’alertes', 3),

            P('Le module d’alertes se distingue des autres en ce qu’il ne se consulte pas : il se manifeste. Onze règles de détection sont réévaluées à chaque saisie et à chaque import ; les alertes qu’elles produisent s’ouvrent et se referment d’elles-mêmes, sans intervention. L’agent n’a pas à interroger l’application pour savoir si quelque chose ne va pas.'),

            P('Cette conception répond à un dysfonctionnement précis du diagnostic : l’information sur les dérives existait, mais il fallait la chercher. Un dispositif d’alerte transforme une information disponible en une information qui se signale.'),

            // ═════════════════════════════════════════ III.3.2
            Titre('III.3.2. La gestion des profils d’accès', 2),

            P('Le contrôle des accès repose sur le modèle fondé sur les rôles : un agent reçoit un rôle, le rôle porte des permissions, et chaque opération vérifie la permission correspondante. Treize permissions ont été définies, réparties entre les rôles selon la répartition des compétences observée à l’unité de gestion.'),

            Titre('a) Le principe directeur : reproduire la séparation des compétences', 3),

            P('Le principe retenu n’est pas hiérarchique mais fonctionnel. Un rôle ne donne pas accès à davantage de choses parce que son titulaire occupe une position plus élevée : il donne accès aux opérations qui relèvent de sa compétence. La section des marchés instruit ainsi les avenants sans accéder aux décomptes, et la section financière l’inverse. Cette séparation est celle de l’unité de gestion ; l’application ne fait que la reproduire.'),

            P('Elle vaut jusque dans les mécanismes automatiques. Lors de l’import du classeur mensuel, le coût réel n’est inscrit que si l’agent qui dépose le fichier a la charge des finances ; à défaut, la valeur acquise est alimentée et le coût laissé vide, qu’un import ultérieur mené par la section compétente viendra compléter. Sans cette précaution, la facturation entrerait en base par un chemin détourné.'),

            Legende('Tableau 2 — Matrice des droits par profil', true),
            Tableau(
                ['Opération', 'Admin.', 'Direct.', 'Chef proj.', 'Agent fin.', 'Ch. marchés', 'COPIL', 'Visiteur'],
                MATRICE, [30, 10, 10, 10, 10, 10, 10, 10], [1, 2, 3, 4, 5, 6, 7],
            ),
            Legende('Source : élaboration personnelle'),

            Titre('b) Les profils de consultation', 3),

            P('Aux rôles ci-dessus s’ajoutent des profils de consultation correspondant aux intervenants du génie civil : maîtrise d’ouvrage déléguée, maîtrise d’œuvre, entreprise générale, sous-traitant, bureau de contrôle, laboratoire d’essais, ordonnancement-pilotage-coordination, et les différentes spécialités d’ingénierie. Tous portent les mêmes trois droits de consultation.'),

            P('Cette distinction n’a donc aucun effet sur les autorisations, et il faut le dire plutôt que de le laisser croire : elle qualifie la fonction de la personne, non ses droits. Son utilité est documentaire — savoir à quel titre un compte a été ouvert — et elle prépare une différenciation ultérieure des accès si le besoin s’en fait sentir.'),

            Titre('c) Le contrôle est exercé côté serveur', 3),

            P('Un point de conception mérite d’être souligné, car il distingue une protection réelle d’une apparence de protection. Les boutons et onglets auxquels un profil n’a pas droit ne lui sont pas affichés, mais cette dissimulation n’est qu’un confort de lecture. Le contrôle véritable s’exerce au serveur : toute opération vérifie la permission avant d’écrire, y compris lorsque la requête est adressée directement à l’adresse de la fonction, sans passer par l’interface. Un utilisateur averti qui contournerait l’affichage se heurterait au même refus.'),

            P('S’y ajoute une vérification préalable à toute autre : un compte désactivé est écarté dès l’authentification, sans que ses rôles soient même examinés.'),

            Titre('d) Limites de la matrice actuelle', 3),

            P('Deux points doivent être signalés. Le premier est que la suppression d’un projet est accordée au chef de projet comme à l’agent financier, alors qu’elle relève plutôt de la direction. La suppression étant réversible — le projet est marqué et non effacé — la conséquence reste limitée, mais le droit est plus large qu’il ne devrait.'),

            P('Le second est qu’une permission subsiste dans la matrice sans commander aucune opération : elle est attribuée aux rôles sans que rien ne la vérifie. Ce vestige d’une version antérieure devrait être retiré, une permission sans effet donnant une fausse idée de la granularité réelle du contrôle.'),

            // ═════════════════════════════════════════ III.3.3
            Titre('III.3.3. Les maquettes des interfaces principales', 2),

            P('Trois écrans concentrent l’essentiel de l’usage : le tableau de bord, la fiche projet et l’écran de saisie. Leur conception obéit à trois règles communes.'),

            Puce('Le chiffre avant le graphique. Un responsable cherche d’abord une valeur ; la courbe vient ensuite, pour situer cette valeur dans une tendance.'),
            Puce('La couleur ne porte jamais seule l’information. Chaque carte colorée porte également un libellé de niveau, afin de rester lisible en noir et blanc comme par un lecteur atteint de dyschromatopsie.'),
            Puce('L’absence de valeur se distingue de la valeur nulle. Un tiret signale un indicateur non calculable et désigne la donnée manquante ; un zéro est une mesure.'),

            P('Le tableau de bord présente les agrégats du portefeuille, la répartition des projets par étape du cycle de vie et la localisation des sites. Il répond à la demande initiale du coordonnateur : disposer, en une vue, de l’état d’ensemble du programme.'),

            P('La fiche projet ouvre sur une rangée de cartes d’indicateurs, suivie des onglets thématiques. Cette disposition est délibérée : le lecteur voit l’état du projet avant d’avoir à choisir un onglet, et n’entre dans le détail que s’il en a besoin.'),

            P('L’écran de saisie, enfin, a été conçu pour l’usage réel plutôt que pour la démonstration. La saisie se fait ouvrage par ouvrage, l’écran conservant la valeur du mois précédent afin que l’agent constate l’écart au moment où il le renseigne. L’import, quant à lui, ne s’exécute jamais directement : il présente d’abord ce qu’il a compris du fichier, et n’écrit qu’après confirmation.'),

            Figure('[ FIGURE à insérer — Maquette du tableau de bord ]'),
            Legende('Figure III.x — Tableau de bord du portefeuille'),
            Legende('Source : élaboration personnelle'),

            Figure('[ FIGURE à insérer — Maquette de la fiche projet ]'),
            Legende('Figure III.x — Fiche projet et cartes d’indicateurs'),
            Legende('Source : élaboration personnelle'),

            Figure('[ FIGURE à insérer — Maquette de l’écran de saisie et de l’écran de contrôle avant import ]'),
            Legende('Figure III.x — Saisie de l’avancement et contrôle préalable à l’import'),
            Legende('Source : élaboration personnelle'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
