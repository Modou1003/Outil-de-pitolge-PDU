/**
 * Tableau V.8 — Indices dérivés, leurs formules et les valeurs restituées.
 *
 * La colonne des formules est composée en OMML : équations natives Word,
 * éditables, et non du texte mis en forme. Les notations sont définies avant
 * le tableau afin que chaque symbole renvoie à une grandeur du niveau 1.
 */
import { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
         WidthType, AlignmentType, HeadingLevel, BorderStyle, ShadingType,
         Math, MathRun, MathFraction, MathSum, MathSubScript } from 'docx';
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

/** Ligne de la table des notations. */
const Nota = (symbole, sens) => new Paragraph({
    children: [T(symbole, { bold: true }), T(' — ' + sens)],
    bullet: { level: 0 },
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 60, line: 264 },
});

const B = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const BORDS = { top: B, bottom: B, left: B, right: B };

const CADRE = (largeur, entete = false) => ({
    width: { size: largeur, type: WidthType.PERCENTAGE },
    borders: BORDS,
    shading: entete ? { type: ShadingType.CLEAR, fill: 'D9D9D9' } : undefined,
    margins: { top: 60, bottom: 60, left: 70, right: 70 },
});

const Cel = (t, { entete = false, largeur, centre = false } = {}) => new TableCell({
    ...CADRE(largeur, entete),
    children: [new Paragraph({
        children: [new TextRun({ text: t, font: POLICE, size: 19, bold: entete })],
        alignment: (entete || centre) ? AlignmentType.CENTER : AlignmentType.LEFT,
        spacing: { after: 0, line: 230 },
    })],
});

/** Cellule contenant une équation composée. */
const CelMath = (elements, largeur) => new TableCell({
    ...CADRE(largeur),
    children: [new Paragraph({
        children: [new Math({ children: elements })],
        alignment: AlignmentType.CENTER,
        spacing: { after: 0, line: 230 },
    })],
});

const Section = (titre, n) => new TableRow({
    children: [new TableCell({
        columnSpan: n,
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

// ─────────────────────────────────────────────── briques d'équation

const R = (t) => new MathRun(t);
const Frac = (num, den) => new MathFraction({
    numerator: Array.isArray(num) ? num : [R(num)],
    denominator: Array.isArray(den) ? den : [R(den)],
});
const Ind = (base, ind) => new MathSubScript({ children: [R(base)], subScript: [R(ind)] });
const Somme = (children) => new MathSum({
    children,
    subScript: [R('i=1')],
    superScript: [R('n')],
});

const L = [15, 33, 26, 26]; // largeurs des colonnes

// ─────────────────────────────────────────────── lignes du tableau

const LIGNES = [
    ['Grandeurs de la valeur acquise (F CFA)', [
        ['Base du périmètre suivi (BAC)',
            [R('BAC = '), Somme([Ind('E', 'i')])],
            'Enveloppes contractuelles des trente ouvrages', '62 779 799 503'],
        ['Valeur planifiée (PV)',
            [R('PV = '), Somme([Ind('E', 'i'), R(' × '), Ind('p', 'i')])],
            'Enveloppes ; taux planifiés cumulés', '58 410 762 839'],
        ['Valeur acquise (EV)',
            [R('EV = '), Somme([Ind('E', 'i'), R(' × '), Ind('r', 'i')])],
            'Enveloppes ; taux réalisés du tableau V.6', '19 231 299 229'],
        ['Coût réel (AC)',
            [R('AC = '), Somme([Ind('F', 'i')]), R(' + P')],
            'Facturations du tableau V.7 ; provision pour révision', '19 101 798 996'],
        ['Écart de coût (CV)',
            [R('CV = EV − AC')],
            'EV ; AC', '+ 129 500 233'],
        ['Écart de délai (SV)',
            [R('SV = EV − PV')],
            'EV ; PV', '− 39 179 463 609'],
        ['Coût final estimé (EAC)',
            [R('EAC = '), Frac('BAC', 'CPI')],
            'BAC ; CPI', '62 343 395 733'],
        ['Reste à engager (ETC)',
            [R('ETC = EAC − AC')],
            'EAC ; AC', '43 241 596 737'],
        ['Écart à l’achèvement (VAC)',
            [R('VAC = BAC − EAC')],
            'BAC ; EAC', '+ 436 403 770'],
    ]],
    ['Indices de performance', [
        ['Indice de performance de coût (CPI)',
            [R('CPI = '), Frac('EV', 'AC')],
            'EV ; AC', '1,007'],
        ['Indice de performance de délai (SPI)',
            [R('SPI = '), Frac('EV', 'PV')],
            'EV ; PV', '0,329'],
        ['Valeur acquise temporelle (ES)',
            [R('ES = k + '), Frac([R('EV − '), Ind('PV', 'k')], [Ind('PV', 'k+1'), R(' − '), Ind('PV', 'k')])],
            'Courbe des valeurs planifiées ; EV', '312 jours'],
        ['Temps écoulé (AT)',
            [R('AT = '), Ind('D', 'arrêté'), R(' − '), Ind('D', 'OS')],
            'Dates contractuelles', '603 jours'],
        ['Indice temporel (SPI(t))',
            [R('SPI(t) = '), Frac('ES', 'AT')],
            'ES ; AT', '0,517'],
        ['Date d’achèvement projetée',
            [Ind('D', 'fin'), R(' = '), Ind('D', 'OS'), R(' + '), Frac('PD', 'SPI(t)')],
            'Date d’ordre de service ; durée contractuelle ; SPI(t)', '15 septembre 2028'],
    ]],
    ['Indicateurs de cohérence et d’exposition', [
        ['Écart physico-financier',
            [R('Δ = '), Ind('A', 'phys'), R(' − '), Frac('AC', 'M')],
            'Avancement consolidé ; AC ; montant du marché', '+ 4,40 points'],
        ['Exposition sur avances (F CFA)',
            [R('X = ('), Ind('A', 'dém'), R(' + '), Ind('A', 'appro'), R(') − '), Somme([Ind('R', 'k')])],
            'Avances et remboursements du tableau V.5', '13 625 550 493'],
        ['Fraîcheur de la donnée',
            [R('F = '), Ind('D', 'obs'), R(' − '), Ind('D', 'relevé')],
            'Date d’arrêté du dernier relevé', '51 jours au 20 août 2026'],
    ]],
];

// ─────────────────────────────────────────────── assemblage

const tableau = new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
        new TableRow({
            tableHeader: true,
            children: ['Indicateur', 'Formule appliquée', 'Entrées, toutes validées au niveau 1', 'Valeur restituée']
                .map((e, i) => Cel(e, { entete: true, largeur: L[i] })),
        }),
        ...LIGNES.flatMap(([titre, lignes]) => [
            Section(titre, L.length),
            ...lignes.map(([nom, formule, entrees, valeur]) => new TableRow({
                children: [
                    Cel(nom, { largeur: L[0] }),
                    CelMath(formule, L[1]),
                    Cel(entrees, { largeur: L[2] }),
                    Cel(valeur, { largeur: L[3], centre: true }),
                ],
            })),
        ]),
    ],
});

const doc = new Document({
    styles: { default: { document: { run: { font: POLICE, size: 24 } } } },
    sections: [{
        properties: { page: { margin: { top: 1134, bottom: 1134, left: 1134, right: 1134 } } },
        children: [
            Titre('Second niveau : indices dérivés et vérification par recalcul', 2),

            P('Le rapport de la mission de contrôle ne produit ni valeur planifiée, ni valeur acquise, ni indice de performance, ni coût final estimé. Il établit un avancement physique, une situation de facturation, et lit un décalage sur une courbe. Les indices que restitue l’application ne peuvent donc être confrontés à aucun énoncé externe : ils sont vérifiés par recalcul sur tableur, conduit préalablement à toute consultation de l’application.'),

            P('Le tableau ci-après fait apparaître, pour chaque indicateur, la formule effectivement appliquée et les entrées dont elle procède. Cette dernière colonne n’est pas de commodité : elle établit que le second niveau ne repose sur aucune donnée qui n’ait été validée au premier. Les notations retenues sont les suivantes.'),

            Nota('Eᵢ', 'enveloppe contractuelle de l’ouvrage i, telle qu’elle figure au classeur d’avancement'),
            Nota('pᵢ, rᵢ', 'taux d’avancement cumulés de l’ouvrage i, respectivement planifié et réalisé, à la date d’arrêté'),
            Nota('Fᵢ', 'facturation cumulée de l’ouvrage i'),
            Nota('P', 'provision pour révision de prix facturée, ventilée entre les ouvrages au prorata de leur facturation'),
            Nota('M', 'montant du marché de travaux'),
            Nota('PVₖ', 'valeur planifiée cumulée à la fin du k-ième mois ; k désigne le dernier mois dont la valeur planifiée reste inférieure à la valeur acquise'),
            Nota('D_OS, D_arrêté, D_obs, D_relevé', 'dates d’ordre de service, d’arrêté du marché, d’observation et du dernier relevé enregistré'),
            Nota('PD', 'durée contractuelle d’exécution, en jours'),
            Nota('A_phys', 'avancement physique consolidé du projet'),
            Nota('A_dém, A_appro, Rₖ', 'avance de démarrage, acompte sur approvisionnement, et récupérations opérées sur les décomptes'),

            Legende('Tableau V.8 — Indices dérivés, leurs formules et les valeurs restituées', true),
            tableau,
            Legende('Source : essais réalisés sur le projet d’Odienné ; valeurs de recalcul établies sur tableur, écart nul sur l’ensemble des lignes'),

            P('Le recalcul manuel restitue les mêmes valeurs sur l’ensemble des lignes. Ce résultat était attendu et il n’est pas, en lui-même, le plus instructif : ce qui importe est que ces indices, dont aucun n’existait dans le dispositif de suivi antérieur, reposent intégralement sur des grandeurs qu’un tiers indépendant a établies.'),

            P('Deux formules appellent une précision. La valeur acquise temporelle procède d’une interpolation linéaire entre deux points mensuels de la courbe planifiée : le rang k désigne le dernier mois dont la valeur planifiée cumulée demeure inférieure à la valeur acquise, et la fraction mesure la position de celle-ci dans l’intervalle mensuel suivant. C’est cette interpolation qui explique l’écart de six dixièmes de mois avec la lecture graphique de la mission de contrôle. L’écart physico-financier, quant à lui, rapporte le coût réel au montant du marché et non à la base du périmètre suivi : il confronte en effet l’avancement des travaux à l’effort budgétaire consenti par le maître d’ouvrage, lequel s’apprécie sur l’engagement contractuel dans son entier.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
