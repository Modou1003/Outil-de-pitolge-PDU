/**
 * IV.2 — Réalisation des modules, une sous-section par module annoncé en III.3.1.
 *
 * Reprend la forme adoptée par l'étudiant : présentation brève, principes en
 * puces, figure légendée.
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
    spacing: { before: n === 2 ? 280 : 220, after: 130 },
});

const Puce = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    bullet: { level: 0 },
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 90, line: 264 },
});

const Fig = (texte, numero, titre) => [
    new Paragraph({
        children: [new TextRun({ text: texte, font: POLICE, size: 20, italics: true, highlight: 'yellow' })],
        alignment: AlignmentType.CENTER,
        spacing: { before: 220, after: 70 },
    }),
    new Paragraph({
        children: [new TextRun({ text: `Figure IV.${numero} — ${titre}`, font: POLICE, size: 20, italics: true })],
        alignment: AlignmentType.CENTER,
        spacing: { after: 50 },
    }),
    new Paragraph({
        children: [new TextRun({ text: 'Source : application développée', font: POLICE, size: 20, italics: true })],
        alignment: AlignmentType.CENTER,
        spacing: { after: 190 },
    }),
];

const B = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDS = { top: B, bottom: B, left: B, right: B };

const Cel = (t, { entete = false, largeur } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDS,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 50, bottom: 50, left: 70, right: 70 },
    children: [new Paragraph({
        children: [new TextRun({ text: t, font: POLICE, size: 20, bold: entete })],
        alignment: entete ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 230 },
    })],
});

const Tableau = (entetes, lignes, largeurs) => new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({ tableHeader: true, children: entetes.map((e, i) => Cel(e, { entete: true, largeur: largeurs[i] })) }),
        ...lignes.map((l) => new TableRow({ children: l.map((c, i) => Cel(c, { largeur: largeurs[i] })) })),
    ],
});

const SECTIONS_UGP = [
    ['Opérations et suivi-évaluation',
        'Ouvrages et pondérations, calendrier contractuel, jalons, relevés d’avancement physique, import du classeur mensuel',
        'Données financières et contractuelles, en lecture'],
    ['Affaires administratives et financières',
        'Décomptes, récupérations d’avance, valeurs de la valeur acquise',
        'Avancement physique et pièces contractuelles, en lecture'],
    ['Marchés et affaires juridiques',
        'Avenants en montant et en délai',
        'Avancement physique et financier, en lecture'],
    ['Direction de l’unité de gestion',
        'Supervise l’ensemble ; seule compétente sur les deux domaines à la fois',
        'Portefeuille consolidé'],
    ['Administration',
        'Comptes et rôles, seuils de déclenchement des alertes',
        'Journal des écritures'],
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

            P('Les neuf modules retenus au point III.3.1 ont tous été réalisés. Ils sont présentés ci-après dans l’ordre où ils y ont été annoncés, afin que la correspondance entre ce qui était prévu et ce qui a été construit reste vérifiable.'),

            // ═══════════════════════════════════════════ 1
            Titre('IV.2.1. Le tableau de bord', 3),

            P('Le tableau de bord constitue la page d’accueil après authentification. Il répond au premier besoin exprimé au chapitre II : disposer à tout moment d’une vue consolidée du portefeuille, sans avoir à la reconstituer.'),

            P('Il se compose de trois ensembles : des cartes présentant les valeurs clés du portefeuille — nombre de projets par étape du cycle de vie, avancement moyen pondéré, montant engagé et taux d’exécution budgétaire consolidé ; la liste des projets appelant une attention ; et la liste des alertes actives, classée par gravité.'),

            P('Aucun chiffre du tableau de bord n’est saisi : tous dérivent des relevés élémentaires, ce qui élimine par construction l’écart entre le tableau de bord et les données de base. Pour que l’affichage reste immédiat quel que soit le nombre de projets, l’avancement consolidé de chacun est enregistré au moment même où il est calculé ; cette valeur n’est jamais renseignée à la main et se reconstruit intégralement sur commande.'),

            ...Fig('[ FIGURE à insérer — Tableau de bord de l’application ]', 2, 'Tableau de bord de l’application'),

            // ═══════════════════════════════════════════ 2
            Titre('IV.2.2. La cartographie du portefeuille', 3),

            P('La cartographie situe chaque site universitaire à ses coordonnées géographiques. Le repère prend la couleur de l’état du projet, et un clic ouvre la fiche correspondante.'),

            P('Elle s’appuie sur une bibliothèque de cartes libre et un fond ouvert. Ce choix évite toute dépendance à un service cartographique commercial, qui aurait supposé une clé d’accès, un abonnement et une exposition des données de localisation à un tiers — trois contraintes difficilement acceptables pour un outil public.'),

            P('Son utilité tient à la dispersion géographique du programme : les sites sont répartis sur l’ensemble du territoire, et la seule lecture d’une liste ne fait pas apparaître qu’un projet en difficulté se trouve à huit cent soixante-dix kilomètres du siège.'),

            ...Fig('[ FIGURE à insérer — Cartographie interactive du portefeuille ]', 3, 'Cartographie interactive du portefeuille'),

            // ═══════════════════════════════════════════ 3
            Titre('IV.2.3. La fiche projet', 3),

            P('La fiche projet donne accès au détail d’une opération. Elle ouvre sur une rangée de cartes d’indicateurs, suivie de sept onglets : informations générales, avancement physique, avancement financier, marché, planning, indicateurs et documents. Cette disposition est délibérée : le lecteur voit l’état du projet avant d’avoir à choisir un onglet, et n’entre dans le détail que s’il en a besoin.'),

            P('Le découpage en onglets reproduit celui des sections de l’unité de gestion, de sorte que l’onglet constitue l’unité naturelle d’habilitation : un agent de la section financière n’a que faire des lots de planning, et un chef de projet n’instruit pas les avenants.'),

            P('Trois représentations y sont produites automatiquement.'),

            Puce('La courbe en S de l’avancement physique, qui superpose mois après mois l’avancement planifié au marché et l’avancement constaté. L’écart vertical donne le retard d’avancement, l’écart horizontal le décalage en délais.'),
            Puce('La courbe de la valeur acquise, qui superpose les cumuls de la valeur planifiée, de la valeur acquise et du coût réel. L’écart entre les deux dernières donne l’écart de coût.'),
            Puce('Le diagramme de Gantt, qui situe les lots et les jalons de l’ouvrage sélectionné, avec une ligne matérialisant la date d’observation.'),

            ...Fig('[ FIGURE à insérer — Fiche projet et cartes d’indicateurs ]', 4, 'Fiche projet et cartes d’indicateurs'),
            ...Fig('[ FIGURE à insérer — Courbe en S générée par l’application ]', 5, 'Courbe en S générée par l’application'),
            ...Fig('[ FIGURE à insérer — Diagramme de Gantt généré par l’application ]', 6, 'Diagramme de Gantt généré par l’application'),

            // ═══════════════════════════════════════════ 4
            Titre('IV.2.4. La saisie et l’import des données d’avancement', 3),

            P('La saisie constitue le point critique du dispositif. Le diagnostic a montré que la charge de saisie conditionne la régularité du suivi : un formulaire long est un formulaire délaissé. L’interface a donc été conçue selon trois principes.'),

            Puce('La réduction du nombre de champs. L’écran présente les ouvrages du marché, rappelle l’avancement de la période précédente et ne demande que le pourcentage atteint et un commentaire facultatif. Tout ce qui peut être déduit l’est.'),
            Puce('Le contrôle à la saisie. Refus d’un pourcentage hors des bornes admises, d’un second relevé pour une période et un ouvrage déjà renseignés, ou d’un relevé rattaché à un ouvrage étranger au projet. Ces contrôles sont exercés par le serveur : une requête adressée directement à l’adresse de la fonction se heurte au même refus.'),
            Puce('L’habilitation et la traçabilité. Seul un agent titulaire de la compétence correspondante peut écrire, et chaque saisie porte le nom de son auteur ainsi que son horodatage, consignés au journal d’activité.'),

            P('Ce dernier point fixe la portée de ce que l’outil garantit. L’application ne comporte pas de circuit d’approbation hiérarchique : un relevé alimente les indicateurs dès son enregistrement. La garantie de fiabilité repose donc sur l’origine de la donnée, issue du rapport mensuel visé par la mission de contrôle — document contradictoire établi hors de l’application. Le contrôle existe, mais il se situe en amont de la saisie.'),

            P('C’est précisément ce constat qui a conduit à doter le module d’une fonction d’import, non prévue au cahier des charges initial. La mission de contrôle établit chaque mois un classeur contenant, pour chaque ouvrage, sa pondération, son avancement planifié et constaté, son calendrier et sa facturation. Plutôt que de retranscrire ce document, l’application l’importe, en deux temps.'),

            Puce('La lecture, qui transpose le classeur sans qu’aucune écriture n’intervienne, et restitue à l’écran ce qui a été reconnu — ouvrages, pondérations, mois nouveaux, décomptes — ainsi que ce qui n’a pas pu être rattaché.'),
            Puce('L’écriture, qui n’intervient qu’après confirmation et ne fait qu’ajouter : seules les périodes absentes donnent lieu à un relevé, une correction apportée à la main survit à tout import ultérieur, et le même fichier déposé deux fois ne produit aucun doublon.'),

            P('Une seule opération alimente ainsi l’avancement physique, la valeur acquise, les décomptes, les calendriers d’ouvrages, les indicateurs et les alertes. L’écran de contrôle répond à la fragilité du procédé : la lecture repose sur la structure d’un document dont l’application n’est pas l’auteur, et une refonte du classeur en interromprait la reconnaissance. L’écran transforme alors une rupture silencieuse en une rupture visible.'),

            ...Fig('[ FIGURE à insérer — Écran de saisie de l’avancement physique ]', 7, 'Écran de saisie de l’avancement physique'),
            ...Fig('[ FIGURE à insérer — Contrôle du contenu reconnu avant import ]', 8, 'Contrôle préalable à l’import de la base de calcul'),

            // ═══════════════════════════════════════════ 5
            Titre('IV.2.5. Le module marché', 3),

            P('Le module marché rassemble les actes contractuels, c’est-à-dire les avenants, qu’ils portent sur le montant, sur le délai, ou sur les deux à la fois.'),

            P('Son principe de réalisation tient en une phrase : ni le montant actualisé du marché ni l’échéance actualisée ne sont stockés. L’un et l’autre se déduisent à la lecture.'),

            Puce('Le montant actualisé vaut le montant initial augmenté de la somme des avenants approuvés.'),
            Puce('L’échéance actualisée vaut la date de fin contractuelle augmentée de la somme des prolongations accordées.'),

            P('L’enregistrement d’un avenant met ainsi à jour, du même geste, le montant de référence, les taux qui s’y rapportent, le coût final estimé et le retard projeté. Aucune reprise manuelle n’est nécessaire, et aucune n’est possible — ce qui écarte tout risque de divergence entre le suivi et les pièces contractuelles.'),

            P('Une restriction a été posée : seul un avenant approuvé peut être enregistré. Une demande en instruction auprès du bailleur ne peut l’être, sous peine d’afficher un montant ou une échéance qui n’existent pas encore.'),

            ...Fig('[ FIGURE à insérer — Module marché et saisie d’un avenant ]', 9, 'Module marché : avenants en montant et en délai'),

            // ═══════════════════════════════════════════ 6
            Titre('IV.2.6. Le module financier', 3),

            P('Le module financier porte deux natures de données : les valeurs de la méthode de la valeur acquise, et les décomptes de l’entreprise avec leurs récupérations d’avance.'),

            P('Deux choix de réalisation méritent d’être signalés.'),

            Puce('La valeur acquise est portée par l’ouvrage et non par le projet, chaque ouvrage étant valorisé à son montant contractuel. Ce choix est plus juste qu’une répartition du marché au prorata de la pondération physique, les deux poids ne se confondant pas.'),
            Puce('Les décomptes sont enregistrés en montant brut, avant déduction des récupérations d’avance, de sorte que la facturation constatée se distingue du décaissement effectif. L’exposition aux avances en découle, et demeure signalée tant qu’elle n’est pas apurée.'),

            P('Le module restitue en outre les trois indicateurs financiers contractuels définis en III.2.2 — reste à facturer, encaissé, exposition aux avances — ainsi que la courbe cumulée de la valeur acquise.'),

            ...Fig('[ FIGURE à insérer — Module financier : valeur acquise et décomptes ]', 10, 'Module financier : valeur acquise et décomptes'),

            // ═══════════════════════════════════════════ 7
            Titre('IV.2.7. Le module d’alertes automatiques', 3),

            P('Le module d’alertes met en œuvre les onze règles de détection définies en III.4.3. Le service parcourt l’ensemble des règles et les évalue à chaque écriture — saisie manuelle comme import — plutôt qu’à intervalles réguliers.'),

            P('Ce choix supprime le décalage entre le moment où la donnée entre en base et celui où l’anomalie est signalée. Il a pour contrepartie qu’une alerte fondée sur l’écoulement du temps, telle l’absence de mise à jour, n’est réévaluée qu’à la prochaine écriture sur le projet ; une exécution périodique complémentaire lèverait cette limite.'),

            P('Trois mécanismes garantissent la pertinence du dispositif dans la durée.'),

            Puce('La non-duplication. Une alerte déjà ouverte pour un projet et un motif donné n’est pas recréée à chaque évaluation ; seule sa gravité est réajustée si l’écart s’aggrave.'),
            Puce('La clôture automatique. Dès que la condition ayant déclenché une alerte cesse d’être remplie, celle-ci est automatiquement close. Sans ce mécanisme, la liste se remplirait de signalements périmés et cesserait d’être lue.'),
            Puce('Le paramétrage des seuils. Les seize seuils ne figurent pas dans le code mais dans une table de paramètres, modifiable depuis un écran d’administration. L’unité de gestion conserve ainsi la maîtrise de ce qu’elle considère comme une dérive.'),

            ...Fig('[ FIGURE à insérer — Module d’alertes de l’application ]', 11, 'Module d’alertes de l’application'),

            // ═══════════════════════════════════════════ 8
            Titre('IV.2.8. Le module de rapports', 3),

            P('Le module de rapports produit deux documents complémentaires : la fiche projet, au format A4 portrait, qui restitue la situation détaillée d’une opération ; et le rapport de portefeuille, en A4 paysage, qui consolide l’ensemble des projets. L’un et l’autre sont générés à la demande, sans préparation préalable ni intervention technique.'),

            P('Leur principale caractéristique est d’être composables : avant le téléchargement, l’utilisateur coche les sections qu’il souhaite retenir — onze pour la fiche projet, trois pour le rapport de portefeuille. Toutes sont retenues par défaut, de sorte que la production d’un document complet ne demande qu’un clic ; la sélection ne devient nécessaire que lorsqu’un destinataire particulier appelle un document allégé.'),

            P('Une propriété du module mérite d’être soulignée : le rapport n’effectue aucun calcul propre. Il interroge les mêmes services que les écrans et se borne à mettre leurs résultats en forme. Aucun écart ne peut donc apparaître entre un chiffre consulté à l’écran et le même chiffre exporté.'),

            P('Les graphiques ont posé une difficulté particulière. Les bibliothèques employées dans l’interface s’exécutent dans le navigateur, alors que le rapport est produit par le serveur, qui n’en dispose pas. Les courbes du rapport sont donc tracées directement en graphique vectoriel côté serveur : ce dédoublement a un coût, mais il garantit que le document est identique quel que soit le poste qui l’a demandé.'),

            ...Fig('[ FIGURE à insérer — Exemple de rapport généré ]', 12, 'Exemple de rapport généré'),

            // ═══════════════════════════════════════════ 9
            Titre('IV.2.9. Le module d’administration', 3),

            P('Trois écrans réservés au profil administrateur complètent l’application : la gestion des comptes et des rôles, le paramétrage des seuils de déclenchement, et la consultation du journal d’activité.'),

            P('Le journal consigne chaque création, modification et suppression avec son auteur, son horodatage et l’objet concerné, y compris les imports, attribués à celui qui les déclenche. Il est conçu pour absorber ses propres erreurs : un défaut de journalisation n’interrompt jamais l’opération métier qu’il accompagne.'),

            P('Un défaut de sécurité a été corrigé au cours de cette réalisation, et il mérite d’être mentionné plutôt que taire : la désactivation d’un compte n’était pas vérifiée à l’authentification, de sorte qu’un agent désactivé pouvait encore se connecter. La condition a été portée dans la requête d’authentification elle-même et fait désormais l’objet d’un essai automatisé.'),

            ...Fig('[ FIGURE à insérer — Écran d’administration : journal d’activité et seuils ]', 13, 'Administration : journal d’activité et paramétrage des seuils'),

            // ═══════════════════════════════════════════ base de données
            Titre('IV.2.10. L’alimentation de la base par les sections de l’UGP', 3),

            P('La base de données a été construite par migrations, c’est-à-dire par une suite de scripts versionnés décrivant la création de chaque table et de chaque contrainte — quarante-huit à ce jour. La structure est ainsi reconstructible à l’identique sur un autre serveur, et son évolution est tracée au même titre que le code.'),

            P('L’alimentation reproduit la répartition des responsabilités constatée au chapitre II. La séparation est effective et non déclarative : la section des marchés instruit les avenants sans accéder aux décomptes, et la section financière l’inverse.'),

            new Paragraph({
                children: [new TextRun({ text: 'Tableau IV.3 — Alimentation de la base par les sections de l’UGP', font: POLICE, size: 20, italics: true })],
                alignment: AlignmentType.CENTER,
                spacing: { before: 170, after: 90 },
            }),
            Tableau(['Section', 'Données saisies', 'Données consultées'], SECTIONS_UGP, [24, 46, 30]),
            new Paragraph({
                children: [new TextRun({ text: 'Source : auteur', font: POLICE, size: 20, italics: true })],
                alignment: AlignmentType.CENTER,
                spacing: { before: 70, after: 190 },
            }),

            P('Cette séparation vaut jusque dans les mécanismes automatiques. Lors de l’import du classeur mensuel, le coût réel n’est inscrit que si l’agent qui dépose le fichier a la charge des finances ; à défaut, la valeur acquise est alimentée et le coût laissé vide, qu’un import ultérieur mené par la section compétente viendra compléter.'),

            P('Une précaution a été prise pour la mise en service : des jeux de données initiaux créent automatiquement les rôles, les permissions et les référentiels de base. Cette automatisation évite une saisie manuelle fastidieuse au démarrage et garantit que l’environnement de production est configuré exactement comme l’environnement de développement.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
