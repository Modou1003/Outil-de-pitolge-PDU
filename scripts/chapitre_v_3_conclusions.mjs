/**
 * Chapitre V — V.3.1 et V.3.2 : confrontation des conditions de confirmation
 * aux résultats des essais.
 *
 * Les conditions sont celles posées par l'étudiant avant les essais ; seule
 * leur confrontation aux résultats est rédigée ici.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType } from 'docx';
import { writeFileSync } from 'fs';

const POLICE = 'Times New Roman';
const T = (t, o = {}) => new TextRun({ text: t, font: POLICE, size: 24, ...o });
const AVoir = (t) => T(t, { highlight: 'yellow' });

const P = (t) => new Paragraph({
    children: Array.isArray(t) ? t : [T(t)],
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 110, line: 264 },
});

const Titre = (t, n = 3) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: n === 2 ? 26 : 24, bold: true })],
    heading: n === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3,
    spacing: { before: 220, after: 120 },
});

const Legende = (t, avant = false) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 20, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: avant ? { before: 160, after: 90 } : { before: 70, after: 160 },
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

const H1 = [
    ['1', 'Les trois sections alimentent la même base, sans fichiers parallèles',
        'La base est unique et les compétences y sont séparées : la section des marchés instruit les avenants sans accéder aux décomptes, la section financière l’inverse. Le classeur de la mission de contrôle subsiste toutefois en amont, non comme fichier parallèle mais comme document source',
        'Vérifiée pour l’outil ; l’abandon durable des suivis parallèles relève de l’usage et ne peut être constaté sur quelques semaines'],
    ['2', 'Situation consolidée obtenue par simple consultation',
        'L’avancement pondéré des trente ouvrages est recalculé à chaque saisie et restitué sans agrégation manuelle ; il coïncide exactement avec celui de la mission de contrôle',
        'Vérifiée'],
    ['3', 'Deux restitutions à la même date présentent des valeurs identiques',
        'Le rapport exporté et le tableau de bord sont produits à partir des mêmes enregistrements et affichent les mêmes valeurs, l’unicité de la source excluant la divergence',
        'Vérifiée'],
    ['4', 'Toute valeur restituée est rattachable à une saisie identifiée et datée',
        'Chaque relevé porte sa période, sa date de mesure et l’agent qui l’a enregistré, y compris lorsqu’il provient d’un import ; le journal d’activité conserve la trace horodatée de chaque écriture',
        'Vérifiée'],
];

const H2 = [
    ['1', 'Exactitude des indicateurs restitués',
        'L’avancement consolidé, l’avancement planifié, l’écart d’avancement, le coût réel et le taux de consommation du délai sont restitués sans écart avec les valeurs de référence ; le décalage en délais est calculé à neuf mois et six dixièmes contre neuf mois lus graphiquement par la mission de contrôle',
        'Vérifiée'],
    ['2', 'Le tableau de bord reflète l’état courant sans délai de consolidation',
        'Les indicateurs sont recalculés à l’enregistrement de la saisie ; aucune opération de consolidation différée n’intervient. La fraîcheur de la donnée reste en revanche celle de la source : quarante-huit jours à la date des essais, ancienneté que l’outil affiche lui-même',
        'Vérifiée pour le délai de consolidation, sous réserve de la périodicité de la source'],
    ['3', 'Les alertes se déclenchent et se closent conformément aux règles',
        'Cinq des onze règles se sont ouvertes sur les seules données réelles du chantier, sans provocation ; la fermeture automatique a été vérifiée en rétablissant la situation',
        'Vérifiée'],
    ['4', 'Les agents reconnaissent y trouver l’information nécessaire',
        '[ À compléter après les entretiens ]',
        '[ À conclure ]'],
];

const doc = new Document({
    styles: { default: { document: { run: { font: POLICE, size: 24 } } } },
    sections: [{
        properties: {
            page: {
                size: { width: 16838, height: 11906, orientation: 'landscape' },
                margin: { top: 1134, right: 1134, bottom: 1134, left: 1134 },
            },
        },
        children: [
            Titre('V.3.1. Hypothèse 1 — confrontation des conditions aux résultats', 2),

            Legende('Tableau V.9 — Vérification des conditions de la première hypothèse', true),
            Tableau(['N°', 'Condition posée', 'Résultat constaté', 'Appréciation'], H1, [4, 22, 50, 24], [0]),
            Legende('Source : essais réalisés sur le projet d’Odienné'),

            P('Trois des quatre conditions sont vérifiées sans réserve. La deuxième et la troisième portent sur le fonctionnement même de l’outil et se constatent directement : une situation consolidée s’obtient par consultation, et deux restitutions produites à la même date ne peuvent diverger puisqu’elles procèdent des mêmes enregistrements. La quatrième l’est également, la traçabilité couvrant aussi bien les saisies manuelles que les données issues d’un import.'),

            P('La première condition appelle une distinction que le protocole n’avait pas anticipée. Elle est vérifiée du point de vue de l’outil : la base est unique, et les trois sections y écrivent chacune dans son domaine, la séparation des compétences interdisant à l’une d’empiéter sur l’autre. Elle ne peut en revanche être tenue pour établie du point de vue de l’usage : l’abandon durable des suivis tenus en parallèle est un fait d’organisation, qui s’observe sur des mois et non sur quelques semaines d’essai. Il serait imprudent de conclure autrement.'),

            P('Une seconde nuance mérite d’être portée, car elle touche à la nature même de la centralisation obtenue. Le classeur mensuel de la mission de contrôle continue d’exister, et continuera d’exister : il n’est pas un fichier parallèle que l’application viendrait remplacer, mais le document source dont elle tire sa matière. La dispersion que l’hypothèse visait n’était pas celle des documents de chantier, qui sont légitimes et contractuels ; elle était celle des suivis reconstitués à partir de ces documents, chacun par sa section et à sa manière. C’est cette seconde dispersion qui disparaît, et par un mécanisme non prévu au départ : l’import du classeur supprime la transcription, et avec elle la possibilité que deux agents tirent deux chiffres différents du même rapport.'),

            P([
                T('L’hypothèse est en conséquence tenue pour '),
                T('vérifiée', { bold: true }),
                T(' quant à l’unicité et à la fiabilité de l’information de suivi, et pour '),
                T('partiellement vérifiée', { bold: true }),
                T(' quant à l’abandon effectif des tenues parallèles, dont l’appréciation excède la durée des essais. Une confirmation partielle est ici plus honnête qu’une confirmation totale, et n’affaiblit pas le résultat : ce qui dépendait de l’outil est établi ; ce qui reste en suspens dépend des pratiques.'),
            ]),

            P('Un dernier point mérite d’être relevé, car il concerne la formulation même de l’hypothèse. Celle-ci suppose une base « alimentée par les différentes sections de l’UGP », c’est-à-dire une saisie répartie entre elles. Le dispositif retenu s’en écarte, et dans un sens plus favorable : l’essentiel de la donnée entre par l’import d’un document unique, la base de calcul de la mission de contrôle, une même opération alimentant l’avancement physique, l’avancement financier, les décomptes, les calendriers d’ouvrages, les indicateurs et les alertes. Les sections conservent la maîtrise de leur domaine — elles seules peuvent écrire ou corriger — mais elles n’ont plus à transcrire. L’unicité de la source, que l’hypothèse posait comme résultat attendu, se trouve ainsi obtenue par un moyen plus sûr que celui qui était envisagé.'),

            Titre('V.3.2. Hypothèse 2 — confrontation des conditions aux résultats', 2),

            Legende('Tableau V.10 — Vérification des conditions de la seconde hypothèse', true),
            Tableau(['N°', 'Condition posée', 'Résultat constaté', 'Appréciation'], H2, [4, 22, 50, 24], [0]),
            Legende('Source : essais réalisés sur le projet d’Odienné'),

            P('La première condition est vérifiée, et elle l’est de la manière la plus exigeante qui soit : non par recoupement interne, mais par confrontation à un tiers. Les valeurs restituées coïncident avec celles du rapport mensuel de la mission de contrôle, établi indépendamment du présent travail et antérieurement à lui. Le seul écart notable, six dixièmes de mois sur le décalage en délais, sépare une lecture graphique d’un calcul par interpolation : il atteste d’une concordance et non d’une divergence.'),

            P('La deuxième condition demande d’être lue avec précision, car elle comporte deux exigences que l’on confond aisément. L’absence de délai de consolidation est vérifiée : les indicateurs sont recalculés à l’enregistrement, sans traitement différé ni intervention. La fraîcheur de la donnée, elle, demeure celle de la source. Un outil de pilotage ne rend pas l’information plus récente qu’elle ne l’est ; il en rend l’accès immédiat et le vieillissement visible. C’est précisément ce que fait l’indicateur de fraîcheur, et ce que signalait, à la date des essais, l’alerte relative à l’absence de mise à jour.'),

            P('La troisième condition est vérifiée. Cinq règles se sont ouvertes sur les seules données réelles du chantier, sans qu’aucune situation ait dû être provoquée, et la fermeture automatique a été constatée au rétablissement. Ce point mérite d’être souligné : les alertes ne signalent pas des cas d’école construits pour l’essai, mais l’état effectif d’un chantier en difficulté.'),

            P([
                T('La quatrième condition relève de l’appréciation des utilisateurs et reste à établir. '),
                AVoir('[ À compléter après les entretiens : préciser les profils consultés et la teneur de leurs appréciations. ]'),
            ]),

            P('Reste à apprécier le terme central de l’hypothèse : la compatibilité des délais avec l’action corrective. Il appelle un critère, faute de quoi l’appréciation resterait affaire d’opinion. Le critère retenu est le suivant : l’information est mise à disposition dans des délais compatibles avec l’action corrective si elle parvient au décideur plus souvent que ne se présentent les occasions de décider.'),

            P('Appliqué au projet d’Odienné, ce critère est satisfait. Les décisions correctives se prennent au rythme des réunions tenues entre le maître d’ouvrage, la maîtrise d’œuvre et l’entreprise, dont le diagnostic du chapitre II a établi le caractère bimestriel, et des points contradictoires convoqués en cas de dérive — telle la réunion des 12 et 13 mai 2026, d’où est issue la demande de prolongation de huit mois. La donnée, elle, est disponible mensuellement dès la remise du rapport. L’information devance donc l’occasion de décider, ce qui n’était pas le cas lorsqu’elle devait être demandée, rassemblée puis mise en forme.'),

            P('Ce critère fixe aussi la limite de ce qui est démontré. Il ne s’agit pas d’un suivi en continu : un événement survenu en début de mois n’apparaîtra qu’au rapport suivant, et l’application ne peut abréger une périodicité dont elle n’est pas l’auteur. La compatibilité établie est celle du pilotage contractuel — avenants, mises en demeure, arbitrages de délai — et non celle de la conduite quotidienne du chantier, qui relève de la maîtrise d’œuvre présente sur le site et dispose de ses propres moyens.'),

            P([
                T('L’hypothèse est en conséquence tenue pour '),
                T('vérifiée', { bold: true }),
                T(' : les indicateurs sont exacts, leur consolidation est immédiate, les alertes se déclenchent et se closent conformément aux règles, et l’information parvient au décideur plus fréquemment que ne se présentent les occasions d’agir. Elle l’est dans les limites du pilotage contractuel, seul horizon auquel une donnée de périodicité mensuelle puisse prétendre.'),
            ]),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
