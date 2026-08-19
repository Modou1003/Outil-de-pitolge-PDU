/**
 * IV.2 et IV.3 — Réalisation des modules, dans la forme adoptée par l'étudiant.
 *
 * Le plan, le style et les figures sont conservés ; seules les affirmations
 * devenues inexactes ont été rectifiées, et les modules apparus depuis la
 * première rédaction ont été ajoutés.
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
    spacing: { before: 220, after: 60 },
});

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

            // ───────────────────────────────────────────────── IV.2.1
            Titre('IV.2.1. Interface de saisie et de mise à jour des données d’avancement par projet', 3),

            P('La saisie constitue le point critique du dispositif. Le diagnostic a montré que la charge de saisie conditionne la régularité du suivi : un formulaire long est un formulaire délaissé. L’interface a donc été conçue selon trois principes.'),

            Puce('La réduction du nombre de champs. L’écran de saisie d’avancement physique présente les ouvrages du marché, rappelle l’avancement de la période précédente et ne demande que le pourcentage atteint et un commentaire facultatif. Tout ce qui peut être déduit l’est : la pondération est déjà connue, l’avancement consolidé se calcule, le retard au démarrage se déduit des dates du calendrier contractuel.'),
            Puce('Le contrôle à la saisie. Refus d’un pourcentage hors des bornes admises, refus d’un second relevé pour une période et un ouvrage déjà renseignés, refus d’un relevé rattaché à un ouvrage étranger au projet. L’erreur est ainsi arrêtée à l’entrée plutôt que corrigée ensuite. Ces contrôles sont exercés par le serveur et non par l’écran : une requête adressée directement à l’adresse de la fonction se heurte au même refus.'),
            Puce('L’habilitation et la traçabilité. Seul un agent titulaire de la compétence correspondante peut écrire, et chaque saisie porte le nom de son auteur ainsi que son horodatage, consignés au journal d’activité. Le contrôle ne s’exerce donc pas par un visa préalable mais par l’habilitation en amont et la traçabilité en aval.'),

            P('Ce dernier point mérite d’être explicité, car il fixe la portée de ce que l’outil garantit. L’application ne comporte pas de circuit d’approbation hiérarchique : un relevé alimente les indicateurs dès son enregistrement. La garantie de fiabilité ne repose donc pas sur un mécanisme interne, mais sur l’origine de la donnée, qui provient du rapport mensuel visé par la mission de contrôle — document contradictoire établi hors de l’application. Le contrôle existe, il se situe en amont de la saisie. L’introduction d’un circuit de validation figure parmi les perspectives exposées au chapitre V.'),

            P('L’écran a par ailleurs été complété d’un bloc de calendrier contractuel, renseigné à la création de l’ouvrage : durée, dates de début et de fin prévues au marché, dates de début et de fin réellement constatées. Ce complément répond à un constat de terrain rapporté au chapitre V, plusieurs ouvrages ayant démarré des mois après la date prévue sans que rien dans l’application ne permît de le savoir.'),

            Figure('[ FIGURE à insérer — Écran de saisie de l’avancement physique ]'),
            Legende('Figure IV.2 — Écran de saisie de l’avancement physique'),
            Legende('Source : application développée'),

            // ───────────────────────────────────────────────── IV.2.2
            Titre('IV.2.2. Import de la base de calcul de la mission de contrôle', 3),

            P('Ce module n’était pas prévu au cahier des charges initial. Il est né de l’examen des documents réellement produits sur le chantier : la mission de contrôle établit chaque mois un classeur contenant, pour chaque ouvrage, sa pondération, son avancement planifié et constaté, son calendrier et sa facturation. Retranscrire ce document à la main aurait été aussi long que hasardeux.'),

            P('L’import se déroule en deux temps, et cette séparation constitue le choix de conception essentiel du module.'),

            Puce('La lecture. Le classeur déposé est transposé sans qu’aucune écriture n’intervienne. Un écran de contrôle restitue ce qui a été reconnu — ouvrages, pondérations, mois nouveaux, décomptes — ainsi que ce que l’application n’a pas su rattacher.'),
            Puce('L’écriture. Elle n’intervient qu’après confirmation explicite, et ne fait qu’ajouter : seules les périodes absentes donnent lieu à un relevé, une correction apportée à la main survit à tout import ultérieur, et le même fichier déposé deux fois ne produit aucun doublon.'),

            P('Une seule opération alimente ainsi l’avancement physique, la valeur acquise, les décomptes, les calendriers d’ouvrages, les indicateurs et les alertes. La saisie manuelle demeure — elle reste nécessaire pour corriger une valeur ou renseigner un projet dépourvu de classeur — mais elle n’est plus le mode d’alimentation ordinaire.'),

            P('L’écran de contrôle répond à la fragilité inhérente du procédé. La lecture repose sur la structure d’un document dont l’application n’est pas l’auteur : une refonte du classeur en interromprait la reconnaissance. L’écran transforme alors une rupture silencieuse — des données fausses entrant en base sans que nul ne s’en aperçoive — en une rupture visible, l’utilisateur constatant qu’aucun ouvrage n’a été reconnu.'),

            Figure('[ FIGURE à insérer — Écran de contrôle du contenu reconnu avant import ]'),
            Legende('Figure IV.3 — Contrôle préalable à l’import de la base de calcul'),
            Legende('Source : application développée'),

            // ───────────────────────────────────────────────── IV.2.3
            Titre('IV.2.3. Base de données centralisée : organisation et alimentation par les sections de l’UGP', 3),

            P('La base de données a été construite par migrations, c’est-à-dire par une suite de scripts versionnés décrivant la création de chaque table et de chaque contrainte — quarante-huit à ce jour. Ce procédé présente un avantage déterminant pour la pérennité de l’outil : la structure de la base est reconstructible à l’identique sur un autre serveur, et son évolution est tracée au même titre que le code.'),

            P('L’alimentation de la base reproduit la répartition des responsabilités constatée au chapitre II. La séparation est effective et non déclarative : la section des marchés instruit les avenants sans accéder aux décomptes, et la section financière l’inverse.'),

            Legende('Tableau IV.3 — Alimentation de la base par les sections de l’UGP', true),
            Tableau(['Section', 'Données saisies', 'Données consultées'], SECTIONS_UGP, [24, 46, 30]),
            Legende('Source : auteur'),

            P('Cette séparation vaut jusque dans les mécanismes automatiques. Lors de l’import du classeur mensuel, le coût réel n’est inscrit que si l’agent qui dépose le fichier a la charge des finances ; à défaut, la valeur acquise est alimentée et le coût laissé vide, qu’un import ultérieur mené par la section compétente viendra compléter. Sans cette précaution, la facturation entrerait en base par un chemin détourné.'),

            P('Une précaution a été prise pour la mise en service : des jeux de données initiaux permettent de créer automatiquement les rôles, les permissions et les référentiels de base. Cette automatisation évite une saisie manuelle fastidieuse au démarrage et garantit que l’environnement de production est configuré exactement comme l’environnement de développement.'),

            // ───────────────────────────────────────────────── IV.2.4
            Titre('IV.2.4. Le tableau de bord interactif', 3),

            P('Le tableau de bord constitue la page d’accueil après authentification. Il répond au premier besoin exprimé au chapitre II : disposer à tout moment d’une vue consolidée du portefeuille, sans avoir à la reconstituer.'),

            P('Il se compose de trois ensembles. Des cartes d’indicateurs présentent les valeurs clés : nombre de projets par étape du cycle de vie, avancement moyen pondéré, montant engagé et taux d’exécution budgétaire consolidé. La liste des alertes actives, classée par gravité, signale les situations appelant une décision.'),

            P('La cartographie complète cet ensemble. Chaque site universitaire est localisé par un repère dont la couleur traduit l’état du projet ; un clic ouvre la fiche correspondante. Elle s’appuie sur une bibliothèque de cartes libre et un fond ouvert, ce qui évite toute dépendance à un service commercial supposant une clé d’accès et un abonnement.'),

            P('Les valeurs affichées dérivent toutes des relevés élémentaires. Aucun chiffre du tableau de bord n’est saisi, ce qui élimine par construction l’écart entre le tableau de bord et les données de base. Pour que l’affichage reste immédiat quel que soit le nombre de projets, l’avancement consolidé de chacun est enregistré au moment même où il est calculé ; cette valeur n’est jamais renseignée à la main et se reconstruit intégralement sur commande.'),

            Figure('[ FIGURE à insérer — Tableau de bord de l’application ]'),
            Legende('Figure IV.4 — Tableau de bord de l’application'),
            Legende('Source : application développée'),

            Figure('[ FIGURE à insérer — Cartographie interactive du portefeuille ]'),
            Legende('Figure IV.5 — Cartographie interactive du portefeuille'),
            Legende('Source : application développée'),

            // ───────────────────────────────────────────────── IV.2.5
            Titre('IV.2.5. Suivi par projet', 3),

            P('La fiche projet donne accès au détail d’une opération. Elle est organisée en sept onglets : informations générales, avancement physique, avancement financier, marché, planning, indicateurs et documents. Ce découpage reproduit celui des sections de l’unité de gestion, de sorte que l’onglet constitue l’unité naturelle d’habilitation.'),

            P('Quatre représentations y sont produites automatiquement.'),

            Puce('La courbe en S de l’avancement physique. Elle superpose, mois après mois, l’avancement planifié au planning contractuel et l’avancement constaté. L’écart vertical entre les deux courbes donne le retard d’avancement ; l’écart horizontal donne le décalage en délais, dont se déduit la date d’achèvement projetée.'),
            Puce('La courbe de la valeur acquise. Elle superpose les trois courbes cumulées de la valeur planifiée, de la valeur acquise et du coût réel. L’écart entre la valeur acquise et le coût réel donne l’écart de coût ; l’écart entre la valeur acquise et la valeur planifiée donne l’écart de délai exprimé en montant.'),
            Puce('Le diagramme de Gantt. Il situe les lots et les jalons de l’ouvrage sélectionné dans le temps, avec une ligne de suivi matérialisant la date d’observation.'),
            Puce('Le calendrier contractuel de l’ouvrage. À l’ouverture d’un ouvrage, le retard au démarrage est affiché avec les dates dont il procède, et signalé au-delà de la tolérance paramétrée.'),

            P('Les indicateurs de performance définis en III.2.2 sont calculés à chaque affichage, à partir des dernières valeurs enregistrées.'),

            Figure('[ FIGURE à insérer — Courbe en S générée par l’application ]'),
            Legende('Figure IV.6 — Courbe en S générée par l’application'),
            Legende('Source : application développée'),

            Figure('[ FIGURE à insérer — Diagramme de Gantt généré par l’application ]'),
            Legende('Figure IV.7 — Diagramme de Gantt généré par l’application'),
            Legende('Source : application développée'),

            // ───────────────────────────────────────────────── IV.2.6
            Titre('IV.2.6. Module d’alertes automatiques', 3),

            P('Le module d’alertes met en œuvre les onze règles de détection définies en III.4.3. Le service parcourt l’ensemble des règles et les évalue à chaque écriture — saisie manuelle comme import — plutôt qu’à intervalles réguliers.'),

            P('Ce choix mérite d’être justifié. Une évaluation périodique aurait introduit un décalage entre le moment où la donnée entre en base et celui où l’anomalie est signalée ; l’évaluation à l’écriture supprime ce décalage, l’alerte étant ouverte au moment même où la condition apparaît. Elle a pour contrepartie qu’une alerte fondée sur l’écoulement du temps — l’absence de mise à jour, ou le retard d’un ouvrage non démarré — n’est réévaluée qu’à la prochaine écriture sur le projet. Une exécution périodique complémentaire lèverait cette limite.'),

            P('Trois mécanismes garantissent la pertinence du dispositif dans la durée.'),

            Puce('La non-duplication. Une alerte déjà ouverte pour un projet et un motif donné n’est pas recréée à chaque évaluation ; seule sa gravité est réajustée si l’écart s’aggrave.'),
            Puce('La clôture automatique. Dès que la condition ayant déclenché une alerte cesse d’être remplie, celle-ci est automatiquement close. Sans ce mécanisme, la liste se remplirait de signalements périmés et cesserait d’être lue.'),
            Puce('Le paramétrage des seuils. Les seize seuils ne figurent pas dans le code mais dans une table de paramètres, modifiable depuis un écran d’administration. L’UGP conserve ainsi la maîtrise de ce qu’elle considère comme une dérive.'),

            Figure('[ FIGURE à insérer — Module d’alertes de l’application ]'),
            Legende('Figure IV.8 — Module d’alertes de l’application'),
            Legende('Source : application développée'),

            // ───────────────────────────────────────────────── IV.3
            Titre('IV.3. Module de reporting', 2),
            Titre('IV.3.1. Génération de rapports', 3),

            P('Le module de reporting produit deux documents complémentaires. La fiche projet, au format A4 portrait, restitue la situation détaillée d’une opération ; le rapport de portefeuille, en A4 paysage, consolide l’ensemble des projets pour une lecture d’ensemble. L’un et l’autre sont générés à la demande, sans préparation préalable ni intervention technique.'),

            P('Leur principale caractéristique est d’être composables. Avant le téléchargement, l’utilisateur coche les sections qu’il souhaite retenir :'),

            Puce('Onze pour la fiche projet : identification, indicateurs clés, avenants au marché, courbes d’avancement, planning contractuel par ouvrage, situation financière et décomptes, ouvrages, planning et lots, jalons clés, équipe projet, alertes ouvertes ;'),
            Puce('Trois pour le rapport de portefeuille : synthèse, répartition par région, liste détaillée des projets.'),

            P('Toutes sont retenues par défaut, de sorte que la production d’un document complet ne demande qu’un clic ; la sélection ne devient nécessaire que lorsqu’un destinataire particulier appelle un document allégé.'),

            P('Une propriété du module mérite d’être soulignée : le rapport n’effectue aucun calcul propre. Il interroge les mêmes services que les écrans et se borne à mettre leurs résultats en forme. Aucun écart ne peut donc apparaître entre un chiffre consulté à l’écran et le même chiffre exporté, ce qui répond directement à l’une des conditions de vérification de la première hypothèse.'),

            P('Les graphiques ont posé une difficulté particulière. Les bibliothèques employées dans l’interface s’exécutent dans le navigateur, alors que le rapport est produit par le serveur, qui n’en dispose pas. Les courbes du rapport sont donc tracées directement en graphique vectoriel côté serveur. Ce dédoublement a un coût — deux implémentations à maintenir pour une même courbe — mais il garantit que le document produit est identique quel que soit le poste qui l’a demandé.'),

            Figure('[ FIGURE à insérer — Exemple de rapport généré ]'),
            Legende('Figure IV.9 — Exemple de rapport généré'),
            Legende('Source : application développée'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
