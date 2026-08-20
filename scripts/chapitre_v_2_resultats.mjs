/**
 * Chapitre V — V.2 Résultats de la validation et V.3 Vérification des hypothèses.
 *
 * Toutes les valeurs restituées portées aux tableaux sont celles effectivement
 * mesurées sur le projet d'Odienné chargé depuis la base de calcul de juin 2026
 * (tests tests/Feature/BaseDeCalculImportTest.php et AlerteProvocationTest.php).
 * Les valeurs de référence sont recalculées sur le même périmètre que les
 * indices — l'enveloppe des ouvrages suivis — afin que la comparaison ait un
 * sens ; les valeurs du rapport de la mission de contrôle sont rappelées en
 * commentaire lorsqu'elles reposent sur une autre base.
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
    children: [new TextRun({ text: t, font: POLICE, size: n === 2 ? 26 : 24, bold: true })],
    heading: n === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3,
    spacing: { before: 260, after: 150 },
});

const Legende = (t, avant = false) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: avant ? { before: 200, after: 100 } : { before: 80, after: 200 },
});

const Figure = (t) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true, highlight: 'yellow' })],
    alignment: AlignmentType.CENTER,
    spacing: { before: 220, after: 60 },
});

const B = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDS = { top: B, bottom: B, left: B, right: B };

const Cel = (t, { entete = false, largeur, centre = false, jaune = false, gras = false } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDS,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 60, bottom: 60, left: 80, right: 80 },
    children: [new Paragraph({
        children: [new TextRun({
            text: t, font: POLICE, size: 20,
            bold: entete || gras,
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

/** Ligne de séparation grisée à l'intérieur d'un tableau. */
const Section = (titre, largeurs) => new TableRow({
    children: [new TableCell({
        columnSpan: largeurs.length,
        width: { size: 100, type: WidthType.PERCENTAGE },
        borders: BORDS,
        shading: { type: ShadingType.CLEAR, fill: 'F2F2F2' },
        margins: { top: 50, bottom: 50, left: 80, right: 80 },
        children: [new Paragraph({
            children: [new TextRun({ text: titre, font: POLICE, size: 20, bold: true, italics: true })],
            spacing: { after: 0 },
        })],
    })],
});

const TableauSections = (entetes, blocs, largeurs, centrees = []) => new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({
            tableHeader: true,
            children: entetes.map((e, i) => Cel(e, { entete: true, largeur: largeurs[i] })),
        }),
        ...blocs.flatMap(([titre, lignes]) => [
            Section(titre, largeurs),
            ...lignes.map((l) => new TableRow({
                children: l.map((c, i) => Cel(c, {
                    largeur: largeurs[i],
                    centre: centrees.includes(i),
                    jaune: c === '…' || c.startsWith('[ '),
                })),
            })),
        ]),
    ],
});

// ══════════════════════════════════ V.2.1 — vérification des calculs

const L = 'Nul';
const C = 'Conforme';

const VERIF = [
    ['Avancement physique', [
        ['Avancement physique global pondéré', '32,13 %', '32,13 %', L, C],
        ['Avancement physique planifié pondéré', '93,25 %', '93,25 %', L, C],
        ['Écart d’avancement', '− 61,11 points', '− 61,11 points', L, C],
    ]],
    ['Valeur acquise — grandeurs monétaires (F CFA)', [
        ['Base du périmètre suivi (BAC)', '62 779 799 503', '62 779 799 503', L, C],
        ['Valeur planifiée (PV)', '58 410 762 839', '58 410 762 839', L, C],
        ['Valeur acquise (EV)', '19 231 299 229', '19 231 299 229', L, C],
        ['Coût réel (AC)', '19 101 798 996', '19 101 798 996', L, C],
        ['Écart de coût (CV = EV − AC)', '+ 129 500 233', '+ 129 500 233', L, C],
        ['Écart de délai (SV = EV − PV)', '− 39 179 463 609', '− 39 179 463 609', L, C],
        ['Coût final estimé (EAC = BAC ÷ CPI)', '62 343 395 733', '62 343 395 733', L, C],
        ['Reste à engager (ETC = EAC − AC)', '43 241 596 737', '43 241 596 737', L, C],
        ['Écart à l’achèvement (VAC = BAC − EAC)', '+ 436 403 770', '+ 436 403 770', L, C],
    ]],
    ['Valeur acquise — indices', [
        ['Indice de performance de coût (CPI)', '1,007', '1,007', L, C],
        ['Indice de performance de délai (SPI)', '0,329', '0,329', L, C],
        ['Indice temporel (SPI(t) = ES ÷ AT)', '0,517', '0,517', L, C],
        ['Valeur acquise temporelle (ES)', '312 jours', '312 jours', L, C],
        ['Temps écoulé (AT)', '603 jours', '603 jours', L, C],
        ['Décalage en délais (AT − ES)', '9 mois (rapport n° 14)', '9,6 mois', '0,6 mois', 'Conforme (méthode)'],
        ['Fin projetée à l’achèvement', '[ non produite antérieurement ]', '15 septembre 2028', '—', 'Nouveau'],
    ]],
    ['Exécution financière', [
        ['Taux de facturation cumulée', '50,07 %', '50,07 %', L, C],
        ['Taux d’exécution budgétaire (avancement financier)', '27,73 %', '27,73 %', L, C],
        ['Taux d’encaissement', '47,51 %', '47,51 %', L, C],
        ['Exposition sur avances (F CFA)', '13 625 550 493', '13 625 550 493', L, C],
        ['Écart physico-financier', '+ 4,40 points', '+ 4,4 points', '0,00 point', 'Conforme (arrondi)'],
    ]],
    ['Délai et fiabilité de la donnée', [
        ['Taux de consommation du délai à la date d’arrêté', '82,74 %', '82,74 %', L, C],
        ['Fraîcheur de la donnée au 20 août 2026', '51 jours', '51 jours', L, C],
    ]],
];

// ══════════════════════════════════ V.2.1 — alertes

const ALERTES = [
    ['Règles déclenchées par les données réelles du chantier', [
        ['Écart d’avancement', 'Constatée : 61,11 points de retard sur le planning pondéré',
            'Alerte ouverte, gravité critique', C],
        ['Retard de livraison projeté', 'Constatée : achèvement projeté au 15 septembre 2028, soit 682 jours au-delà de l’échéance',
            'Alerte ouverte, gravité critique', C],
        ['Décompte en attente', 'Constatée : décomptes n° 9 à 12 et acompte sur approvisionnement non réglés, soit 6 864 084 309 F CFA',
            'Alerte ouverte, gravité informative', C],
        ['Démarrage d’ouvrage en retard', 'Constatée : vingt-trois ouvrages démarrés hors délai, dont onze non démarrés',
            'Alerte ouverte, gravité critique', C],
        ['Absence de mise à jour', 'Constatée : dernier relevé arrêté au 30 juin 2026, soit 51 jours',
            'Alerte ouverte, gravité avertissement', C],
    ]],
    ['Règles éprouvées par provocation, puis rétablissement (scénario 8)', [
        ['Jalon manqué', 'Provoquée : jalon de réception provisoire daté du 31 mai 2026 laissé non atteint',
            'Alerte ouverte ; refermée automatiquement dès le jalon marqué atteint', C],
        ['Dépassement du délai contractuel', 'Provoquée : échéance contractuelle ramenée au 31 janvier 2026, en deçà du dernier achèvement d’ouvrage constaté',
            'Alerte ouverte ; refermée au rétablissement de l’échéance', C],
        ['Dépassement budgétaire', 'Provoquée : décaissement porté à 63 milliards, soit 91,5 % du marché actualisé, au-delà du seuil de 90 %',
            'Alerte ouverte ; refermée au rétablissement du décaissement réel', C],
        ['Décalage physico-financier', 'Provoquée : décaissement porté à 60 % pour un avancement physique de 32,13 %, soit 28 points d’avance du paiement, au-delà du seuil de 20 points',
            'Alerte ouverte ; refermée au rétablissement', C],
        ['Dérive de coût', 'Provoquée : coût réel du dernier relevé majoré de 6 milliards, portant le CPI à 0,76, sous le seuil de 0,95',
            'Alerte ouverte ; refermée au rétablissement du coût', C],
        ['Avenants cumulés excessifs', 'Provoquée : avenant de 12 milliards enregistré, soit 17,4 % du marché initial, au-delà du seuil de 15 %',
            'Alerte ouverte ; refermée à la suppression de l’avenant', C],
    ]],
];

// ══════════════════════════════════ V.2.3 — avant / après

const AVANT_APRES = [
    ['Produire une situation consolidée du portefeuille',
        'Demande adressée aux trois sections, collecte de fichiers séparés, recopie et agrégation manuelle sur tableur — [ deux à trois jours ouvrés ]',
        'Consultation du tableau de bord : les valeurs sont déjà consolidées, aucune agrégation n’est requise — affichage immédiat',
        'De plusieurs jours à l’instantané ; suppression de la recopie et du risque d’erreur qui l’accompagne'],
    ['Connaître l’avancement d’un projet donné',
        'Demande au chef de projet, attente du dernier rapport mensuel ; l’information disponible date de la dernière remontée — [ de quelques heures à plusieurs jours ]',
        'Fiche projet : l’avancement pondéré est recalculé à chaque relevé validé, et l’ancienneté de la donnée est affichée',
        'Délai d’obtention supprimé ; la fraîcheur, jusque-là implicite, devient une information explicite'],
    ['Calculer les indicateurs de performance d’un marché',
        'Non produits en routine : aucun indice de la méthode de la valeur acquise n’était calculé ; un calcul ponctuel sur tableur demandait [ une demi-journée par projet ]',
        'Dix-neuf indicateurs recalculés à chaque relevé, dont l’ensemble des grandeurs et indices de la valeur acquise',
        'Passage de l’absence de mesure à une production systématique et vérifiable'],
    ['Produire un rapport destiné au comité de pilotage',
        'Assemblage manuel des contributions des sections, mise en forme, relecture — [ trois à cinq jours ]',
        'Rapport exporté en format PDF, onze sections sélectionnables, produit à partir de la base elle-même — quelques secondes',
        'De plusieurs jours à quelques secondes ; le rapport ne peut plus diverger de la base'],
    ['Retrouver une pièce contractuelle',
        'Recherche dans les classeurs papier ou les répertoires partagés, issue incertaine — [ de quelques minutes à plusieurs heures ]',
        'Document rattaché au projet et qualifié par son type ; recherche bornée au projet — moins d’une minute',
        'Recherche bornée, traçable, et dont l’échec est lui-même une information'],
    ['Identifier un projet en dérive',
        'Détection à la lecture du rapport mensuel, soit au mieux un mois après le fait générateur',
        'Onze règles d’alerte évaluées à chaque mise à jour ; l’alerte s’ouvre et notifie les responsables concernés',
        'Du mois à la journée de la saisie ; la détection cesse de dépendre de l’attention du lecteur'],
];

// ══════════════════════════════════ V.3.3 — limites

const LIMITES = [
    ['Échantillon restreint', 'Un seul projet en phase de travaux a fait l’objet d’une validation complète',
        'Les résultats relatifs aux indicateurs de performance ne peuvent être généralisés sans réserve à des configurations différentes'],
    ['Période d’observation courte', 'Les essais couvrent quelques semaines d’usage',
        'L’effet sur la réactivité des décisions ne peut être établi ; seule la disponibilité de l’information l’est'],
    ['Absence de test de montée en charge', 'Le nombre d’utilisateurs simultanés et le volume de données demeurent faibles',
        'Le comportement de l’application à l’échelle d’un portefeuille étendu reste à éprouver'],
    ['Position de l’évaluateur', 'Les essais ont été conduits par le concepteur de l’outil',
        'Un biais favorable ne peut être exclu ; les retours des agents, extérieurs à la conception, en constituent le contrepoids'],
    ['Dépendance à la qualité des saisies', 'L’application contrôle la cohérence formelle des données, non leur sincérité',
        'Un avancement surestimé sur le terrain produit des indicateurs exacts au regard de données fausses'],
    ['Données historiques reconstituées', 'L’historique antérieur à la mise en service a été saisi rétrospectivement',
        'La vérification porte sur l’exactitude des calculs, non sur la régularité réelle des remontées'],
    ['Dualité des bases de calcul', 'Les indices de performance portent sur l’enveloppe des ouvrages suivis, 62,78 milliards, tandis que le suivi financier porte sur le marché, 68,88 milliards',
        'Toute lecture qui confondrait les deux bases fausserait l’interprétation ; la base retenue doit être rappelée à chaque restitution'],
    ['Faible pouvoir informatif du CPI', 'Sur un marché à prix unitaires, la facturation suit la valeur des travaux exécutés',
        'L’indice reste structurellement voisin de l’unité et ne peut porter le signal de dérive de coût, qui doit être cherché dans les avenants et l’écart à l’achèvement'],
    ['Sensibilité de l’import à la mise en forme du classeur', 'La lecture repose sur la structure du classeur mensuel de la mission de contrôle',
        'Une modification de cette structure interromprait l’alimentation automatique ; la saisie manuelle demeure toutefois disponible'],
];

// ══════════════════════════════════ document

const doc = new Document({
    styles: { default: { document: { run: { font: POLICE, size: 24 } } } },
    sections: [{
        properties: { page: { margin: { top: 1134, bottom: 1134, left: 1417, right: 1134 } } },
        children: [
            Titre('V.2. Résultats de la validation', 2),
            Titre('V.2.1. Résultats des tests sur données réelles et vérification des calculs', 3),

            P('Les essais ont été conduits sur les données réelles du projet d’Odienné, selon les onze scénarios du protocole. La volumétrie mise en jeu est la suivante : trente ouvrages pondérés, six cents relevés d’avancement physique et six cents relevés de valeur acquise couvrant vingt périodes mensuelles, quatorze décomptes, pour un marché de 68 879 636 067 francs CFA. Ces données proviennent intégralement de la base de calcul d’avancement arrêtée au 30 juin 2026, importée dans l’application au moyen de la fonction d’import décrite au chapitre IV.'),

            P('La vérification des calculs a suivi une règle stricte : chaque indicateur a d’abord été calculé à la main sur tableur, à partir des valeurs élémentaires, avant toute consultation de la valeur restituée par l’application.'),

            P('Les valeurs de référence sont de deux natures. Certaines proviennent du rapport mensuel de la mission de contrôle, établi indépendamment du présent travail et antérieurement à lui : la vérification y est alors une confrontation à un tiers. Les autres résultent d’un calcul conduit sur tableur à partir des mêmes valeurs élémentaires.'),

            P('Une précision de méthode s’impose avant la lecture du tableau. Les grandeurs de la valeur acquise sont rapportées à l’enveloppe des ouvrages effectivement suivis, soit 62 779 799 503 francs CFA, et non au montant du marché. L’écart entre les deux, 6 099 836 564 francs CFA, correspond aux postes que la décomposition du marché ne ventile pas en ouvrages — provision pour révision de prix et sommes à valoir — et qui ne font l’objet d’aucun relevé d’avancement physique. Les valeurs de référence ont donc été recalculées sur cette même base, faute de quoi la comparaison opposerait deux grandeurs de périmètres différents. Ce point est développé au paragraphe qui suit le tableau.'),

            Legende('Tableau V.5 — Vérification des indicateurs calculés', true),
            TableauSections(['Indicateur', 'Valeur de référence', 'Valeur restituée', 'Écart', 'Conclusion'],
                VERIF, [30, 20, 20, 13, 17], [1, 2, 3, 4]),
            Legende('Source : essais réalisés sur le projet d’Odienné ; valeurs de référence établies sur tableur et, lorsqu’elles y figurent, confrontées au rapport mensuel n° 14 du groupement TAEP/IETF'),

            P('Vingt-quatre des vingt-cinq lignes présentent un écart nul. La vingt-cinquième, le décalage en délais, appelle un commentaire de méthode plutôt qu’une réserve, et deux points du tableau méritent d’être développés.'),

            P('Le premier tient à la base de calcul, et l’examen en apprend davantage que les lignes conformes. La mission de contrôle rapporte la facturation au montant total du marché, provision pour révision de prix comprise, tandis que la décomposition en ouvrages ne couvre que 90,9 % de ce montant. Tant que les grandeurs de la valeur acquise étaient divisées par le marché entier, le coût final estimé se trouvait mécaniquement majoré de 8,86 %, et l’écart à l’achèvement qui s’en déduit devenait ininterprétable. La validation a précisément mis ce défaut au jour, et la correction consiste à énoncer explicitement le périmètre : les indices de performance portent sur l’enveloppe des ouvrages, le suivi financier du maître d’ouvrage sur le marché. Ce sont deux questions distinctes, et les confondre était la source de l’erreur.'),

            P('Le second porte sur le décalage en délais, mesuré à neuf mois et six dixièmes par l’application contre neuf mois au rapport. La mission de contrôle lit cette valeur graphiquement, comme la distance horizontale entre les deux courbes ; l’application l’obtient par interpolation entre deux points mensuels de la courbe planifiée, selon la méthode de la valeur acquise temporelle. Un écart de six dixièmes de mois entre une lecture à la règle et un calcul est le signe d’une concordance, non d’une divergence.'),

            P('Une observation s’impose enfin sur l’indice de performance de coût, restitué à 1,007. Cette valeur, proche de l’unité, ne traduit pas une maîtrise remarquable de la dépense : l’entreprise facture la valeur des travaux qu’elle a exécutés, de sorte que le coût réel suit mécaniquement la valeur acquise. Sur un marché à prix unitaires, cet indice ne peut s’écarter durablement de un, et le signal de coût doit être cherché ailleurs — dans les avenants et dans l’écart entre le marché et le coût final estimé. L’indice conserve néanmoins son utilité comme contrôle de cohérence : une divergence signalerait une facturation déconnectée de l’avancement constaté. C’est d’ailleurs l’écart à l’achèvement, positif de 436 403 770 francs CFA, qui porte ici l’information utile : au rythme de dépense constaté, l’achèvement du périmètre suivi se ferait légèrement en deçà de son enveloppe.'),

            P('La validation a par ailleurs révélé un second défaut, de nature différente et sans effet sur les montants. La courbe en S consolidait les ouvrages par une moyenne simple, alors que l’avancement affiché en tête de fiche projet les consolide par leur pondération. À la date d’arrêté, la courbe annonçait ainsi 25,3 % là où l’avancement du projet indiquait 32,13 %, pour la seule raison que les ouvrages les plus lourds sont aussi les plus avancés. La consolidation de la courbe a été rendue pondérée, en cohérence avec la convention retenue au chapitre III. Les valeurs portées au tableau V.5 sont celles obtenues après cette correction.'),

            Titre('Vérification du déclenchement des alertes', 3),

            P('Le dispositif d’alerte compte onze règles. Cinq d’entre elles se déclenchent sur les seules données réelles du chantier, sans qu’il ait été nécessaire de provoquer quoi que ce soit : le projet d’Odienné présente en effet un écart d’avancement considérable, une fin projetée très au-delà de l’échéance, des décomptes en attente de règlement, vingt-trois ouvrages démarrés hors délai et une donnée vieillissante. Les six autres supposent des situations que le chantier ne présente pas à la date d’arrêté ; elles ont été éprouvées par provocation délibérée, selon le scénario 8 du protocole, chaque provocation étant suivie du rétablissement de la situation antérieure afin de vérifier la fermeture automatique de l’alerte.'),

            Legende('Tableau V.6 — Vérification du déclenchement des alertes', true),
            TableauSections(['Règle de détection', 'Situation', 'Comportement observé', 'Conclusion'],
                ALERTES, [22, 34, 32, 12], [3]),
            Legende('Source : essais réalisés sur le projet d’Odienné ; provocations conduites sur le jeu de données réel, non sur un projet fabriqué'),

            P('Les onze règles se déclenchent conformément à leur définition et les six règles provoquées se referment d’elles-mêmes dès la situation rétablie, sans intervention. Le choix de conduire les provocations sur le jeu de données réel, plutôt que sur un projet créé pour l’occasion, n’est pas indifférent : il permet de vérifier qu’une règle ne se déclenche pas déjà pour une autre raison, et donc que l’ouverture observée est bien imputable à la condition provoquée. Chacun de ces essais est consigné dans la suite de tests automatisés de l’application et peut être rejoué.'),

            Figure('[ FIGURE à insérer — Capture d’écran de la fiche du projet d’Odienné ]'),
            Legende('Figure V.1 — Fiche du projet d’Odienné'),
            Legende('Source : application développée'),

            Figure('[ FIGURE à insérer — Capture d’écran de la courbe en S du projet d’Odienné ]'),
            Legende('Figure V.2 — Courbe en S du projet d’Odienné'),
            Legende('Source : application développée'),

            Figure('[ FIGURE à insérer — Capture d’écran de la courbe de la valeur acquise ]'),
            Legende('Figure V.3 — Courbes de la valeur acquise du projet d’Odienné'),
            Legende('Source : application développée'),

            Titre('V.2.3. Comparaison avant / après', 3),

            P('L’appréciation de l’apport de l’outil suppose de disposer d’un point de comparaison. Les modalités du circuit antérieur ont été relevées avant la mise en service, dans le cadre du diagnostic conduit au chapitre II ; les durées qui les accompagnent sont des ordres de grandeur recueillis auprès des agents et demandent à être confirmés.'),

            P('La comparaison porte sur six tâches courantes du travail de suivi, retenues parce qu’elles se répètent et que leur coût cumulé est significatif.'),

            Legende('Tableau V.8 — Comparaison des situations antérieure et actuelle', true),
            Tableau(['Tâche', 'Situation antérieure', 'Avec l’application', 'Gain observé'],
                AVANT_APRES, [20, 30, 28, 22]),
            Legende('Source : Auteur'),

            P('Le troisième cas se distingue des autres. Il ne s’agit pas d’un gain de temps mais d’un changement de nature : les indices de la méthode de la valeur acquise n’étaient pas calculés, faute de temps et faute d’une base de données qui les rende calculables. Le gain n’est donc pas de produire plus vite une information qui existait, mais de produire une information qui n’existait pas. Le sixième cas relève de la même logique : la détection d’une dérive cessait d’être un acte de lecture pour devenir une propriété du système.'),

            P('Une précaution de lecture s’impose. Les durées relevées après mise en service correspondent à un usage encore récent, antérieur à la pleine maîtrise de l’outil par les agents. Elles constituent donc une estimation prudente : l’écart réel est vraisemblablement supérieur à celui mesuré. Inversement, elles portent sur un périmètre restreint et ne sauraient être extrapolées sans prudence à l’ensemble du portefeuille.'),

            Titre('V.3. Vérification des hypothèses et appréciation des résultats', 2),

            P('Deux hypothèses ont été posées en introduction. Leur vérification suppose d’énoncer, avant d’examiner les résultats, ce qui vaudrait confirmation et ce qui vaudrait infirmation. Sans ce préalable, toute observation peut être lue comme favorable.'),

            Titre('V.3.1. Hypothèse 1 : la base centralisée résout-elle la dispersion des données de suivi ?', 3),

            P('L’hypothèse énonce que la mise en place d’une base de données centralisée, alimentée par les différentes sections de l’UGP, résout la dispersion des données et garantit la fiabilité et l’unicité de l’information de suivi.'),

            P('Elle serait tenue pour confirmée si quatre conditions étaient réunies.'),
            Puce('Les trois sections alimentent effectivement la même base, chacune dans son domaine, sans tenue parallèle de fichiers séparés.'),
            Puce('Une situation consolidée du portefeuille s’obtient par simple consultation, sans opération d’agrégation manuelle.'),
            Puce('Deux restitutions produites à la même date présentent des valeurs identiques, l’unicité de la source excluant la divergence.'),
            Puce('Toute valeur restituée est rattachable à une saisie identifiée, datée.'),

            P('Elle devrait être tenue pour infirmée, ou pour partiellement vérifiée, si la tenue de fichiers parallèles subsistait, ou si la reconstitution d’une situation d’ensemble continuait d’exiger un travail manuel.'),

            P('La première condition est partiellement vérifiée. Le partage des écritures entre sections est effectivement établi et contrôlé : la section technique inscrit les relevés d’avancement physique, la section financière les décomptes et les coûts réels, la section des marchés les pièces contractuelles et les avenants, et les essais du scénario 9 ont confirmé qu’une écriture tentée hors de son domaine est refusée. La fonction d’import de la base de calcul mensuelle respecte elle-même cette répartition : conduite par un agent de la section technique, elle inscrit les avancements sans toucher aux coûts, qu’un import ultérieur mené par la section financière vient compléter. Mais l’abandon effectif des fichiers parallèles ne se décrète pas et ne s’observe pas en quelques semaines. La condition est donc remplie du point de vue du dispositif, et reste à confirmer du point de vue des pratiques.'),

            P('La deuxième condition est vérifiée. La situation consolidée du portefeuille s’affiche par simple consultation du tableau de bord, sans opération d’agrégation : les valeurs y sont recalculées à l’enregistrement de chaque relevé validé, et non à la demande. Le chargement du projet d’Odienné en fournit la démonstration la plus nette : six cents relevés physiques, six cents relevés financiers et quatorze décomptes ont produit, sans aucune reprise manuelle, un avancement consolidé de 32,13 % identique à celui du rapport de la mission de contrôle.'),

            P('La troisième condition est vérifiée. Les valeurs de la fiche projet, celles du rapport exporté et celles du tableau de bord procèdent des mêmes enregistrements et des mêmes fonctions de calcul ; la vérification conduite le même jour sur les trois restitutions n’a fait apparaître aucune divergence. Cette propriété n’est pas le fruit d’une précaution particulière : elle découle de ce qu’aucune de ces restitutions ne recalcule pour son compte, chacune lisant la valeur produite par la même fonction. C’est précisément ce que la dispersion antérieure ne permettait pas.'),

            P('La quatrième condition est vérifiée. Chaque valeur restituée se rattache à un enregistrement portant sa date de mesure, sa période, son auteur et son statut de validation ; l’historique des saisies d’un ouvrage est consultable depuis sa fiche. La correction apportée au calcul du coût réel en fournit une illustration : il a été possible d’établir que la provision pour révision de prix, jusque-là absente du coût réel, avait été facturée à hauteur de 91 522 879 francs CFA, et de la ventiler entre les ouvrages au prorata de leur facturation. Une telle vérification aurait été impraticable sur des fichiers dispersés.'),

            P('L’hypothèse est donc confirmée sur ses trois conditions vérifiables à ce stade, et partiellement sur la première. La dispersion technique de la donnée est résolue : il existe désormais une source unique, partagée, tracée, et dont les restitutions ne peuvent diverger. La dispersion organisationnelle — l’habitude de tenir en parallèle son propre fichier — relève de la conduite du changement et non de l’outil ; le chapitre VI y revient. Cette confirmation partielle est un résultat plus crédible qu’une confirmation totale que la durée de l’observation ne permettrait pas d’étayer.'),

            Titre('V.3.2. Hypothèse 2 : l’outil intégré fournit-il une vision synthétique et actualisée du programme ?', 3),

            P('L’hypothèse énonce qu’un outil intégrant tableau de bord, indicateurs calculés automatiquement et alertes fournit aux décideurs une vision synthétique et actualisée de l’état d’avancement, et améliore ainsi la réactivité du pilotage.'),

            P('Elle serait tenue pour confirmée si quatre conditions étaient réunies.'),
            Puce('Les indicateurs restitués sont exacts, au sens du critère de fiabilité arrêté au protocole.'),
            Puce('Le tableau de bord reflète l’état courant sans délai de consolidation, une donnée validée s’y répercutant immédiatement.'),
            Puce('Les alertes se déclenchent conformément aux règles établies et se closent au rétablissement de la situation.'),
            Puce('Les agents et responsables consultés reconnaissent y trouver l’information nécessaire à leur niveau de décision.'),

            P('La première condition est vérifiée. Le tableau V.5 fait apparaître un écart nul sur vingt-quatre des vingt-cinq indicateurs vérifiés, la vingt-cinquième relevant d’une différence de méthode de lecture et non d’une erreur de calcul. Ce résultat mérite toutefois d’être lu pour ce qu’il est : il n’a pas été acquis d’emblée. La confrontation aux valeurs de référence a mis au jour quatre défauts de calcul — une base erronée pour le coût final estimé, l’omission de la provision pour révision de prix dans le coût réel, un double décompte de l’avance de démarrage dans l’encaissement et l’omission de l’acompte sur approvisionnement dans l’exposition sur avances — auxquels s’est ajouté le défaut de consolidation de la courbe en S. C’est la validation elle-même qui a produit ce résultat, et c’est en cela qu’elle vaut : un dispositif d’essais qui ne révèle rien n’a rien éprouvé.'),

            P('La deuxième condition est vérifiée. Les indicateurs sont recalculés à l’enregistrement de la donnée qui les détermine, non selon une périodicité de consolidation : une saisie validée modifie immédiatement l’avancement du projet, les grandeurs de la valeur acquise et les cartes du tableau de bord. Il n’existe aucun traitement différé entre la saisie et la restitution, et l’application n’a donc pas d’état intermédiaire dans lequel les écrans afficheraient une situation périmée.'),

            P('La troisième condition est vérifiée. Le tableau V.6 établit que les onze règles se déclenchent conformément à leur définition, et que les six règles provoquées se referment d’elles-mêmes dès la situation rétablie. Le contrôle de la fermeture importe autant que celui de l’ouverture : un dispositif qui n’ouvrirait que des alertes sans jamais les clore produirait rapidement un bruit dont les destinataires se détourneraient.'),

            P('La quatrième condition n’est vérifiée qu’en partie et appelle une réserve méthodologique. L’amélioration de la réactivité du pilotage suppose d’observer des décisions prises plus tôt qu’elles ne l’auraient été, ce qu’une période d’essai de quelques semaines ne permet pas d’établir. Ce qui peut être vérifié ici, c’est la mise à disposition plus précoce de l’information : la comparaison du tableau V.8 montre que la détection d’une dérive passe du rythme mensuel du rapport au jour de la saisie, et que la production d’une situation consolidée passe de plusieurs jours à l’instantané. La transformation de cette disponibilité en décision effective relève de l’organisation et ne pourra être appréciée qu’à plus longue échéance.'),

            P('L’hypothèse est donc confirmée pour ce qui concerne la vision synthétique et actualisée, qui constitue son objet propre, et laissée en suspens pour ce qui concerne l’amélioration de la réactivité, qui en est une conséquence attendue mais non observable dans le temps de ce travail. Là encore, la restriction vaut mieux qu’une affirmation que rien n’étaierait.'),

            Figure('[ FIGURE à insérer — Capture d’écran d’une alerte déclenchée sur le projet d’Odienné ]'),
            Legende('Figure V.4 — Alerte déclenchée lors des essais'),
            Legende('Source : application développée'),

            Titre('V.3.3. Limites identifiées et points d’amélioration', 3),

            P('La portée des résultats obtenus doit être appréciée au regard de plusieurs limites, dont certaines tiennent au dispositif de validation lui-même, d’autres à l’outil, et trois d’entre elles ont été révélées par les essais.'),

            Legende('Tableau V.9 — Limites de la validation et de l’outil', true),
            Tableau(['Limite', 'Nature', 'Conséquence sur les conclusions'],
                LIMITES, [24, 36, 40]),
            Legende('Source : élaboration personnelle'),

            P('La cinquième limite mérite un développement, car elle touche à la portée même de la solution. L’outil garantit qu’une donnée saisie sera correctement traitée, restituée et tracée. Il ne garantit pas qu’elle soit conforme à la réalité du chantier. Cette garantie-là relève du contrôle exercé sur le terrain et de la validation hiérarchique, non du logiciel. Le dispositif de validation en deux temps retenu au chapitre III y contribue sans s’y substituer.'),

            P('La septième limite, révélée par les essais, appelle une recommandation de restitution plutôt qu’une correction. Deux périmètres coexistent nécessairement : celui des ouvrages, sur lequel seules les grandeurs de la valeur acquise ont un sens, et celui du marché, sur lequel se lisent la facturation et l’encaissement. L’application affiche désormais la base retenue sous le tableau de la valeur acquise, avec la mention de son écart au marché ; c’est le minimum requis pour qu’un lecteur non averti ne rapproche pas deux nombres qui ne se comparent pas.'),

            P('Les points d’amélioration recensés à l’issue des essais sont de trois ordres. Le premier concerne le suivi des avenants, aujourd’hui enregistrés mais dont l’effet sur le calendrier contractuel mériterait d’être répercuté sur les calendriers d’ouvrages. Le deuxième concerne la robustesse de l’import, dont la lecture dépend de la mise en forme du classeur mensuel : un contrôle de structure plus explicite, signalant à l’avance les intitulés non reconnus, éviterait au chef de projet d’avoir à en découvrir l’effet après coup. Le troisième concerne la saisie en conditions de connexion dégradée, fréquente sur les chantiers éloignés, qui gagnerait à être possible hors ligne avec synchronisation différée.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
