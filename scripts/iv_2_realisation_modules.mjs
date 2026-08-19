/**
 * IV.2 — Réalisation des modules de l'application.
 *
 * Décrit ce qui a été effectivement construit, les choix de mise en œuvre et
 * les difficultés rencontrées.
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

const VOLUMETRIE = [
    ['Modèles de données', '22', 'Une classe par entité métier'],
    ['Contrôleurs', '22', 'Un par domaine fonctionnel'],
    ['Services de calcul', '8', 'Agrégation, valeur acquise temporelle, alertes, seuils, journal, import'],
    ['Migrations de base', '48', 'Chaque évolution du schéma est versionnée et réversible'],
    ['Composants d’interface', '66', 'Écrans, onglets, modales et cartes d’indicateurs'],
    ['Jeux d’essais automatisés', '15', 'Environ 130 cas de test'],
];

const SERVICES = [
    ['Service d’agrégation',
        'Recalcule l’avancement consolidé du projet, les cumuls de la valeur acquise et le montant décaissé',
        'Appelé après chaque écriture, quelle qu’en soit l’origine'],
    ['Service de valeur acquise temporelle',
        'Situe le temps acquis sur la courbe planifiée, en déduit l’indice de performance des délais et la date d’achèvement projetée',
        'Interrogé à l’affichage de la fiche projet et du rapport'],
    ['Service d’alertes',
        'Évalue les onze règles de détection, ouvre, ajuste ou referme les alertes',
        'Déclenché après chaque saisie et chaque import'],
    ['Service des seuils',
        'Charge les seize seuils paramétrables, avec repli sur les valeurs par défaut',
        'Consulté par le service d’alertes et par les cartes d’indicateurs'],
    ['Service des indicateurs',
        'Calcule l’écart physico-financier et la fraîcheur de la donnée',
        'Partagé par l’écran et par le rapport, afin qu’ils ne divergent pas'],
    ['Lecteur et importateur de classeur',
        'Transposent la base de calcul de la mission de contrôle, puis l’écrivent sans doublon',
        'Utilisés par l’écran d’import et par le chargement initial d’un projet'],
    ['Journal d’activité',
        'Consigne l’auteur, l’action et l’objet de chaque écriture',
        'Absorbe ses propres erreurs, afin qu’un défaut de journalisation n’interrompe jamais une opération métier'],
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
            Titre('IV.2. Réalisation des modules de l’application', 2),

            P('La conception arrêtée au chapitre précédent a été traduite en une application fonctionnelle, mise en ligne et alimentée par les données réelles d’un chantier. Le présent point rend compte de cette réalisation : ce qui a été construit, comment, et ce que la construction a révélé que la conception n’avait pas prévu.'),

            Titre('IV.2.1. L’organisation du code', 2),

            P('Le développement représente environ dix mille sept cents lignes de code applicatif côté serveur, neuf mille quatre cents lignes d’interface et deux mille six cents lignes de jeux d’essais. Cette dernière proportion mérite d’être relevée : un quart de l’effort porte sur la vérification automatique du comportement, ce qui n’est pas excessif pour un outil dont la crédibilité repose entièrement sur l’exactitude de ses calculs.'),

            Legende('Tableau 1 — Volumétrie du développement', true),
            Tableau(['Élément', 'Nombre', 'Observation'], VOLUMETRIE, [30, 14, 56], [1]),
            Legende('Source : élaboration personnelle'),

            Titre('a) Le principe de séparation retenu', 3),

            P('Un choix d’organisation domine tout le développement : aucun calcul d’indicateur ne réside dans un écran ni dans un contrôleur. Les règles de calcul sont rassemblées dans des services autonomes, que l’écran, le rapport et le module d’alertes interrogent indifféremment.'),

            P('Cette séparation n’est pas une élégance d’architecture. Elle répond à un risque précis : si l’avancement consolidé était calculé une fois pour l’écran et une autre fois pour le rapport, rien ne garantirait que les deux versions restent identiques au fil des modifications. En les faisant procéder du même service, on rend la divergence matériellement impossible. C’est ce qui fonde la propriété énoncée au chapitre III — l’écran, le rapport exporté et les alertes ne peuvent pas afficher trois valeurs différentes.'),

            Legende('Tableau 2 — Services de calcul et leur rôle', true),
            Tableau(['Service', 'Responsabilité', 'Sollicitation'], SERVICES, [24, 46, 30]),
            Legende('Source : élaboration personnelle'),

            Titre('IV.2.2. Le module de suivi de l’avancement physique', 2),

            P('Ce module est le premier à avoir été réalisé, et celui qui a le plus évolué. Dans sa forme initiale, il se réduisait à un formulaire de saisie mensuelle par ouvrage. Trois ajouts successifs l’ont transformé.'),

            P('Le premier a été la pondération des ouvrages, sans laquelle l’avancement consolidé n’aurait été qu’une moyenne arithmétique dépourvue de sens sur un chantier où un bâtiment pèse vingt et un pour cent et un autre un pour cent. Un contrôle serveur interdit à la somme des pondérations de dépasser cent, en indiquant le solde disponible plutôt qu’un refus sec.'),

            P('Le deuxième a été le calendrier contractuel de l’ouvrage, ajouté après un constat de terrain rapporté au chapitre V : plusieurs ouvrages avaient démarré des mois après la date prévue au planning, sans que l’application permît de le savoir. Cinq colonnes ont été ajoutées à la table des ouvrages et deux grandeurs dérivées calculées à la lecture, le retard au démarrage et l’état de non-démarrage.'),

            P('Le troisième, et le plus important, a été l’import du classeur mensuel de la mission de contrôle, exposé au point suivant.'),

            Figure('[ FIGURE à insérer — Capture d’écran de la saisie de l’avancement physique par ouvrage ]'),
            Legende('Figure IV.x — Saisie de l’avancement physique'),
            Legende('Source : application développée'),

            Titre('IV.2.3. Le module d’import de la base de calcul', 2),

            P('Ce module n’était pas prévu au cahier des charges initial. Il est né de l’examen des documents réellement produits sur le chantier : la mission de contrôle établit chaque mois un classeur contenant, pour chaque ouvrage, sa pondération, son avancement planifié et constaté, son calendrier et sa facturation. Retranscrire ce document à la main aurait été absurde autant que risqué.'),

            Titre('a) Un import en deux temps', 3),

            P('L’opération se déroule en deux étapes distinctes, séparation qui constitue le choix de conception essentiel du module. La lecture transpose le contenu du classeur sans rien écrire, et rend compte de ce qu’elle a reconnu : ouvrages, pondérations, périodes nouvelles, décomptes, ainsi que ce qu’elle n’a pas su rattacher. L’écriture n’intervient qu’après confirmation explicite.'),

            P('Cette séparation répond à la fragilité inhérente du procédé. La lecture repose sur la structure d’un document dont l’application n’est pas l’auteur ; une refonte du classeur interromprait la reconnaissance. L’écran de contrôle transforme alors une rupture silencieuse — des données fausses entrant en base sans que nul ne s’en aperçoive — en une rupture visible : l’utilisateur constate qu’aucun ouvrage n’a été reconnu et appelle à l’aide.'),

            Titre('b) Un import qui complète et ne remplace jamais', 3),

            P('Trois règles gouvernent l’écriture, et chacune a fait l’objet d’un essai automatisé.'),

            Puce('Seules les périodes absentes de la base donnent lieu à un relevé ; les mois déjà saisis ne sont pas retouchés.'),
            Puce('Une correction apportée à la main survit à tout import ultérieur, ce qui importe lorsqu’une valeur a été rectifiée à la suite d’une réunion contradictoire.'),
            Puce('Le même fichier déposé deux fois ne produit aucun doublon, tandis qu’un décompte passé de l’état déposé à l’état réglé voit son statut mis à jour.'),

            Titre('c) Les difficultés rencontrées', 3),

            P('Trois obstacles ont été rencontrés lors de la mise au point, et ils méritent d’être rapportés car ils auraient produit des données fausses sans être détectés.'),

            P('Le classeur est presque entièrement calculé : lire une cellule renvoyait la formule et non son résultat. Il a fallu retenir la valeur mise en cache par le tableur plutôt que de recalculer des formules référençant des feuilles non chargées.'),

            P('La feuille du planning reprend plus bas les mêmes intitulés d’ouvrages à d’autres fins ; la seconde occurrence écrasait la première, de sorte que les calendriers arrivaient vides. Seule la première occurrence fait désormais foi.'),

            P('Le rapprochement des ouvrages entre les feuilles, qui ne les nomment pas identiquement, acceptait un préfixe trop court : une clé vide rattachait n’importe quel ouvrage à n’importe quelle tâche. Une longueur minimale a été imposée.'),

            Figure('[ FIGURE à insérer — Capture d’écran de l’écran de contrôle précédant l’import ]'),
            Legende('Figure IV.x — Contrôle du contenu reconnu avant écriture'),
            Legende('Source : application développée'),

            Titre('IV.2.4. Les modules financier et marché', 2),

            P('Le module financier porte la valeur acquise et les décomptes ; le module marché porte les avenants. Leur séparation reproduit celle des sections de l’unité de gestion, et elle est effective : la section des marchés instruit les avenants sans accéder aux décomptes.'),

            P('Deux choix de réalisation méritent d’être signalés. Le premier est que la valeur acquise est portée par l’ouvrage et non par le projet, chaque ouvrage étant valorisé à son montant contractuel. Ce point a fait l’objet d’une correction en cours de développement : les valeurs étaient initialement inscrites au niveau du projet, de sorte que les écrans financiers, qui les lisent par ouvrage, demeuraient vides alors même que la base était complète.'),

            P('Le second est que le montant du marché actualisé n’est jamais stocké. Il se déduit à la lecture du montant initial et des avenants approuvés, si bien que l’enregistrement d’un avenant met à jour, du même geste, le montant de référence, les taux qui s’y rapportent et le coût final estimé. Le même mécanisme vaut pour l’échéance contractuelle, qu’un avenant de délai repousse sans qu’aucune date n’ait à être ressaisie.'),

            Titre('IV.2.5. Le tableau de bord et la cartographie', 2),

            P('Le tableau de bord est le seul écran qui ne s’adresse pas à celui qui saisit mais à celui qui décide. Sa réalisation a soulevé une difficulté propre : il agrège l’ensemble du portefeuille, et devait le faire sans que le temps d’affichage ne croisse avec le nombre de projets.'),

            P('La solution retenue tient au choix de conservation exposé au chapitre III. L’avancement consolidé de chaque projet et les cumuls de sa valeur acquise sont enregistrés en même temps qu’ils sont calculés, de sorte que le tableau de bord n’a pas à recalculer chaque projet à chaque affichage : il lit des valeurs déjà établies. Ces valeurs ne sont jamais saisies, et une commande permet de les reconstruire intégralement en cas de doute.'),

            P('L’écran restitue les agrégats du portefeuille, la répartition des projets par étape du cycle de vie, et la liste de ceux qui appellent une attention. La cartographie, réalisée au moyen d’une bibliothèque de cartes libre s’appuyant sur un fond ouvert, situe chaque site universitaire à ses coordonnées et en indique l’état d’avancement. Ce choix évite toute dépendance à un service cartographique commercial, qui aurait supposé une clé d’accès et un abonnement.'),

            P('La fiche projet, quant à elle, ouvre sur une rangée de cartes d’indicateurs suivie des sept onglets thématiques. Deux graphiques y sont produits dans le navigateur : la courbe en S, qui superpose avancement planifié et avancement constaté mois après mois, et le diagramme de Gantt de l’ouvrage sélectionné, dressé à partir des lots et des jalons.'),

            Figure('[ FIGURE à insérer — Capture d’écran du tableau de bord et de la cartographie du portefeuille ]'),
            Legende('Figure IV.x — Tableau de bord et cartographie'),
            Legende('Source : application développée'),

            Titre('IV.2.6. Le module d’alertes', 2),

            P('Onze règles de détection ont été implémentées, chacune sous la forme d’une méthode d’évaluation et d’une méthode de description du contexte. Le service parcourt les règles à chaque saisie et à chaque import, ouvre celles dont la condition est remplie, ajuste la gravité de celles qui s’aggravent, et referme celles dont la condition a cessé.'),

            P('La clôture automatique a demandé plus d’attention que l’ouverture. Une alerte qui ne se referme pas transforme la liste en registre de signalements périmés, que plus personne ne consulte. Chaque règle porte donc, indissociablement, sa condition d’ouverture et sa condition de fermeture, et les deux sont éprouvées par les jeux d’essais.'),

            P('La sensibilité des règles n’est pas inscrite dans le code : les seize seuils sont enregistrés en base et modifiables depuis un écran d’administration, chacun borné pour interdire un paramétrage aberrant. Une défaillance de lecture de ces seuils ne bloque pas l’application, qui se replie sur les valeurs par défaut.'),

            Titre('IV.2.7. La production des restitutions', 2),

            P('Le module de restitution fait l’objet du point IV.3, auquel il est renvoyé pour son contenu et ses destinataires. Sa réalisation appelle toutefois deux observations techniques, qui trouvent leur place ici.'),

            P('La première est que les rapports procèdent des mêmes services de calcul que les écrans. C’est l’application directe du principe de séparation exposé plus haut : le rapport n’effectue aucun calcul propre, il interroge les services et met en forme leurs résultats. Aucun écart ne peut donc apparaître entre un chiffre affiché et le même chiffre exporté.'),

            P('La seconde est que les graphiques ont posé une difficulté particulière. Les bibliothèques employées dans l’interface s’exécutent dans le navigateur, alors que le rapport est produit par le serveur, qui n’en dispose pas. Les courbes du rapport sont donc tracées directement en graphique vectoriel côté serveur. Ce dédoublement a un coût — deux implémentations à maintenir pour une même courbe — mais il garantit que le document produit est identique quel que soit le poste qui l’a demandé.'),

            Titre('IV.2.8. Le module d’administration', 2),

            P('Trois écrans réservés au profil administrateur complètent l’application : la gestion des comptes et des rôles, le paramétrage des seuils, et la consultation du journal d’activité. Ce dernier consigne chaque création, modification et suppression avec son auteur et son horodatage, y compris les imports, attribués à celui qui les déclenche.'),

            P('Un défaut de sécurité a été corrigé au cours de cette réalisation, et il mérite d’être mentionné plutôt que taire : la désactivation d’un compte n’était pas vérifiée à l’authentification, de sorte qu’un agent désactivé pouvait encore se connecter. La condition a été portée dans la requête d’authentification elle-même et fait désormais l’objet d’un essai automatisé.'),

            Titre('IV.2.9. La vérification automatisée', 2),

            P('Quinze jeux d’essais, totalisant environ cent trente cas, accompagnent le développement. Ils ne vérifient pas l’apparence des écrans mais le comportement des règles : qu’une saisie hors bornes est refusée, qu’un profil non habilité se heurte à un refus serveur, qu’une alerte se referme au rétablissement, qu’un import rejoué n’ajoute rien, qu’un compte désactivé ne peut pas se connecter.'),

            P('Ces essais ont une fonction que le développement a confirmée : ils ont permis de modifier le mode de calcul de la fin projetée, de ventiler la valeur acquise par ouvrage et de corriger le décompte du temps écoulé sans crainte de rompre ce qui fonctionnait. Sans eux, chacune de ces reprises aurait été un pari.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
