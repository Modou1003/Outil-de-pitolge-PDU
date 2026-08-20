/**
 * Résultats du suivi physique et de l'analyse par la valeur acquise — Odienné.
 *
 * Forme narrative avec formules composées en OMML (équations natives Word,
 * éditables et rendues comme telles). Toutes les valeurs sont celles vérifiées
 * au V.2.1 : grandeurs élémentaires confrontées au rapport mensuel n° 14,
 * indices dérivés recalculés sur ces grandeurs.
 */
import { Document, Packer, Paragraph, TextRun, AlignmentType, HeadingLevel,
         Math, MathRun, MathFraction } from 'docx';
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

const SousTitre = (t) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 24, bold: true, italics: true })],
    spacing: { before: 200, after: 100 },
});

/** Puce « libellé : valeur », la valeur en gras. */
const Item = (libelle, valeur, suite = '') => new Paragraph({
    children: [T(libelle + ' : '), T(valeur, { bold: true }), ...(suite ? [T(suite)] : [])],
    bullet: { level: 0 },
    alignment: AlignmentType.JUSTIFIED,
    spacing: { after: 80, line: 276 },
});

const Frac = (num, den) => new MathFraction({
    numerator: [new MathRun(num)],
    denominator: [new MathRun(den)],
});

/** Équation centrée : suite de MathRun et de fractions. */
const Formule = (elements) => new Paragraph({
    children: [new Math({ children: elements })],
    alignment: AlignmentType.CENTER,
    spacing: { before: 140, after: 160 },
});

const Commentaire = (t) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 22, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: { after: 200 },
});

const doc = new Document({
    styles: { default: { document: { run: { font: POLICE, size: 24 } } } },
    sections: [{
        properties: { page: { margin: { top: 1134, bottom: 1134, left: 1417, right: 1134 } } },
        children: [

            Titre('2. Résultats du suivi physique et analyse des écarts', 2),

            P('L’application a consolidé l’avancement physique par agrégation pondérée des ouvrages. Pour l’Université d’Odienné, dont le marché se décompose en trente ouvrages pondérés répartis en quatre composantes — installations de chantier, bâtiments, terrassements et voirie et réseaux divers, équipements —, le système a mis en évidence les valeurs suivantes à la date d’arrêté du 30 juin 2026.'),

            Item('Avancement physique planifié pondéré', '93,25 %'),
            Item('Avancement physique réel constaté', '32,13 %'),
            Item('Écart d’avancement global', '− 61,11 points de pourcentage'),

            P('Le module de décomposition par ouvrage permet d’isoler les causes de ce retard, ce qu’un avancement global ne peut faire. Les travaux préparatoires sont achevés : les terrassements et la plateforme atteignent 100 %, de même que les installations de chantier. Les bâtiments, qui pèsent 66,19 % du marché, plafonnent en revanche à 30,17 % pour une prévision de 95,49 % à la même date, et la voirie et les réseaux divers à 31,36 % pour une prévision de 86,68 %.'),

            P('Le retard tient d’abord à des démarrages différés plutôt qu’à un rythme d’exécution insuffisant : vingt-trois ouvrages sur trente ont démarré hors délai, et onze n’avaient pas commencé à la date d’arrêté. Le poste des mobiliers et équipements, qui pèse 4,80 % et devait être intégralement achevé, est resté non entamé, de sorte qu’il contribue pour zéro à l’avancement consolidé alors qu’il aurait dû y verser la totalité de son poids.'),

            Titre('3. Résultats de l’analyse par la valeur acquise', 2),

            P('L’automatisation des calculs de la méthode de la valeur acquise sur les relevés physiques et financiers du marché d’Odienné donne les résultats suivants à la date d’analyse. Les grandeurs monétaires sont rapportées à l’enveloppe des ouvrages effectivement suivis, seul périmètre sur lequel une valeur acquise puisse être mesurée.'),

            Item('Base du périmètre suivi (BAC)', '62 779 799 503 FCFA',
                ', à distinguer du montant du marché, qui s’élève à 68 879 636 067 FCFA'),
            Item('Valeur planifiée (PV)', '58 410 762 839 FCFA'),
            Item('Valeur acquise (EV)', '19 231 299 229 FCFA'),
            Item('Coût réel (AC)', '19 101 798 996 FCFA',
                ', provision pour révision de prix comprise'),

            SousTitre('3.1. Indice de performance des délais'),

            Formule([
                new MathRun('SPI = '), Frac('EV', 'PV'),
                new MathRun(' = '), Frac('19 231 299 229', '58 410 762 839'),
                new MathRun(' = 0,329'),
            ]),

            Commentaire('SPI < 1,00 — retard d’exécution majeur'),

            P('L’indice s’interprète comme la part du travail planifié effectivement réalisée : à la date d’arrêté, moins du tiers de la valeur qui devait être acquise l’a été. Cet indice ne s’exprime toutefois pas en unités de temps, et sa valeur ne saurait être lue comme une durée de retard ; c’est la valeur acquise temporelle, examinée plus loin, qui répond à cette question.'),

            SousTitre('3.2. Indice de performance des coûts'),

            Formule([
                new MathRun('CPI = '), Frac('EV', 'AC'),
                new MathRun(' = '), Frac('19 231 299 229', '19 101 798 996'),
                new MathRun(' = 1,007'),
            ]),

            Commentaire('CPI ≈ 1,00 — équilibre apparent entre valeur acquise et dépense'),

            P('Cette valeur, proche de l’unité, ne traduit pas une maîtrise remarquable de la dépense. Le marché étant conclu à prix unitaires, l’entreprise facture la valeur des travaux qu’elle a exécutés, de sorte que le coût réel suit mécaniquement la valeur acquise et que l’indice ne peut s’écarter durablement de un. Il conserve néanmoins son utilité comme contrôle de cohérence : une divergence signalerait une facturation déconnectée de l’avancement constaté. Le signal de coût doit donc être cherché ailleurs, dans les avenants et dans l’écart à l’achèvement.'),

            SousTitre('3.3. Coût final estimé et écart à l’achèvement'),

            Formule([
                new MathRun('EAC = '), Frac('BAC', 'CPI'),
                new MathRun(' = '), Frac('62 779 799 503', '1,007'),
                new MathRun(' = 62 343 395 733 FCFA'),
            ]),

            Formule([
                new MathRun('VAC = BAC − EAC = 62 779 799 503 − 62 343 395 733 = + 436 403 770 FCFA'),
            ]),

            Commentaire('VAC > 0 — achèvement projeté 0,70 % en deçà de l’enveloppe suivie'),

            P('Le reste à engager s’en déduit et mesure la dépense qu’il reste à consentir pour achever le périmètre suivi.'),

            Formule([
                new MathRun('ETC = EAC − AC = 62 343 395 733 − 19 101 798 996 = 43 241 596 737 FCFA'),
            ]),

            SousTitre('3.4. Analyse par la valeur acquise temporelle'),

            P('Le calcul du temps acquis rapporte l’avancement constaté à la date à laquelle il était planifié, et non à la valeur qui aurait dû être acquise. Le niveau d’avancement de 32,13 % devait être atteint au dixième mois du projet, alors que celui-ci se trouve au vingtième mois de son exécution.'),

            Formule([
                new MathRun('SPI(t) = '), Frac('ES', 'AT'),
                new MathRun(' = '), Frac('312 jours', '603 jours'),
                new MathRun(' = 0,517'),
            ]),

            P('La durée totale d’exécution se projette en divisant la durée contractuelle par cet indice temporel.'),

            Formule([
                new MathRun('Durée estimée = '), Frac('PD', 'SPI(t)'),
                new MathRun(' = '), Frac('730 jours', '0,517'),
                new MathRun(' = 1 412 jours'),
            ]),

            Item('Échéance contractuelle', '3 novembre 2026'),
            Item('Date d’achèvement projetée par l’application', '15 septembre 2028'),
            Item('Retard de livraison estimé', '682 jours, soit 22,4 mois'),

            P('Cette projection mérite d’être confrontée à une décision que le rapport de la mission de contrôle documente. À la suite de la réunion contradictoire des 12 et 13 mai 2026, une demande de prolongation de huit mois a été soumise à l’organisme financeur, portant le délai d’exécution de vingt-quatre à trente-deux mois. Les deux durées ne sont pas de même nature : l’une résulte d’une négociation entre les parties, l’autre de l’extrapolation du rythme constaté sur vingt mois d’exécution. Leur écart n’en est pas moins significatif, et il illustre l’apport propre de l’outil. Au rythme observé, huit mois de prolongation ne suffiront pas à l’achèvement, et l’application l’établit dès juin 2026 — c’est-à-dire au moment où l’avenant se négocie, et non après.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
