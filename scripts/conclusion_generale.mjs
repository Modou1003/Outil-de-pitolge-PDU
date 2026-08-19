/**
 * Conclusion générale, structure des références et liste des annexes.
 *
 * Les résultats invoqués sont ceux effectivement constatés au chapitre V.
 */
import { Document, Packer, Paragraph, TextRun, WidthType, Table, TableRow, TableCell,
         AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (t, o = {}) => new TextRun({ text: t, font: POLICE, size: 24, ...o });
const AVoir = (t) => T(t, { highlight: 'yellow' });

const P = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 110, line: 264 },
});

const Titre = (t, n = 1) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: n === 1 ? 28 : 24, bold: true })],
    heading: n === 1 ? HeadingLevel.HEADING_1 : HeadingLevel.HEADING_3,
    alignment: n === 1 ? AlignmentType.CENTER : AlignmentType.LEFT,
    spacing: { before: n === 1 ? 400 : 220, after: 180 },
});

const Ref = (t) => new Paragraph({
    children: [T(t)],
    alignment: AlignmentType.JUSTIFIED,
    indent: { left: 567, hanging: 567 },
    spacing: { after: 110, line: 264 },
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

const ANNEXES = [
    ['A', 'Guides d’entretien et questionnaire d’appréciation', 'Chapitre II (diagnostic) et chapitre V (utilisabilité)'],
    ['B', 'Liste des trente ouvrages : pondération, montant contractuel et calendrier', 'Chapitres III et V'],
    ['C', 'Extrait de la base de calcul d’avancement de la mission de contrôle', 'Chapitre V (source des données de validation)'],
    ['D', 'Matrice des rôles et des permissions', 'Chapitre III (gestion des accès)'],
    ['E', 'Règles de détection des alertes et seuils paramétrables', 'Chapitres III et V'],
    ['F', 'Diagrammes de conception : cas d’utilisation, classes, séquences', 'Chapitre III'],
    ['G', 'Captures d’écran de l’application', 'Chapitres IV et V'],
    ['H', 'Chaîne de déploiement et procédure de sauvegarde', 'Chapitre IV'],
    ['I', 'Fiche de calcul manuel des indicateurs de référence', 'Chapitre V (vérification des calculs)'],
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
            Titre('CONCLUSION GÉNÉRALE'),

            P('Le Programme de décentralisation des universités conduit simultanément plusieurs opérations de construction réparties sur le territoire ivoirien. Le suivi de ces opérations reposait sur des documents transmis périodiquement et sur des situations reconstituées séparément par chaque section de l’unité de gestion, de sorte qu’aucune vision d’ensemble n’était disponible sans un travail de rassemblement préalable. C’est ce constat, formulé par le coordonnateur du programme, qui a donné son objet au présent travail : doter l’unité de gestion d’un outil de pilotage intégré.'),

            P('La démarche a suivi quatre étapes. Un diagnostic du dispositif existant a d’abord établi la nature des difficultés et recensé les besoins par profil d’utilisateur. La conception a ensuite défini la structure des données, les indicateurs à calculer et les règles de détection des dérives. Le développement a produit une application accessible par navigateur, mise en ligne et éprouvée en conditions réelles. La validation, enfin, a porté sur le projet de construction de l’Université d’Odienné, alimenté par les documents authentiques du chantier.'),

            Titre('Ce que le travail établit', 3),

            P('Le premier résultat tient à l’exactitude des traitements. Alimentée par la base de calcul d’avancement de la mission de contrôle, l’application restitue un avancement consolidé de 32,13 pour cent, valeur strictement identique à celle du rapport mensuel établi indépendamment par le groupement chargé de l’assistance à maîtrise d’ouvrage. Le décalage en délais, calculé selon la méthode de la valeur acquise temporelle, s’établit à neuf mois et six dixièmes contre neuf mois lus graphiquement par ce même groupement. La vérification ne procède donc pas d’un recoupement interne, mais d’une confrontation à un tiers qui ne connaissait pas l’outil.'),

            P('Le deuxième résultat tient au mode d’alimentation, et il n’était pas prévu au départ. Le document mensuel que la mission de contrôle établit déjà pour ses propres besoins est importé tel quel : une seule opération alimente l’avancement physique, l’avancement financier, les décomptes, le calendrier contractuel de chaque ouvrage, les indicateurs et les alertes. La transcription manuelle disparaît, et avec elle le risque qu’un taux mal recopié fausse silencieusement l’avancement consolidé. Le gain n’est pas d’abord un gain de temps, il est un gain de fiabilité.'),

            P('Le troisième résultat porte sur la nature de l’information restituée. Un avancement consolidé indique qu’un retard existe, non ce qui le cause. En enregistrant le calendrier contractuel de chaque ouvrage, l’application mesure l’écart entre le démarrage prévu et le démarrage constaté : vingt-trois des trente ouvrages du chantier d’Odienné sont ainsi désignés. L’outil produit donc, à côté des indicateurs d’effet que sont les indices de performance, un indicateur de cause, lequel désigne les ouvrages sur lesquels une décision peut porter.'),

            P('Ces résultats permettent d’apprécier les hypothèses posées en introduction. La première est vérifiée quant à l’unicité et à la fiabilité de la source d’information, et partiellement vérifiée quant à l’abandon effectif des suivis tenus en parallèle, lequel relève des pratiques et s’observe sur une durée excédant celle des essais. La seconde est vérifiée : les indicateurs sont exacts, leur consolidation est immédiate, les alertes se déclenchent et se referment conformément aux règles, et l’information parvient au décideur plus fréquemment que ne se présentent les occasions d’agir. Elle l’est dans les limites du pilotage contractuel, seul horizon auquel une donnée de périodicité mensuelle puisse prétendre.'),

            Titre('Ce que le travail n’établit pas', 3),

            P('Trois limites doivent être rappelées, non pour tempérer les résultats mais pour en fixer la portée. L’application ne comporte pas de circuit d’approbation hiérarchique : la donnée est saisie par un agent habilité et intégralement tracée, elle n’est pas visée dans l’outil. La garantie repose sur l’origine du document source, contradictoire et établi hors de l’application, et sur l’habilitation préalable de celui qui écrit.'),

            P('La validation, ensuite, n’a porté en profondeur que sur un projet. Si complètement alimenté soit-il, un cas unique ne renseigne pas sur le comportement de l’outil face à un portefeuille en charge, ni sur la diversité des situations contractuelles qu’il aurait à représenter.'),

            P('Enfin, l’application n’abrège pas la périodicité de sa source. Elle rend l’information disponible dès qu’elle existe et signale elle-même son vieillissement, mais elle ne produit pas de donnée que le chantier n’a pas transmise. Confondre disponibilité immédiate et suivi en continu serait une erreur de lecture.'),

            Titre('Perspectives', 3),

            P('Les développements à venir se hiérarchisent d’eux-mêmes. L’introduction d’un circuit d’approbation vient en premier, puisqu’elle seule lèverait la réserve portant sur la première hypothèse ; le modèle de données s’y prête déjà. Le rattachement des décomptes à une source de financement permettrait ensuite de suivre la consommation par convention, ce qu’exige un montage associant un prêt concessionnel et une contrepartie de l’État. L’extension de l’alimentation complète à d’autres projets du portefeuille éprouverait enfin la consolidation multi-projets sur laquelle repose la demande initiale du coordonnateur.'),

            P('Une perspective d’un autre ordre mérite d’être signalée. Les relevés étant rattachés aux ouvrages, rien n’interdit de calculer les indices de performance à cette maille plutôt qu’à celle du projet. Le suivi gagnerait en précision ce qu’il perdrait en simplicité, et permettrait de distinguer les ouvrages qui tirent le chantier de ceux qui le retiennent.'),

            P('La condition de pérennité de l’outil n’est toutefois pas technique. Une application de pilotage ne vit que si son alimentation est inscrite dans une procédure et si ses restitutions servent effectivement de support aux décisions. L’ancrage institutionnel — la désignation des agents responsables de la saisie, l’intégration du rapport produit au circuit de décision du comité de pilotage — décidera davantage du sort de ce travail que ses caractéristiques techniques. C’est l’objet des recommandations formulées au chapitre suivant.'),

            // ───────────────────────────────────────────── références
            Titre('RÉFÉRENCES BIBLIOGRAPHIQUES'),

            P([
                AVoir('[ Les références ci-dessous sont celles que le présent travail cite avec certitude. Y ajouter les lectures propres du chapitre I, en conservant la présentation adoptée et en vérifiant chaque entrée sur le document original. ]'),
            ]),

            Titre('Ouvrages et articles', 3),

            Ref('LIPKE, W. (2003). Schedule is Different. The Measurable News, Project Management Institute, été 2003, p. 31-34.'),
            Ref('PROJECT MANAGEMENT INSTITUTE (2021). Guide du corpus des connaissances en management de projet (Guide PMBOK), 7ᵉ édition. Newtown Square : Project Management Institute.'),
            Ref([
                AVoir('[ Compléter : les références du chapitre I sur le suivi-évaluation, les indicateurs de performance et les systèmes d’information de gestion de projet. ]'),
            ]),

            Titre('Travaux universitaires', 3),

            Ref('KOUASSI, D. A. S. (2025). Mise en place d’outils de suivi-évaluation des projets d’infrastructures de l’Université d’Odienné. Mémoire de fin d’études, Institut National Polytechnique Félix Houphouët-Boigny, Yamoussoukro.'),

            Titre('Documents de projet', 3),

            Ref('GROUPEMENT TAEP / IETF (2026). Rapport mensuel d’activité n° 14 — Travaux de construction de l’Université d’Odienné, tranche 1. Arrêté au 30 juin 2026. Programme de décentralisation des universités.'),
            Ref('GROUPEMENT TAEP / IETF (2026). Base de calcul d’avancement physique du projet — juin 2026. Annexe au rapport mensuel d’activité n° 14.'),
            Ref('MINISTÈRE DE L’ENSEIGNEMENT SUPÉRIEUR ET DE LA RECHERCHE SCIENTIFIQUE (2024). Marché de travaux n° 2024-0-00-00-2-0503/05-333 — Travaux de construction de l’Université d’Odienné, tranche 1.'),
            Ref([
                AVoir('[ Compléter : les documents du PDU cités au chapitre II — textes institutifs, rapports d’activité, comptes rendus de comité de pilotage. ]'),
            ]),

            Titre('Ressources techniques', 3),

            Ref([
                AVoir('[ Compléter : documentation des technologies retenues, citée au chapitre IV. Ne mentionner que celles qui sont effectivement discutées dans le texte. ]'),
            ]),

            // ───────────────────────────────────────────── annexes
            Titre('ANNEXES'),

            P('Les annexes rassemblent les pièces auxquelles le corps du mémoire renvoie sans les reproduire, soit qu’elles soient trop volumineuses, soit que leur consultation ne soit nécessaire qu’à la vérification.'),

            Tableau(['Annexe', 'Contenu', 'Chapitre concerné'], ANNEXES, [10, 55, 35], [0]),

            P('Deux d’entre elles méritent une attention particulière. L’annexe C, extrait de la base de calcul de la mission de contrôle, permet au lecteur de refaire lui-même le chemin qui mène du document de chantier aux valeurs restituées par l’application : elle est la pièce justificative de tout le chapitre V. L’annexe I, qui consigne les calculs manuels ayant servi de référence, en est le complément indispensable, puisqu’elle établit que la vérification a bien précédé la consultation de l’outil.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
