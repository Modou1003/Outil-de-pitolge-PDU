/**
 * Chapitre VI — Recommandations et perspectives.
 *
 * Les recommandations prennent appui sur les limites établies au V.3.3 et sur
 * l'état réel de l'application : sept rôles et dix-sept profils de consultation,
 * onze règles d'alerte à seuils paramétrables, import de la base de calcul
 * mensuelle, cartographie des projets actifs, cent quarante-deux essais
 * automatisés. Rien n'est recommandé qui ne réponde à un manque constaté.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (t, o = {}) => new TextRun({ text: t, font: POLICE, size: 24, ...o });

const P = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 120, line: 276 },
});

const Puce = (t) => new Paragraph({
    children: [T(t)],
    alignment: AlignmentType.JUSTIFIED,
    bullet: { level: 0 },
    spacing: { after: 100, line: 276 },
});

const Titre = (t, n) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: n === 1 ? 28 : n === 2 ? 26 : 24, bold: true })],
    heading: n === 1 ? HeadingLevel.HEADING_1 : n === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3,
    alignment: n === 1 ? AlignmentType.CENTER : AlignmentType.LEFT,
    spacing: { before: n === 1 ? 0 : 260, after: n === 1 ? 260 : 150 },
});

const Legende = (t, avant = false) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: avant ? { before: 200, after: 100 } : { before: 80, after: 200 },
});

const B = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDS = { top: B, bottom: B, left: B, right: B };

const Cel = (t, { entete = false, largeur, centre = false, jaune = false } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDS,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 60, bottom: 60, left: 80, right: 80 },
    children: [new Paragraph({
        children: [new TextRun({
            text: t, font: POLICE, size: 20, bold: entete,
            highlight: jaune ? 'yellow' : undefined,
        })],
        alignment: (entete || centre) ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 240 },
    })],
});

const Tableau = (entetes, lignes, largeurs, centrees = []) => new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({
            tableHeader: true,
            children: entetes.map((e, i) => Cel(e, { entete: true, largeur: largeurs[i] })),
        }),
        ...lignes.map((l) => new TableRow({
            children: l.map((c, i) => Cel(c, {
                largeur: largeurs[i],
                centre: centrees.includes(i),
                jaune: c === '…' || c.startsWith('[ '),
            })),
        })),
    ],
});

// ═════════════════════════════════════════════════════════ tableaux

const DEPLOIEMENT = [
    ['Première vague', 'Le projet d’Odienné et deux projets en travaux, choisis pour la diversité de leur état d’avancement',
        'Six à huit semaines', 'Trois cycles mensuels complets de saisie, d’import et de restitution sans reprise manuelle'],
    ['Deuxième vague', 'L’ensemble des projets en phase de travaux',
        'Huit à dix semaines', 'Rapport du comité de pilotage produit intégralement depuis l’application'],
    ['Troisième vague', 'Les projets en études, en passation et achevés ; reprise de l’historique documentaire',
        'Six semaines', 'Portefeuille complet consultable ; abandon formel des tableaux de suivi parallèles'],
];

const FORMATION = [
    ['Agents de saisie des trois sections', 'Une demi-journée par section',
        'Saisie d’un relevé mensuel, import de la base de calcul, correction d’une erreur de saisie, lecture des messages de contrôle',
        'Fiche mémo d’une page par section, affichée au poste de travail'],
    ['Chefs de projet', 'Une journée',
        'Création et pondération des ouvrages, calendriers contractuels, lecture des indicateurs de la valeur acquise, traitement d’une alerte, production d’un rapport',
        'Guide d’utilisation illustré, organisé par tâche et non par écran'],
    ['Directeurs et coordonnateur', 'Deux heures',
        'Lecture du tableau de bord, de la cartographie et des alertes ; interprétation des indices et de leur périmètre de calcul',
        'Note de lecture de deux pages sur la signification des indicateurs'],
    ['Membres du comité de pilotage', 'Une heure, en ouverture de séance',
        'Lecture du rapport produit par l’application et de ses onze sections',
        'Sommaire commenté du rapport type'],
    ['Référents internes, un par section', 'Deux journées, après la première vague',
        'Ensemble des fonctions de leur domaine, gestion des comptes et des profils, diagnostic des incidents courants',
        'Procédure de premier niveau et registre des incidents'],
];

const MAINTENANCE = [
    ['Corrective', 'Correction des anomalies signalées par les utilisateurs ou relevées dans le journal des erreurs',
        'Au fil de l’eau ; mise en production groupée mensuelle',
        'Développeur référent, sur signalement du référent de section'],
    ['Préventive', 'Exécution de la suite des cent quarante-deux essais automatisés avant toute mise en production',
        'À chaque livraison', 'Développeur référent'],
    ['Adaptative', 'Mise à jour des composants logiciels et de leurs dépendances de sécurité',
        'Trimestrielle pour les correctifs de sécurité ; annuelle pour les versions majeures',
        'Développeur référent'],
    ['Adaptative', 'Vérification de la lisibilité du classeur mensuel de la mission de contrôle et adaptation du lecteur en cas de changement de mise en forme',
        'À chaque campagne mensuelle d’import', 'Référent de la section technique'],
    ['Évolutive', 'Traitement du registre des demandes issues des utilisateurs et des limites recensées au chapitre V',
        'Revue semestrielle, arbitrage par le coordonnateur',
        'Comité restreint : coordonnateur, référents de section, développeur'],
    ['Continuité', 'Sauvegarde quotidienne de la base et des documents attachés, conservée hors du serveur d’exploitation',
        'Quotidienne, automatique', 'Hébergeur, sous contrôle de l’UGP'],
    ['Continuité', 'Essai de restauration complète sur un environnement distinct',
        'Semestrielle', 'Développeur référent, procès-verbal au coordonnateur'],
];

const ANCRAGE = [
    ['Rendre la saisie obligatoire et en fixer le calendrier',
        'Note de service du coordonnateur : date d’arrêté au dernier jour du mois, saisie close le 10 du mois suivant',
        'Coordonnateur de l’UGP',
        'La donnée cesse de dépendre de la disponibilité de chacun ; l’alerte d’absence de mise à jour devient exigible'],
    ['Faire de l’application la source unique des restitutions officielles',
        'Décision de produire le rapport mensuel et le dossier du comité de pilotage depuis l’application, à l’exclusion de tout autre support',
        'Coordonnateur, sur avis du comité de pilotage',
        'Supprime le double circuit, dont l’expérience montre qu’il survit tant qu’il reste toléré'],
    ['Inscrire l’outil au manuel de procédures de l’UGP',
        'Révision du manuel : qui saisit, dans quel délai, qui valide, qui consulte',
        'Coordonnateur, avec les responsables de section',
        'L’usage survit au départ des personnes qui l’ont installé'],
    ['Obtenir la base de calcul dans un format lisible',
        'Clause et annexe technique dans les marchés d’assistance à maîtrise d’ouvrage et de contrôle des travaux',
        'Section des marchés, à la passation ou par avenant',
        'Pérennise l’alimentation automatique et transfère au prestataire la charge du formatage'],
    ['Fixer le régime de la donnée',
        'Convention d’hébergement : propriété des données, lieu de conservation, durée d’archivage, conditions de restitution en fin de contrat',
        'Coordonnateur, avec l’appui du service juridique',
        'Prévient la captation de la donnée publique par un prestataire technique'],
    ['Désigner les responsabilités nominativement',
        'Décision portant désignation d’un référent par section et d’un administrateur de l’application',
        'Coordonnateur',
        'Traite le risque d’homme-clé, principal danger d’un outil né d’un travail individuel'],
];

const EXTENSION = [
    ['Infrastructures routières', 'Section homogène repérée par ses points kilométriques, ouvrage d’art traité comme un ouvrage distinct',
        'Décomposition linéaire ; l’avancement se mesure en linéaire réalisé plutôt qu’en pourcentage de bâtiment',
        'Modéré : la pondération par enveloppe s’applique sans changement de structure'],
    ['Établissements de santé', 'Bâtiment ou plateau technique, souvent répété à l’identique sur plusieurs sites',
        'Modèles d’ouvrages réutilisables d’un site à l’autre, pour éviter la ressaisie de décompositions identiques',
        'Faible : la structure convient en l’état ; l’ajout des modèles est un confort'],
    ['Adduction et assainissement', 'Réseau linéaire et ouvrages ponctuels — captages, réservoirs, stations',
        'Coexistence de deux logiques de mesure dans un même projet ; les taux de desserte relèvent d’indicateurs de résultat, non d’avancement',
        'Élevé : suppose de distinguer avancement des travaux et effet sur les usagers'],
    ['Bâtiments administratifs et scolaires', 'Bâtiment, comme pour les universités',
        'Aucune adaptation structurelle ; seuls les seuils d’alerte demandent un nouveau paramétrage',
        'Faible : transposition directe'],
];

const INTERCONNEXION = [
    ['Chaîne de la dépense publique', 'Engagements, mandatements et paiements rattachés aux marchés suivis',
        'Du système financier vers l’application',
        'Convention entre administrations ; identifiant de marché commun aux deux systèmes'],
    ['Système de gestion des marchés publics', 'Références du marché, avenants, montants et délais contractuels',
        'Du système des marchés vers l’application',
        'Convention ; correspondance établie entre les numéros de marché'],
    ['Information géographique nationale', 'Fonds de plan officiels, emprises et limites administratives',
        'Vers l’application, en consultation',
        'Convention de mise à disposition ; les emprises remplacent les points de localisation actuels'],
    ['Systèmes de l’établissement bénéficiaire', 'Effectifs accueillis, capacités livrées et mises en service',
        'Vers l’application, après réception',
        'Définition partagée des indicateurs de résultat, aujourd’hui absents du dispositif'],
];

const ANALYTIQUE = [
    ['Immédiat — sur les données existantes',
        'Aucun : les grandeurs nécessaires sont déjà calculées',
        'Assortir la date d’achèvement projetée d’une fourchette plutôt que d’une valeur unique, en faisant varier l’indice temporel sur les derniers mois observés',
        'Faible ; l’intervalle est plus honnête que le point qu’il remplace'],
    ['Court terme — deux à trois ans d’exploitation',
        'Un historique mensuel continu sur plusieurs projets achevés',
        'Établir des durées de référence par nature d’ouvrage et par région, et signaler les écarts au moment de la programmation plutôt qu’au moment du retard',
        'Modéré ; les références vieillissent et doivent être révisées'],
    ['Moyen terme — cinq ans et davantage',
        'Un historique complet, comprenant les projets achevés et leurs écarts finaux',
        'Estimer le risque de retard au démarrage à partir de variables observables — saison, région, nature de l’ouvrage, taille du marché — par des modèles statistiques simples',
        'Réel : un modèle appris sur des retards passés reproduit les causes de ces retards, y compris celles qu’il faudrait corriger'],
    ['Horizon institutionnel',
        'Les précédents, et une décision d’en faire un instrument d’arbitrage',
        'Simuler l’effet d’une réallocation budgétaire entre projets sur les dates d’achèvement du programme',
        'Élevé : la décision engage la responsabilité publique et doit rester motivable devant un tiers'],
];

// ═════════════════════════════════════════════════════════ document

const doc = new Document({
    styles: { default: { document: { run: { font: POLICE, size: 24 } } } },
    sections: [{
        properties: { page: { margin: { top: 1134, bottom: 1134, left: 1417, right: 1134 } } },
        children: [
            Titre('CHAPITRE VI : RECOMMANDATIONS ET PERSPECTIVES', 1),

            P('Les chapitres précédents ont établi que l’application calcule juste, détecte ce qu’elle doit détecter et met l’information à disposition sans délai de consolidation. Ils n’établissent rien quant à sa survie. Un outil de suivi ne meurt pas d’un défaut de conception : il meurt de n’être plus alimenté, de n’avoir plus de responsable désigné lorsque son auteur s’en va, ou d’avoir été toléré à côté des circuits officiels sans jamais les remplacer. Le présent chapitre traite d’abord de ces trois risques, avant d’examiner ce que l’outil pourrait devenir.'),

            P('Les recommandations qui suivent ne sont pas générales. Chacune répond à une limite précise établie au chapitre V, et notamment à la seule condition d’hypothèse restée non vérifiée : la persistance possible des fichiers tenus en parallèle.'),

            Titre('VI.1. Recommandations pour la pérennisation de l’outil', 2),
            Titre('VI.1.1. Stratégie de déploiement et de formation des agents de l’UGP', 3),

            P('Le déploiement d’un outil de suivi se heurte à une difficulté que la qualité technique ne résout pas : pendant la période de transition, les agents doivent alimenter le nouveau système sans avoir encore cessé d’alimenter l’ancien. Cette double charge est le moment de tous les abandons. La stratégie proposée vise à la rendre aussi brève que possible et à en fixer le terme par avance.'),

            P('Le basculement général est écarté. Il suppose que l’ensemble du portefeuille soit repris en une fois, ce qui mobilise les trois sections simultanément et rend tout incident bloquant pour tous. Le déploiement progressif retenu procède par vagues, chacune subordonnée à une condition de passage vérifiable — non à une échéance de calendrier, qui serait tenue quoi qu’il advienne.'),

            Legende('Tableau VI.1 — Plan de déploiement par vagues', true),
            Tableau(['Vague', 'Périmètre', 'Durée indicative', 'Condition de passage à la vague suivante'],
                DEPLOIEMENT, [14, 32, 16, 38]),
            Legende('Source : élaboration personnelle'),

            P('La première vague porte sur le projet d’Odienné, déjà chargé dans l’application avec ses vingt périodes mensuelles, et sur deux autres projets choisis pour la diversité de leur état d’avancement. La condition de passage n’est pas que l’outil fonctionne — cela est acquis — mais que trois cycles mensuels complets se soient déroulés sans qu’aucun agent ait eu besoin de reprendre à la main un résultat produit par l’application. C’est cette absence de reprise, et elle seule, qui atteste qu’un circuit s’est substitué à un autre.'),

            P('La deuxième vague étend le dispositif aux autres projets en travaux et se conclut par un acte qui engage : la production du rapport du comité de pilotage depuis l’application. Tant que ce rapport continue d’être assemblé sur tableur, l’application reste un outil d’appoint et les fichiers parallèles conservent leur raison d’être. La troisième vague, enfin, reprend les projets en études et achevés ainsi que l’historique documentaire ; elle a moins d’urgence opérationnelle mais conditionne l’usage ultérieur de l’historique.'),

            P('Une date d’abandon des supports parallèles doit être arrêtée dès le lancement de la première vague, et non constatée à la fin. L’expérience des systèmes d’information administratifs est constante sur ce point : un double circuit toléré sans terme se pérennise, chacun conservant son fichier « pour être sûr », et la centralisation demeure formelle. C’est très exactement le risque que le chapitre V a laissé ouvert.'),

            P('La formation est différenciée par profil, parce que les besoins n’ont rien de commun. Un agent de saisie a besoin de savoir faire trois choses correctement et de savoir quoi faire lorsqu’un message de contrôle apparaît ; un directeur a besoin de savoir lire un indice et de connaître son périmètre de calcul. Une formation unique servirait mal les deux.'),

            Legende('Tableau VI.2 — Plan de formation par public', true),
            Tableau(['Public', 'Durée', 'Contenu', 'Support remis'],
                FORMATION, [22, 14, 38, 26]),
            Legende('Source : élaboration personnelle'),

            P('Deux points de ce plan méritent d’être soulignés. Le premier est la formation des directeurs à la lecture du périmètre de calcul. Le chapitre V a montré que deux bases coexistent nécessairement — l’enveloppe des ouvrages suivis, 62,78 milliards, et le montant du marché, 68,88 milliards — et qu’un lecteur non averti risque de rapprocher deux nombres qui ne se comparent pas. Ce risque ne se corrige pas par le logiciel : il se corrige par la formation et par la mention du périmètre à l’écran, désormais présente.'),

            P('Le second est la désignation d’un référent par section. Il ne s’agit pas d’un rôle supplémentaire dans l’application, qui en compte déjà sept, mais d’une fonction dans l’organisation : quelqu’un qui, dans chaque section, connaît l’outil mieux que ses collègues et vers qui l’on se tourne avant d’abandonner. L’absence d’un tel relais est, dans les administrations, la première cause de retour aux anciennes pratiques.'),

            Titre('VI.1.2. Plan de maintenance et d’évolution de l’application', 3),

            P('Une application de suivi n’est pas un livrable achevé mais un ouvrage qui demande un entretien régulier. Trois natures d’intervention doivent être distinguées, parce qu’elles n’appellent ni les mêmes délais ni les mêmes autorisations : la maintenance corrective répare ce qui est défaillant, la maintenance adaptative maintient l’application en état de fonctionner dans un environnement qui change, la maintenance évolutive lui ajoute ce qui lui manque.'),

            Legende('Tableau VI.3 — Plan de maintenance', true),
            Tableau(['Nature', 'Objet', 'Périodicité', 'Responsable'],
                MAINTENANCE, [15, 42, 21, 22]),
            Legende('Source : élaboration personnelle'),

            P('La maintenance préventive mérite une mention particulière. L’application est accompagnée de cent quarante-deux essais automatisés qui vérifient, entre autres, la justesse des indicateurs sur les valeurs de référence du projet d’Odienné et le déclenchement des onze règles d’alerte. Leur intérêt n’est pas de prouver une fois que le calcul est juste — le chapitre V s’en est chargé — mais de garantir qu’une modification ultérieure ne le fausse pas à l’insu de son auteur. Un correctif apporté au calcul du coût réel qui déferait la ventilation de la provision pour révision de prix serait signalé immédiatement. C’est la seule protection réelle contre la régression silencieuse, et elle suppose que les essais soient effectivement rejoués avant chaque mise en production, non conservés comme une pièce d’archive.'),

            P('La vérification mensuelle de la lisibilité du classeur de la mission de contrôle répond à une limite identifiée au chapitre V. L’alimentation automatique repose sur la structure de ce classeur, que l’UGP ne maîtrise pas : un intitulé modifié, une colonne déplacée, et la lecture échoue. Le risque n’est pas la perte de données, puisque la saisie manuelle demeure et que l’import ne modifie rien avant validation, mais l’interruption silencieuse d’un automatisme sur lequel on avait cessé de veiller. La clause contractuelle proposée au paragraphe suivant en constitue le remède durable.'),

            P('La continuité de service appelle deux exigences dont la seconde est souvent négligée. La sauvegarde quotidienne des données et des documents attachés est une évidence ; l’essai semestriel de restauration ne l’est pas, et c’est pourtant lui qui distingue une sauvegarde d’une illusion de sauvegarde. Une sauvegarde jamais restaurée n’a jamais été vérifiée. L’essai doit être conduit sur un environnement distinct et donner lieu à un procès-verbal adressé au coordonnateur.'),

            P('Reste le risque principal, qui n’est pas technique. Cette application a été conçue par une personne, dans le cadre d’un travail de fin d’études. Si sa maintenance repose sur cette seule personne, elle s’arrêtera le jour où celle-ci ne sera plus disponible. Trois mesures y répondent : le dépôt du code dans un espace versionné appartenant à l’UGP et non à son auteur, la documentation du code dans la langue de travail — elle est déjà en français, ce qui n’est pas indifférent pour un successeur ivoirien —, et la désignation formelle d’un développeur référent, interne ou contractuel, avec une période de recouvrement effective. Le troisième point relève de l’ancrage institutionnel et non de la technique.'),

            Titre('VI.1.3. Ancrage institutionnel : intégration dans les procédures officielles de suivi du PDU', 3),

            P('Tant que l’usage de l’application repose sur la bonne volonté des agents, il demeure réversible. L’ancrage institutionnel consiste à faire passer cet usage du registre de la pratique à celui de la procédure : ce qui est écrit dans un manuel survit au départ de ceux qui l’ont écrit. Six mesures sont proposées, ordonnées de la plus immédiate à la plus structurante.'),

            Legende('Tableau VI.4 — Mesures d’ancrage institutionnel', true),
            Tableau(['Mesure', 'Instrument', 'Autorité compétente', 'Effet attendu'],
                ANCRAGE, [22, 32, 18, 28]),
            Legende('Source : élaboration personnelle'),

            P('La première mesure est la plus simple et la plus efficace. Fixer par note de service une date d’arrêté et une date limite de saisie transforme une attente en obligation, et rend exigible l’alerte d’absence de mise à jour. Cette alerte s’est ouverte lors des essais parce que le dernier relevé datait de cinquante et un jours ; sans échéance opposable, elle constate un fait sans pouvoir en tirer conséquence. L’indicateur de fraîcheur ne prend son sens que rapporté à une norme.'),

            P('La quatrième mesure est la plus originale et sans doute la plus utile à moyen terme. La base de calcul d’avancement physique est produite chaque mois par la mission de contrôle, au titre de son marché. Rien n’empêche que le format de ce livrable soit précisé par une annexe technique, comme le sont couramment les formats de rapport. La charge du formatage passe alors du maître d’ouvrage, qui la subit, au prestataire, qui produit la donnée et à qui il en coûte peu de la produire dans une forme stable. Cette clause a vocation à figurer dans les marchés d’assistance à maîtrise d’ouvrage à venir, et peut être introduite par avenant dans les marchés en cours.'),

            P('La cinquième mesure touche à un point que les projets financés sur ressources extérieures négligent fréquemment. La donnée de suivi est une donnée publique : elle appartient au maître d’ouvrage, non au prestataire qui héberge l’application ni au bureau qui l’a développée. Une convention d’hébergement précisant la propriété, le lieu de conservation, la durée d’archivage et les conditions de restitution en fin de contrat prévient la situation, banale, où l’administration ne peut plus accéder à son propre historique.'),

            P('Ces mesures se renforcent mutuellement et perdent beaucoup à être prises isolément. Rendre la saisie obligatoire sans faire de l’application la source des restitutions officielles produit une saisie sans usage, donc une saisie de mauvaise qualité. Faire de l’application la source unique sans avoir désigné de référents expose au premier incident non traité. C’est l’ensemble qui fait système.'),

            Titre('VI.2. Perspectives d’évolution et de généralisation', 2),
            Titre('VI.2.1. Extension à d’autres programmes publics d’infrastructures', 3),

            P('La question de la généralisation se pose d’autant plus naturellement que rien, dans la conception de l’application, n’est propre à l’enseignement supérieur. La structure de données repose sur quatre notions — le projet, l’ouvrage pondéré, le relevé périodique et le décompte — dont aucune n’est spécifique au bâtiment universitaire. La méthode de la valeur acquise et sa variante temporelle sont, quant à elles, des méthodes générales de conduite de projet, indifférentes à la nature de l’ouvrage suivi.'),

            P('Ce qui demande adaptation n’est donc pas le noyau, mais la manière de décomposer un projet en ouvrages pondérés. Cette décomposition est la condition de tout le dispositif : c’est d’elle que procèdent l’avancement consolidé, les enveloppes qui servent de base aux indices, et le calendrier contractuel de chaque ouvrage.'),

            Legende('Tableau VI.5 — Transposabilité à d’autres domaines d’infrastructure', true),
            Tableau(['Domaine', 'Unité d’ouvrage pertinente', 'Adaptation requise', 'Effort estimé'],
                EXTENSION, [22, 24, 34, 20]),
            Legende('Source : élaboration personnelle'),

            P('Le domaine routier illustre bien la nature de l’adaptation. Une route ne se décompose pas en bâtiments mais en sections homogènes repérées par leurs points kilométriques, auxquelles s’ajoutent les ouvrages d’art traités séparément. La pondération par enveloppe contractuelle s’applique sans modification, et l’avancement d’une section se mesure en linéaire réalisé rapporté au linéaire prévu — ce qui reste un pourcentage. Aucun changement de structure n’est nécessaire ; seule la manière de nommer et de découper change.'),

            P('Le domaine de l’eau est le plus exigeant, pour une raison qui dépasse la technique. Un projet d’adduction mêle des réseaux linéaires et des ouvrages ponctuels, ce que la structure actuelle accepte, mais il appelle surtout des indicateurs que l’application ne connaît pas : le taux de desserte, le nombre de branchements réalisés, la population effectivement alimentée. Ce sont des indicateurs de résultat et non d’avancement, et les confondre serait une erreur d’analyse. Un ouvrage achevé à cent pour cent qui ne dessert personne n’est pas un projet réussi. Cette distinction, absente du dispositif actuel parce qu’elle n’était pas requise pour des bâtiments universitaires, devrait être introduite avant toute extension au secteur de l’eau ou de la santé.'),

            P('Une dernière condition, d’ordre structurel, mérite d’être signalée. Le portefeuille suivi aujourd’hui est celui d’un programme unique. Une extension à plusieurs programmes supposerait d’introduire la notion de programme au-dessus du projet, afin que les consolidations, les droits d’accès et les seuils d’alerte puissent être distincts d’un programme à l’autre. Les seuils sont déjà externalisés et paramétrables, ce qui lève la moitié de la difficulté : un programme routier n’a pas les mêmes tolérances de retard qu’un programme de bâtiment, et il n’aurait pas fallu que ces valeurs soient inscrites dans le code.'),

            Titre('VI.2.2. Interconnexion avec les systèmes financiers de l’État et les outils SIG', 3),

            P('L’application connaît aujourd’hui la dépense telle que l’UGP la saisit : décomptes, montants bruts, récupérations d’avance, règlements. Elle ignore la position de ces mêmes montants dans la chaîne de la dépense publique — engagement, liquidation, mandatement, paiement effectif. Or c’est précisément dans les écarts entre ces étapes que se logent les retards de règlement, dont le chapitre V a montré qu’ils concernent cinq décomptes pour 6,86 milliards de francs CFA. La double saisie qui en résulte est à la fois coûteuse et source de divergence.'),

            Legende('Tableau VI.6 — Interconnexions envisageables', true),
            Tableau(['Système', 'Donnée échangée', 'Sens de l’échange', 'Préalable indispensable'],
                INTERCONNEXION, [24, 30, 20, 26]),
            Legende('Source : élaboration personnelle'),

            P('Une précaution s’impose sur la nature de ces interconnexions. Elles ne sont pas des questions techniques mais des questions de gouvernance : l’accès aux systèmes financiers de l’État relève de conventions entre administrations, et aucun développement ne peut en tenir lieu. Il serait donc imprudent de les inscrire à un plan d’évolution comme s’il s’agissait de fonctionnalités à programmer.'),

            P('Une étape intermédiaire réaliste existe pourtant, et l’application en possède déjà le mécanisme. L’import de la base de calcul mensuelle montre qu’une alimentation par fichier d’extraction périodique, contrôlée avant intégration et rejouable sans dommage, produit l’essentiel du bénéfice attendu d’une interface applicative pour une fraction de sa complexité institutionnelle. Une extraction mensuelle des positions budgétaires des marchés suivis, transmise dans un format convenu, permettrait le rapprochement recherché sans exiger d’interface entre systèmes. C’est la voie qu’il convient d’explorer d’abord.'),

            P('S’agissant de l’information géographique, l’application dispose déjà d’une carte des projets actifs, positionnés par les coordonnées de leur université de rattachement. L’évolution utile n’est pas d’ajouter des couches d’affichage, mais de substituer aux points de localisation les emprises réelles des campus, établies à partir des fonds officiels. Le bénéfice attendu dépasse l’agrément visuel : il devient possible de croiser l’état d’avancement et le territoire, et donc de documenter la répartition régionale de l’effort d’investissement. Cette question de l’équité territoriale est l’un des motifs affichés du programme ; elle mérite d’être suivie par un indicateur plutôt que par une intuition.'),

            Titre('VI.2.3. Vers un outil d’aide à la décision : apports de l’analytique avancée au pilotage public', 3),

            P('L’application produit aujourd’hui de la mesure et de la détection. Elle constate un écart, projette une date d’achèvement, ouvre une alerte. Elle ne recommande rien et n’anticipe rien qui ne se déduise de la tendance observée. Le passage à l’aide à la décision est une perspective légitime, à condition d’être abordée dans le bon ordre — car la tentation est grande de commencer par les méthodes les plus visibles alors que la condition de leur validité n’est pas réunie.'),

            P('Cette condition est l’historique. Un modèle prédictif n’apprend que de ce qu’il a vu, et l’UGP ne dispose pas encore de séries d’observations sur des projets menés à leur terme. Toute prédiction construite aujourd’hui reposerait sur vingt relevés mensuels d’un seul chantier inachevé : elle n’aurait aucune valeur, et le danger serait qu’elle en paraisse avoir. Le calendrier proposé ci-après est donc ordonné par la disponibilité de la donnée, non par l’ambition des méthodes.'),

            Legende('Tableau VI.7 — Horizons de l’analytique appliquée au suivi', true),
            Tableau(['Horizon', 'Prérequis en données', 'Apport attendu', 'Risque et réserve'],
                ANALYTIQUE, [20, 22, 34, 24]),
            Legende('Source : élaboration personnelle'),

            P('L’apport immédiat ne demande aucune donnée nouvelle. L’application projette aujourd’hui un achèvement au 15 septembre 2028 pour le chantier d’Odienné, soit six cent quatre-vingt-deux jours au-delà de l’échéance contractuelle. Cette date unique est une extrapolation de l’indice temporel constaté ; elle serait plus juste, et surtout plus honnête, assortie d’une fourchette obtenue en faisant varier cet indice sur les derniers mois observés. Une date accompagnée de son incertitude se discute ; une date seule s’oppose.'),

            P('À moyen terme, l’exploitation de l’historique permettrait d’établir des durées de référence par nature d’ouvrage et par région, et de signaler un calendrier prévisionnel manifestement optimiste au moment où il est arrêté — c’est-à-dire au moment où la correction coûte le moins. Le chantier d’Odienné en fournit l’illustration : vingt-trois ouvrages sur trente ont démarré hors délai, dont onze n’avaient pas commencé à la date d’arrêté. Un tel schéma ne relève pas de l’aléa mais d’une planification initiale que l’expérience aurait permis de corriger.'),

            P('Une réserve doit accompagner ces perspectives, et elle n’est pas de pure forme. Un modèle appris sur des retards passés reproduit les causes de ces retards, y compris celles qu’il faudrait corriger : s’il apprend qu’une région est habituellement servie plus tard, il prédira qu’elle le sera encore, et cette prédiction pourra servir à justifier qu’elle le soit. Dans une administration, un modèle qui classe des projets ou des entreprises n’est pas un outil neutre : il oriente des décisions qui engagent des deniers publics et qui doivent pouvoir être motivées devant un tiers. Deux exigences en découlent — que le modèle reste explicable, c’est-à-dire qu’un agent puisse comprendre et contester ce qu’il propose, et que la décision demeure celle d’une personne responsable, l’outil éclairant sans arbitrer.'),

            P('C’est pourquoi le gisement le plus sûr n’est pas dans les méthodes mais dans la donnée. Cinq années de relevés mensuels complets, cohérents et tracés vaudront davantage, pour le pilotage du programme, que n’importe quel modèle appliqué à des données lacunaires. Le présent travail n’a pas d’autre ambition que d’avoir rendu cette accumulation possible : il a construit la base, non les modèles qui s’y appliqueront un jour.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
