/**
 * Chapitre V — V.2.1 Résultats des tests sur données réelles, structure à deux niveaux.
 *
 * Niveau 1 : les grandeurs élémentaires sont confrontées aux énoncés du rapport
 * mensuel n° 14 du groupement TAEP/IETF, établi indépendamment de ce travail.
 * Niveau 2 : les indices dérivés, que ce rapport ne produit pas, sont validés
 * par recalcul — mais leurs entrées sont toutes validées au niveau 1.
 *
 * Toutes les valeurs restituées proviennent des mesures effectives sur le jeu
 * de données d'Odienné ; toutes les valeurs du rapport ont été relevées dans le
 * document lui-même (tableaux n° 07 à 09 et récapitulatif d'avancement).
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

const Cel = (t, { entete = false, largeur, centre = false, gras = false } = {}) => new TableCell({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDS,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 50, bottom: 50, left: 80, right: 80 },
    children: [new Paragraph({
        children: [new TextRun({ text: t, font: POLICE, size: 19, bold: entete || gras })],
        alignment: (entete || centre) ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 230 },
    })],
});

const Tableau = (entetes, lignes, largeurs, centrees = [], grasses = []) => new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({
            tableHeader: true,
            children: entetes.map((e, i) => Cel(e, { entete: true, largeur: largeurs[i] })),
        }),
        ...lignes.map((l, k) => new TableRow({
            children: l.map((c, i) => Cel(c, {
                largeur: largeurs[i],
                centre: centrees.includes(i),
                gras: grasses.includes(k),
            })),
        })),
    ],
});

const Section = (titre, largeurs) => new TableRow({
    children: [new TableCell({
        columnSpan: largeurs.length,
        width: { size: 100, type: WidthType.PERCENTAGE },
        borders: BORDS,
        shading: { type: ShadingType.CLEAR, fill: 'F2F2F2' },
        margins: { top: 50, bottom: 50, left: 80, right: 80 },
        children: [new Paragraph({
            children: [new TextRun({ text: titre, font: POLICE, size: 19, bold: true, italics: true })],
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
                children: l.map((c, i) => Cel(c, { largeur: largeurs[i], centre: centrees.includes(i) })),
            })),
        ]),
    ],
});

const N = 'Nul';

// ═══════════════════ Tableau V.5 — niveau 1, grandeurs d'ensemble

const NIVEAU1 = [
    ['Avancement physique', [
        ['Avancement physique du projet', '32,13 %', '32,13 %', N],
        ['Prévision du planning contractuel à la même date', '93,21 %', '93,25 %', '0,04 point'],
        ['Retard d’avancement physique', '− 61,07 %', '− 61,11 points', '0,04 point'],
        ['Décalage en délais', '09 mois', '9,6 mois', '0,6 mois'],
    ]],
    ['Facturation des travaux (F CFA)', [
        ['Montant du marché HT/HD', '68 879 636 067', '68 879 636 067', N],
        ['Total général travaux facturé HT/HD', '19 010 276 117', '19 010 276 117', N],
        ['Provision pour révision de prix facturée', '91 522 879', '91 522 879', N],
        ['Total facturé sans avances', '19 101 798 996', '19 101 798 996', N],
        ['Avancement financier des travaux', '27,73 %', '27,73 %', N],
    ]],
    ['Décaissement et avances (F CFA)', [
        ['Total facturé avec avances', '32 727 349 489', '32 727 349 489', N],
        ['Taux des montants encaissés', '47,51 %', '47,51 %', N],
        ['Avance de démarrage versée', '13 775 927 213', '13 775 927 213', N],
        ['Avance de démarrage remboursée', '1 757 537 689', '1 757 537 689', N],
        ['Acompte sur approvisionnement versé', '1 607 160 969', '1 607 160 969', N],
        ['Acompte sur approvisionnement remboursé', 'Néant', 'Néant', N],
        ['Taux de remboursement de l’avance', '12,76 %', '11,43 %', '1,33 point'],
    ]],
];

// ═══════════════════ Tableau V.6 — avancement par ouvrage

const OUVRAGES = [
    ['Installations de chantier (installations passives)', '100,00 %', '100,00 %', N],
    ['Présidence, administration centrale, CELO, centre de données', '35,66 %', '35,66 %', N],
    ['Administration CROU', '30,20 %', '30,20 %', N],
    ['Centre médical', '32,59 %', '32,59 %', N],
    ['UFR et scolarité centrale', '32,23 %', '32,23 %', N],
    ['Hébergement des étudiants', '35,28 %', '35,28 %', N],
    ['Logements de fonction', '31,75 % ; 31,32 % ; 27,03 %', '30,12 %', 'Maille (voir infra)'],
    ['Hôtel des enseignants', '31,01 %', '31,01 %', N],
    ['Restaurant universitaire', '25,83 %', '25,83 %', N],
    ['Centre de conférence', '22,66 %', '22,66 %', N],
    ['Cœur de site', '23,38 %', '23,38 %', N],
    ['Amphithéâtre', '17,62 %', '17,62 %', N],
    ['Bibliothèque et learning center', '30,89 %', '30,89 %', N],
    ['Logistique CROU', '28,53 %', '28,53 %', N],
    ['Galerie centrale, passerelles et promenades', '9,66 %', '9,66 %', N],
    ['Guérite', '4,62 %', '4,62 %', N],
    ['Clôture', '49,79 %', '49,79 %', N],
    ['Terrassements et plateforme', '100,00 %', '100,00 %', N],
    ['Voiries, aires de stationnement et parkings', '22,48 %', '22,48 %', N],
    ['Adduction en eau potable', '0,00 %', '0,00 %', N],
    ['Eaux usées', '0,00 %', '0,00 %', N],
    ['Eaux pluviales', '18,34 %', '18,34 %', N],
    ['Environnement — aménagement paysager', '0,00 %', '0,00 %', N],
    ['Environnement — aire de jeux', '0,00 %', '0,00 %', N],
    ['Signalisation intérieure', '0,00 %', '0,00 %', N],
    ['Électricité, réseaux HT, MT, BT et éclairage public', '10,71 %', '10,71 %', N],
    ['Réseau fibre', '0,00 %', '0,00 %', N],
    ['Murs de soutènement', '68,53 %', '68,53 %', N],
    ['Efficacité énergétique (quatre sous-postes)', '0,00 %', '0,00 %', N],
    ['Mobiliers et équipements', 'Non entamé', '0,00 %', N],
    ['Avancement physique consolidé du projet', '32,13 %', '32,13 %', N],
];

// ═══════════════════ Tableau V.7 — facturation par lot

const LOTS = [
    ['Installations de chantier', '2 582 924 335', '2 582 924 335'],
    ['Présidence, administration centrale, CELO, centre de données', '1 158 027 741', '1 158 027 741'],
    ['Administration CROU', '546 865 309', '546 865 309'],
    ['Centre de conférence', '284 403 173', '284 403 173'],
    ['Centre médical', '369 573 461', '369 573 461'],
    ['UFR et scolarité centrale', '2 614 882 565', '2 614 882 565'],
    ['Cœur de site', '250 950 482', '250 950 482'],
    ['Amphithéâtre', '457 671 705', '457 671 705'],
    ['Hébergement des étudiants', '2 632 331 204', '2 632 331 204'],
    ['Logements de fonction', '325 965 915', '325 965 915'],
    ['Hôtel des enseignants', '662 802 201', '662 802 201'],
    ['Bibliothèque et learning center', '1 750 727 343', '1 750 727 343'],
    ['Restaurant universitaire', '700 238 551', '700 238 551'],
    ['Logistique CROU', '81 459 663', '81 459 663'],
    ['Galerie centrale', '73 007 537', '73 007 537'],
    ['Clôture', '561 369 412', '561 369 412'],
    ['Terrassements et plateformes', '2 380 802 326', '2 380 802 326'],
    ['Voiries, aires de stationnement et parkings', '876 756 392', '876 756 392'],
    ['Eaux pluviales', '128 026 495', '128 026 495'],
    ['Électricité, réseaux HT, MT, BT et éclairage public', '237 898 854', '237 898 854'],
    ['Murs de soutènement', '333 591 453', '333 591 453'],
    ['Total général travaux HT/HD', '19 010 276 117', '19 010 276 117'],
    ['Provision pour révision de prix', '91 522 879', '91 522 879'],
    ['Total général HT/HD', '19 101 798 996', '19 101 798 996'],
];

// ═══════════════════ Tableau V.8 — niveau 2, indices dérivés

const NIVEAU2 = [
    ['Grandeurs de la valeur acquise (F CFA)', [
        ['Base du périmètre suivi (BAC)', 'Somme des enveloppes contractuelles des ouvrages',
            'Enveloppes du classeur d’avancement', '62 779 799 503'],
        ['Valeur planifiée (PV)', 'Somme des enveloppes multipliées par les taux planifiés',
            'Enveloppes ; taux planifiés', '58 410 762 839'],
        ['Valeur acquise (EV)', 'Somme des enveloppes multipliées par les taux réalisés',
            'Enveloppes ; taux du tableau V.6', '19 231 299 229'],
        ['Coût réel (AC)', 'Facturation cumulée, provision pour révision comprise',
            'Montants du tableau V.7', '19 101 798 996'],
        ['Écart de coût (CV)', 'Valeur acquise moins coût réel', 'EV ; AC', '+ 129 500 233'],
        ['Écart de délai (SV)', 'Valeur acquise moins valeur planifiée', 'EV ; PV', '− 39 179 463 609'],
        ['Coût final estimé (EAC)', 'Base du périmètre divisée par l’indice de coût', 'BAC ; CPI', '62 343 395 733'],
        ['Reste à engager (ETC)', 'Coût final estimé moins coût réel', 'EAC ; AC', '43 241 596 737'],
        ['Écart à l’achèvement (VAC)', 'Base du périmètre moins coût final estimé', 'BAC ; EAC', '+ 436 403 770'],
    ]],
    ['Indices', [
        ['Indice de performance de coût (CPI)', 'Valeur acquise divisée par le coût réel', 'EV ; AC', '1,007'],
        ['Indice de performance de délai (SPI)', 'Valeur acquise divisée par la valeur planifiée', 'EV ; PV', '0,329'],
        ['Valeur acquise temporelle (ES)', 'Date à laquelle la valeur acquise était planifiée',
            'Courbe des valeurs planifiées', '312 jours'],
        ['Temps écoulé (AT)', 'Durée depuis l’ordre de service', 'Dates contractuelles', '603 jours'],
        ['Indice temporel (SPI(t))', 'Valeur acquise temporelle divisée par le temps écoulé', 'ES ; AT', '0,517'],
        ['Date d’achèvement projetée', 'Durée contractuelle divisée par l’indice temporel',
            'PD ; SPI(t)', '15 septembre 2028'],
    ]],
    ['Indicateurs de cohérence et d’exposition', [
        ['Écart physico-financier', 'Avancement physique moins taux d’exécution budgétaire',
            '32,13 % ; 27,73 %', '+ 4,40 points'],
        ['Exposition sur avances (F CFA)', 'Avances versées moins récupérations',
            'Avances et remboursements du tableau V.5', '13 625 550 493'],
        ['Fraîcheur de la donnée', 'Ancienneté du dernier relevé enregistré',
            'Date d’arrêté du classeur', '51 jours au 20 août 2026'],
    ]],
];

// ═══════════════════ Tableau V.9 — alertes

const ALERTES = [
    ['Règles déclenchées par les données réelles du chantier', [
        ['Écart d’avancement', 'Constatée : 61,11 points de retard sur le planning pondéré',
            'Alerte ouverte, gravité critique', 'Conforme'],
        ['Retard de livraison projeté', 'Constatée : achèvement projeté au 15 septembre 2028, soit 682 jours au-delà de l’échéance',
            'Alerte ouverte, gravité critique', 'Conforme'],
        ['Décompte en attente', 'Constatée : décomptes n° 9 à 12 et acompte sur approvisionnement non réglés, soit 6 864 084 309 F CFA',
            'Alerte ouverte, gravité informative', 'Conforme'],
        ['Démarrage d’ouvrage en retard', 'Constatée : vingt-trois ouvrages démarrés hors délai, dont onze non démarrés',
            'Alerte ouverte, gravité critique', 'Conforme'],
        ['Absence de mise à jour', 'Constatée : dernier relevé arrêté au 30 juin 2026',
            'Alerte ouverte, gravité avertissement', 'Conforme'],
    ]],
    ['Règles éprouvées par provocation, puis rétablissement (scénario 8)', [
        ['Jalon manqué', 'Provoquée : jalon de réception provisoire daté du 31 mai 2026 laissé non atteint',
            'Alerte ouverte ; refermée dès le jalon marqué atteint', 'Conforme'],
        ['Dépassement du délai contractuel', 'Provoquée : échéance ramenée au 31 janvier 2026, en deçà du dernier achèvement d’ouvrage constaté',
            'Alerte ouverte ; refermée au rétablissement de l’échéance', 'Conforme'],
        ['Dépassement budgétaire', 'Provoquée : décaissement porté à 91,5 % du marché, au-delà du seuil de 90 %',
            'Alerte ouverte ; refermée au rétablissement', 'Conforme'],
        ['Décalage physico-financier', 'Provoquée : décaissement porté à 60 % pour un avancement de 32,13 %, soit 28 points d’avance',
            'Alerte ouverte ; refermée au rétablissement', 'Conforme'],
        ['Dérive de coût', 'Provoquée : coût réel majoré de 6 milliards, portant l’indice de coût à 0,76',
            'Alerte ouverte ; refermée au rétablissement', 'Conforme'],
        ['Avenants cumulés excessifs', 'Provoquée : avenant de 12 milliards, soit 17,4 % du marché initial',
            'Alerte ouverte ; refermée à la suppression de l’avenant', 'Conforme'],
    ]],
];

// ═══════════════════════════════════════════════════════ document

const doc = new Document({
    styles: { default: { document: { run: { font: POLICE, size: 24 } } } },
    sections: [{
        properties: { page: { margin: { top: 1134, bottom: 1134, left: 1417, right: 1134 } } },
        children: [
            Titre('V.2. Résultats de la validation', 2),
            Titre('V.2.1. Résultats des tests sur données réelles et vérification des calculs', 3),

            P('Les essais ont été conduits sur les données réelles du projet d’Odienné, selon les scénarios du protocole. La volumétrie mise en jeu est la suivante : trente ouvrages pondérés, six cents relevés d’avancement physique et six cents relevés de valeur acquise couvrant vingt périodes mensuelles, quatorze décomptes, pour un marché de 68 879 636 067 francs CFA. Ces données proviennent intégralement de la base de calcul d’avancement arrêtée au 30 juin 2026, importée dans l’application au moyen de la fonction décrite au chapitre IV.'),

            Titre('Une vérification conduite à deux niveaux', 3),

            P('La vérification d’un outil de calcul se heurte à une difficulté de principe qu’il convient d’énoncer avant d’en présenter les résultats. Comparer les valeurs restituées à des valeurs que l’on aurait soi-même recalculées, sur la même base et à partir des mêmes données, n’établit que la cohérence interne du dispositif : l’opération vérifie l’arithmétique, non la justesse de la mesure. Une telle vérification, si elle est présentée seule, demeure circulaire.'),

            P('La validation a donc été conduite à deux niveaux, dont le premier échappe à cette objection.'),

            P('Le premier niveau porte sur les grandeurs élémentaires — avancements par ouvrage, facturations par lot, avances versées et remboursées, montant du marché. Ces grandeurs sont énoncées par le rapport mensuel d’activité n° 14 du groupement TAEP/IETF, arrêté au 30 juin 2026. Ce document a été établi par l’assistance à maîtrise d’ouvrage dans le cadre de son marché, antérieurement au présent travail et sans en connaître l’existence. La confrontation est donc une confrontation à un tiers indépendant, et non un contrôle de soi par soi.'),

            P('Le second niveau porte sur les indices dérivés — valeur planifiée, valeur acquise, indices de performance, coût final estimé, valeur acquise temporelle. Ces indices ne figurent pas au rapport de la mission de contrôle, qui ne met pas en œuvre la méthode de la valeur acquise : ils ne peuvent donc être confrontés à aucune source externe et sont vérifiés par recalcul. Mais ce recalcul ne porte pas sur des données arbitraires : toutes les entrées de ces indices ont été validées au premier niveau. Ce que le second niveau ajoute au premier n’est que de l’arithmétique appliquée à des données dont la justesse est déjà établie.'),

            Titre('Premier niveau : confrontation au rapport de la mission de contrôle', 3),

            Legende('Tableau V.5 — Grandeurs d’ensemble confrontées au rapport mensuel n° 14', true),
            TableauSections(['Grandeur', 'Énoncé du rapport n° 14', 'Restitution de l’application', 'Écart'],
                NIVEAU1, [36, 24, 24, 16], [1, 2, 3]),
            Legende('Source : rapport mensuel d’activité n° 14 du groupement TAEP/IETF, arrêté au 30 juin 2026, tableaux n° 07 et 08 ; essais réalisés sur le projet d’Odienné'),

            P('Douze des seize grandeurs présentent un écart nul, et les quatre autres appellent une explication de méthode et non une réserve.'),

            P('La prévision du planning contractuel est lue sur la courbe par la mission de contrôle, qui l’arrête à 93,21 %, tandis que l’application la recalcule à partir des pondérations du classeur et obtient 93,25 %. L’écart de quatre centièmes de point se reporte mécaniquement sur le retard d’avancement, que le rapport chiffre à 61,07 points et l’application à 61,11.'),

            P('Le décalage en délais est de neuf mois au rapport et de neuf mois et six dixièmes dans l’application. La mission de contrôle mesure cette distance à la règle, horizontalement, entre les deux courbes ; l’application l’obtient par interpolation entre deux points mensuels de la courbe planifiée, selon la méthode de la valeur acquise temporelle. Six dixièmes de mois entre une lecture graphique et un calcul constituent une concordance.'),

            P('Le taux de remboursement de l’avance, enfin, est de 12,76 % au rapport et de 11,43 % dans l’application. Les numérateurs sont identiques — 1 757 537 689 francs récupérés. Les dénominateurs diffèrent : le rapport rapporte cette récupération à la seule avance de démarrage, l’application aux deux avances consenties, démarrage et approvisionnement. Il s’agit d’une convention de calcul et non d’une divergence de mesure ; celle de l’application a été retenue parce que l’exposition financière du maître d’ouvrage porte sur l’ensemble des sommes avancées.'),

            P('La confrontation a été poussée jusqu’au détail des ouvrages, qui constitue la donnée élémentaire du dispositif. Le récapitulatif d’avancement du rapport et le tableau n° 09 des paiements perçus par lot permettent une comparaison ligne à ligne.'),

            Legende('Tableau V.6 — Avancement physique par ouvrage confronté au rapport n° 14', true),
            Tableau(['Ouvrage', 'Rapport n° 14', 'Application', 'Écart'],
                OUVRAGES, [52, 20, 16, 12], [1, 2, 3], [30]),
            Legende('Source : rapport mensuel n° 14, récapitulatif d’avancement physique ; essais réalisés sur le projet d’Odienné'),

            P('Vingt-neuf des trente ouvrages coïncident au centième de point. Le seul écart tient à la maille de décomposition et non à la valeur : le rapport détaille les logements de fonction en trois lignes — logement du président à 31,75 %, logements du vice-président et des directeurs à 31,32 %, logements d’astreinte à 27,03 % —, là où la base de calcul d’avancement, qui est la source de l’application, les agrège en un ouvrage unique valorisé à 30,12 %. L’application suit sa source ; elle ne pourrait détailler davantage sans inventer une décomposition que le classeur ne fournit pas.'),

            Legende('Tableau V.7 — Facturation cumulée par lot confrontée au rapport n° 14', true),
            Tableau(['Lot', 'Rapport n° 14 (F CFA)', 'Application (F CFA)'],
                LOTS, [56, 22, 22], [1, 2], [21, 22, 23]),
            Legende('Source : rapport mensuel n° 14, tableau n° 09 — détail des paiements perçus par lot ; essais réalisés sur le projet d’Odienné'),

            P('Les vingt et une lignes de facturation coïncident au franc près, de même que les deux totaux et la provision pour révision de prix. Cette dernière mérite une mention : elle constituait, avant correction, le seul poste absent du coût réel calculé par l’application. La confrontation au tableau n° 09 du rapport l’a mise au jour, et sa réintégration — ventilée entre les ouvrages au prorata de leur facturation — porte le total à 19 101 798 996 francs, valeur que le rapport énonce sous l’intitulé « total facturé sans avances ».'),

            P('Une précision s’impose sur ce qui n’a pas été porté au tableau. Le rapport indique un reste à facturer des travaux de 72,27 %, complément de l’avancement financier ; l’application affiche pour sa part un taux de facturation de 50,07 %. Ces deux valeurs ne se comparent pas : la première exclut les avances de l’assiette facturée, la seconde les inclut. Les rapprocher ferait apparaître un écart de vingt-deux points qui ne serait qu’un artefact de définition. Seules les grandeurs dont la définition est commune aux deux sources figurent au tableau V.5.'),

            Titre('Second niveau : indices dérivés et vérification par recalcul', 3),

            P('Le rapport de la mission de contrôle ne produit ni valeur planifiée, ni valeur acquise, ni indice de performance, ni coût final estimé. Il établit un avancement physique, une situation de facturation, et lit un décalage sur une courbe. Les indices que restitue l’application ne peuvent donc être confrontés à aucun énoncé externe : ils sont vérifiés par recalcul sur tableur, conduit préalablement à toute consultation de l’application.'),

            P('Le tableau ci-après fait apparaître, pour chaque indice, sa formule et les entrées dont il procède. Cette colonne n’est pas de commodité : elle établit que le second niveau ne repose sur aucune donnée qui n’ait été validée au premier.'),

            Legende('Tableau V.8 — Indices dérivés, leurs entrées et les valeurs restituées', true),
            TableauSections(['Indicateur', 'Formule appliquée', 'Entrées, toutes validées au niveau 1', 'Valeur restituée'],
                NIVEAU2, [24, 30, 24, 22], [3]),
            Legende('Source : essais réalisés sur le projet d’Odienné ; valeurs de recalcul établies sur tableur, écart nul sur l’ensemble des lignes'),

            P('Le recalcul manuel restitue les mêmes valeurs sur l’ensemble des lignes. Ce résultat était attendu et il n’est pas, en lui-même, le plus instructif : ce qui importe est que ces indices, dont aucun n’existait dans le dispositif de suivi antérieur, reposent intégralement sur des grandeurs qu’un tiers indépendant a établies.'),

            P('Une précision de périmètre conditionne la lecture de ce tableau. Les grandeurs de la valeur acquise sont rapportées à l’enveloppe des ouvrages effectivement suivis, soit 62 779 799 503 francs CFA, et non au montant du marché. L’écart entre les deux, 6 099 836 564 francs, correspond aux postes que la décomposition ne ventile pas en ouvrages et qui ne font l’objet d’aucun relevé d’avancement physique. Diviser le coût final estimé par le marché entier alors que la valeur acquise ne couvre que les ouvrages majorait ce coût de 8,86 % et rendait l’écart à l’achèvement ininterprétable. La validation a mis ce défaut au jour ; la correction a consisté à énoncer explicitement le périmètre, et l’application affiche désormais la base retenue sous le tableau de la valeur acquise.'),

            P('Une observation s’impose sur l’indice de performance de coût, restitué à 1,007. Cette valeur, proche de l’unité, ne traduit pas une maîtrise remarquable de la dépense : l’entreprise facture la valeur des travaux qu’elle a exécutés, de sorte que le coût réel suit mécaniquement la valeur acquise. Sur un marché à prix unitaires, cet indice ne peut s’écarter durablement de un, et le signal de coût doit être cherché ailleurs — dans les avenants et dans l’écart entre le marché et le coût final estimé. Il conserve néanmoins son utilité comme contrôle de cohérence : une divergence signalerait une facturation déconnectée de l’avancement constaté.'),

            P('La date d’achèvement projetée mérite en revanche d’être confrontée à une décision que le rapport documente. La mission de contrôle indique qu’à la suite de la réunion contradictoire des 12 et 13 mai 2026, une demande de prolongation de huit mois a été soumise à l’organisme financeur, portant le délai d’exécution de vingt-quatre à trente-deux mois. L’application, extrapolant le rythme constaté sur vingt mois par la méthode de la valeur acquise temporelle, projette un achèvement au 15 septembre 2028, soit six cent quatre-vingt-deux jours au-delà de l’échéance contractuelle — plus de vingt-deux mois. Les deux nombres ne sont pas de même nature : l’un résulte d’une négociation entre parties, l’autre d’une extrapolation. Mais leur écart illustre exactement l’apport de l’outil. Au rythme observé, huit mois de prolongation ne suffiront pas, et l’application le dit dès juin 2026, c’est-à-dire au moment où l’avenant se négocie et non après.'),

            P('La validation a par ailleurs révélé un second défaut, sans effet sur les montants. La courbe en S consolidait les ouvrages par une moyenne simple, alors que l’avancement affiché en tête de fiche projet les consolide par leur pondération. À la date d’arrêté, la courbe annonçait 25,3 % là où l’avancement du projet indiquait 32,13 %, pour la seule raison que les ouvrages les plus lourds sont aussi les plus avancés. La consolidation de la courbe a été rendue pondérée, en cohérence avec la convention retenue au chapitre III.'),

            Titre('Vérification du déclenchement des alertes', 3),

            P('Le dispositif d’alerte compte onze règles. Cinq d’entre elles se déclenchent sur les seules données réelles du chantier, sans qu’il ait été nécessaire de provoquer quoi que ce soit. Les six autres supposent des situations que le chantier ne présente pas à la date d’arrêté ; elles ont été éprouvées par provocation délibérée, selon le scénario 8 du protocole, chaque provocation étant suivie du rétablissement de la situation antérieure afin de vérifier la fermeture automatique de l’alerte.'),

            Legende('Tableau V.9 — Vérification du déclenchement des alertes', true),
            TableauSections(['Règle de détection', 'Situation', 'Comportement observé', 'Conclusion'],
                ALERTES, [22, 34, 32, 12], [3]),
            Legende('Source : essais réalisés sur le projet d’Odienné ; provocations conduites sur le jeu de données réel, non sur un projet fabriqué'),

            P('Les onze règles se déclenchent conformément à leur définition, et les six règles provoquées se referment d’elles-mêmes dès la situation rétablie, sans intervention. Le choix de conduire les provocations sur le jeu de données réel plutôt que sur un projet créé pour l’occasion n’est pas indifférent : il permet de vérifier qu’une règle ne se déclenche pas déjà pour une autre raison, et donc que l’ouverture observée est bien imputable à la condition provoquée. Chacun de ces essais est consigné dans la suite de tests automatisés de l’application et peut être rejoué.'),

            Titre('Synthèse', 3),

            P('La validation porte sur cinquante-quatre grandeurs élémentaires confrontées à un document établi par un tiers : seize grandeurs d’ensemble, trente avancements d’ouvrages et vingt-quatre lignes de facturation. Cinquante d’entre elles présentent un écart nul, une relève d’une maille de décomposition différente, et trois d’une convention de calcul explicitée. Sur cette base, dix-huit indices dérivés ont été vérifiés par recalcul, sans écart.'),

            P('Ce résultat n’a pas été acquis d’emblée, et c’est ce qui lui donne sa valeur. La confrontation au rapport a mis au jour cinq défauts : une base erronée pour le coût final estimé, l’omission de la provision pour révision de prix dans le coût réel, un double décompte de l’avance de démarrage dans l’encaissement, l’omission de l’acompte sur approvisionnement dans l’exposition sur avances, et une consolidation non pondérée de la courbe en S. Un dispositif d’essais qui ne révèle rien n’a rien éprouvé.'),

            Figure('[ FIGURE à insérer — Capture d’écran de la fiche du projet d’Odienné ]'),
            Legende('Figure V.1 — Fiche du projet d’Odienné'),
            Legende('Source : application développée'),

            Figure('[ FIGURE à insérer — Capture d’écran de la courbe en S du projet d’Odienné ]'),
            Legende('Figure V.2 — Courbe en S du projet d’Odienné'),
            Legende('Source : application développée'),

            Figure('[ FIGURE à insérer — Capture d’écran des courbes de la valeur acquise ]'),
            Legende('Figure V.3 — Courbes de la valeur acquise du projet d’Odienné'),
            Legende('Source : application développée'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
