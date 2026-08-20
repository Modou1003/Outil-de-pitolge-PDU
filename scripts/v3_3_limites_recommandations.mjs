/**
 * V.3.3 — Limites identifiées, recommandations et perspectives d'amélioration.
 *
 * Chaque recommandation répond à une limite établie, et non à une bonne
 * pratique générale. Les éléments vérifiables — index de base de données,
 * classeurs mensuels archivés, absence de règle d'alerte sur l'exposition —
 * ont été contrôlés sur le dépôt et sur les données réelles.
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

const Titre = (t, n = 3) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: n === 2 ? 26 : 24, bold: true })],
    heading: n === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3,
    spacing: { before: 280, after: 150 },
});

const Legende = (t, avant = false) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: avant ? { before: 200, after: 100 } : { before: 80, after: 200 },
});

const B = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDS = { top: B, bottom: B, left: B, right: B };

const Cel = (t, { entete = false, largeur, centre = false } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDS,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 60, bottom: 60, left: 80, right: 80 },
    children: [new Paragraph({
        children: [new TextRun({ text: t, font: POLICE, size: 19, bold: entete })],
        alignment: (entete || centre) ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 230 },
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
            children: l.map((c, i) => Cel(c, { largeur: largeurs[i], centre: centrees.includes(i) })),
        })),
    ],
});

// ════════════════════════════════════════════════ limites

const LIMITES = [
    ['Échantillon restreint',
        'Un seul projet en phase de travaux a fait l’objet d’une validation complète',
        'Les résultats relatifs aux indicateurs de performance ne peuvent être généralisés sans réserve à des configurations différentes'],
    ['Période d’observation courte',
        'Les essais couvrent quelques semaines d’usage',
        'L’effet sur la réactivité des décisions ne peut être établi ; seule la disponibilité de l’information l’est'],
    ['Absence de test de montée en charge',
        'Le nombre d’utilisateurs simultanés et le volume de données demeurent faibles',
        'Le comportement de l’application à l’échelle d’un portefeuille étendu reste à éprouver'],
    ['Dualité des bases de calcul',
        'Les indices de performance portent sur l’enveloppe des ouvrages suivis, 62,78 milliards, tandis que le suivi financier porte sur le marché, 68,88 milliards',
        'Toute lecture qui confondrait les deux bases fausserait l’interprétation ; la base retenue doit être rappelée à chaque restitution'],
    ['Faible pouvoir informatif du CPI',
        'Sur un marché à prix unitaires, la facturation suit la valeur des travaux exécutés',
        'L’indice reste structurellement voisin de l’unité et ne peut porter le signal de dérive de coût, qui doit être cherché dans les avenants et l’écart à l’achèvement'],
    ['Sensibilité de l’import à la mise en forme du classeur',
        'La lecture repose sur la structure du classeur mensuel de la mission de contrôle',
        'Une modification de cette structure interromprait l’alimentation automatique ; la saisie manuelle demeure toutefois disponible'],
];

// ════════════════════════════════════════════════ recommandations

const RECOS = [
    ['R1', 'Échantillon restreint',
        'Rejouer les classeurs mensuels antérieurs de la mission de contrôle — février, mars, avril et mai 2025 — déjà archivés à l’UGP, et vérifier que l’application reproduit à chacune de ces dates l’avancement publié. Étendre ensuite la validation à un projet en études et à un projet achevé.',
        'Immédiat ; aucun coût, les sources existent'],
    ['R2', 'Période d’observation courte',
        'Tirer du journal d’activité deux mesures d’adoption : le nombre de saisies par section et par mois, et le délai séparant la date d’arrêté de la date de saisie effective. Ce second délai est le témoin direct de la réactivité recherchée. Le relever à six mois, puis à douze.',
        'Six et douze mois après mise en service'],
    ['R3', 'Absence de test de montée en charge',
        'Constituer un jeu d’essai synthétique — cinquante projets, trente ouvrages, trente-six périodes, soit cinquante-quatre mille relevés — et rejouer le protocole de mesure des temps de traitement. Deux corrections sont anticipables : un index composé sur l’ouvrage et la période, et une consolidation des courbes calculée en base plutôt que par transmission de l’ensemble des relevés au navigateur.',
        'Avant tout déploiement élargi'],
    ['R4', 'Dualité des bases de calcul',
        'À court terme, afficher en permanence, auprès de toute grandeur de la valeur acquise, le taux de couverture du marché par le périmètre suivi, soit 91,14 %. À terme, rattacher les postes non ventilés — provision pour révision de prix, sommes à valoir — à un ouvrage technique non pondéré, de sorte que la somme des enveloppes égale le marché et que la dualité disparaisse.',
        'Court terme, puis évolution structurelle'],
    ['R5', 'Faible pouvoir informatif du CPI',
        'Reléguer l’indice de coût au rang de contrôle de cohérence, et lui substituer comme signal de coût trois indicateurs : le taux d’avenants cumulés, la part consommée de la provision pour révision de prix, et une estimation du coût de la prolongation. Présenter le coût final estimé sous forme de scénarios plutôt que d’une valeur unique.',
        'Court terme'],
    ['R6', 'Sensibilité de l’import',
        'Faire porter le contrôle préalable sur la structure du classeur et non sur son seul contenu : énumérer, avant toute écriture, les intitulés attendus qui n’ont pas été reconnus, et journaliser les libellés non appariés. Prévoir un format pivot simple en solution de repli, et adosser la stabilité du format à une annexe technique du marché d’assistance à maîtrise d’ouvrage.',
        'Court terme'],
    ['R7', 'Lacune révélée par l’interprétation',
        'Ajouter une douzième règle d’alerte portant sur l’exposition sur avances, rapportée à la valeur acquise. Aucune des onze règles existantes ne surveille cette grandeur, alors qu’elle constitue, sur le chantier d’Odienné, le premier risque financier du dossier.',
        'Court terme'],
];

// ════════════════════════════════════════════════ perspectives

const PERSPECTIVES = [
    ['Alerte sur l’exposition sur avances',
        'Ouvrir une alerte lorsque le solde des avances non récupérées dépasse une part paramétrable de la valeur acquise',
        'Le risque de non-recouvrement croît avec la durée du retard ; il n’est aujourd’hui visible que si l’on va le chercher'],
    ['Fourchette sur la date projetée',
        'Assortir la date d’achèvement d’un intervalle, en faisant varier l’indice temporel sur les derniers mois observés',
        'Une date accompagnée de son incertitude se discute ; une date seule s’oppose'],
    ['Effet calendaire des avenants',
        'Répercuter la prolongation accordée par avenant sur les calendriers contractuels des ouvrages, et non sur la seule échéance du projet',
        'Les retards de démarrage par ouvrage, principal signal du chantier, deviendraient exacts après avenant'],
    ['Coût de la prolongation',
        'Estimer, à partir de la durée projetée, la révision de prix et les frais d’immobilisation à attendre',
        'C’est le risque de coût réel d’un marché indexé, et aucun indice de la valeur acquise ne le fait apparaître'],
    ['Indicateurs de résultat',
        'Distinguer l’avancement des travaux de l’effet produit : capacités livrées, mises en service, effectifs accueillis',
        'Un ouvrage achevé qui n’est pas mis en service n’est pas un projet réussi ; la distinction est indispensable à toute extension hors du bâtiment'],
    ['Saisie en connexion dégradée',
        'Permettre la saisie hors ligne avec synchronisation différée',
        'La condition de connexion est fréquente sur les chantiers éloignés et conditionne la fraîcheur de la donnée'],
    ['Durées de référence issues de l’historique',
        'Établir, à mesure que l’historique s’accumule, des durées de référence par nature d’ouvrage et par région',
        'Permettrait de signaler un calendrier prévisionnel optimiste au moment où il est arrêté, et non au moment du retard'],
];

// ════════════════════════════════════════════════ document

const doc = new Document({
    styles: { default: { document: { run: { font: POLICE, size: 24 } } } },
    sections: [{
        properties: { page: { margin: { top: 1134, bottom: 1134, left: 1417, right: 1134 } } },
        children: [
            Titre('V.3.3. Limites identifiées et points d’amélioration', 3),

            P('La portée des résultats obtenus doit être appréciée au regard de plusieurs limites, dont certaines tiennent au dispositif de validation lui-même et d’autres à l’outil. Trois d’entre elles ont été révélées par les essais.'),

            Legende('Tableau V.11 — Limites de la validation et de l’outil', true),
            Tableau(['Limite', 'Nature', 'Conséquence sur les conclusions'],
                LIMITES, [24, 34, 42]),
            Legende('Source : élaboration personnelle'),

            P('À chacune de ces limites correspond une mesure d’amélioration précise. Il n’a pas paru utile d’en formuler de générales : une recommandation qui ne se rattache pas à un manque constaté n’engage à rien et ne se vérifie pas.'),

            Legende('Tableau V.12 — Recommandations d’amélioration de l’outil', true),
            Tableau(['N°', 'Limite visée', 'Mesure recommandée', 'Horizon'],
                RECOS, [5, 18, 55, 22]),
            Legende('Source : élaboration personnelle'),

            Titre('Quatre recommandations développées', 3),

            P('La première recommandation mérite d’être soulignée parce qu’elle est immédiate et sans coût. La mission de contrôle produit sa base de calcul depuis le début du chantier, et les classeurs de février, mars, avril et mai 2025 sont archivés à l’UGP. Les importer successivement éprouverait trois propriétés en une seule opération : l’application reproduit-elle les avancements publiés à quatre autres dates, l’import répété ajoute-t-il des enregistrements indus, et la reconstitution d’un historique mensuel est-elle fidèle. La validation passerait ainsi d’un point de mesure à cinq, ce qui change la nature de la preuve, sans qu’aucune donnée nouvelle ait à être produite.'),

            P('La troisième appelle deux précisions techniques que les essais ont rendues visibles. La fiche du projet d’Odienné s’assemble en 1,4 seconde pour douze cents relevés, contre 65 millisecondes pour le tableau de bord consolidé : l’écart tient à ce que l’intégralité des relevés est transmise au navigateur, qui les agrège pour tracer les courbes. Ce mode de fonctionnement, satisfaisant pour un projet, croît linéairement avec le volume ; une agrégation opérée en base, ne transmettant que les points de la courbe, rendrait le coût indépendant du nombre de relevés. Par ailleurs, la table des relevés physiques porte un index sur le projet et la période, mais aucun index composé sur l’ouvrage et la période, alors que c’est la lecture la plus fréquente de l’application. Ces deux corrections sont légères et se conduisent avant, non après, la montée en charge.'),

            P('La quatrième propose une solution de fond à un défaut que le chapitre a exposé en détail. Tant que les enveloppes d’ouvrages ne couvrent que 91,14 % du marché, deux bases coexistent et toute restitution doit préciser laquelle elle emploie. Rattacher les postes non ventilés à un ouvrage technique — sans pondération physique, donc sans effet sur l’avancement, mais portant son enveloppe — ferait coïncider la somme des enveloppes et le montant du marché. La dualité disparaîtrait alors non par convention de lecture mais par construction, ce qui est toujours préférable.'),

            P('La cinquième tire les conséquences d’un constat que l’analyse a établi : sur un marché à prix unitaires, l’indice de performance de coût ne peut porter le signal qu’on attend de lui. Le coût final estimé calculé à partir de cet indice s’établit à 62 343 395 733 francs, tandis que le coût final estimé supposant que le reste s’exécutera au budget prévu s’établit à 62 650 299 270 francs : deux valeurs quasi confondues, l’une comme l’autre voisines de la base. Cette convergence n’est pas une confirmation, c’est la marque d’une méthode qui, ici, ne discrimine pas. Le risque de coût de ce chantier ne se lit pas dans ces nombres mais dans la prolongation attendue, qui appellera une révision de prix sur une durée doublée et des frais d’immobilisation que la méthode de la valeur acquise ne modélise pas. Présenter deux scénarios de coût final plutôt qu’une valeur unique, et adjoindre une estimation du coût de la prolongation, restituerait à cette partie de l’analyse le pouvoir informatif qui lui manque.'),

            P('La septième, enfin, ne répond pas à une limite du dispositif de validation mais à une lacune que l’interprétation des résultats a fait apparaître. L’exposition sur avances atteint 13 625 550 493 francs, soit 71 % de la valeur des travaux effectivement réalisés et près de 20 % du marché. Tant que le chantier progresse, cette avance se récupère sur les décomptes ; si l’exécution s’interrompt — et un achèvement projeté à septembre 2028 rend l’hypothèse discutable —, elle devient une créance à recouvrer sur une entreprise qui n’a plus de décompte à percevoir. Or aucune des onze règles d’alerte ne surveille cette grandeur. Une douzième règle, rapportant le solde des avances non récupérées à la valeur acquise, comblerait le principal angle mort du dispositif.'),

            Titre('Perspectives d’amélioration', 3),

            P('Au-delà des mesures répondant aux limites constatées, sept évolutions ont été recensées à l’issue des essais. Elles sont ordonnées par la valeur qu’elles ajouteraient au pilotage, non par leur difficulté de réalisation.'),

            Legende('Tableau V.13 — Perspectives d’amélioration de l’outil', true),
            Tableau(['Évolution', 'Objet', 'Justification'],
                PERSPECTIVES, [24, 38, 38]),
            Legende('Source : élaboration personnelle'),

            P('Trois de ces perspectives se rattachent à un même constat : l’outil mesure aujourd’hui l’avancement des travaux, et il le mesure bien, mais il ne mesure ni le coût du temps perdu, ni l’effet produit par les ouvrages achevés, ni l’incertitude de ses propres projections. Ces trois manques n’en sont pas au regard de l’objet du présent travail, qui était de constituer une base fiable et de restituer un état d’avancement. Ils délimitent en revanche ce qui sépare un outil de suivi d’un véritable instrument d’aide à la décision, et ils tracent le programme des développements ultérieurs.'),

            P('Une remarque, enfin, sur l’ordre dans lequel ces évolutions devraient être conduites. Les deux premières — l’alerte sur l’exposition et la fourchette sur la date projetée — n’exigent aucune donnée nouvelle : elles exploitent ce que l’application détient déjà et pourraient être livrées en quelques jours. Les deux dernières supposent au contraire une accumulation d’historique qui demandera plusieurs années. Il serait imprudent d’engager les secondes avant d’avoir livré les premières, et cette prudence vaut recommandation.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
