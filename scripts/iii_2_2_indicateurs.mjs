/**
 * III.2.2 — Définition des indicateurs calculés automatiquement.
 *
 * Version actualisée après la suppression du score de santé, l'adoption de la
 * valeur acquise temporelle en remplacement de l'extrapolation linéaire, la
 * ventilation de la valeur acquise par ouvrage et l'ajout du retard au
 * démarrage.
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

/** Formule mise en évidence, centrée et en retrait. */
const F = (t) => new Paragraph({
    children: [new TextRun({ text: t, font: POLICE, size: 24, italics: true })],
    alignment: AlignmentType.CENTER,
    spacing: { before: 100, after: 130 },
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
            Titre('III.2.2. Définition des indicateurs calculés automatiquement', 2),

            P('L’un des apports déterminants de l’application est de calculer les indicateurs de pilotage à partir des seules valeurs élémentaires saisies. L’utilisateur ne renseigne que des données observées ; le système en dérive les écarts, les indices et les projections. Aucune des dix cartes de la fiche projet n’est renseignée à la main.'),

            P('Les indicateurs se répartissent en cinq familles : l’avancement physique, la fraîcheur de la donnée, les indicateurs de performance et de projection, les indicateurs financiers contractuels, et les alertes.'),

            // ══════════════════════════════════════════ 1. avancement physique
            Titre('1. L’avancement physique', 2),
            Titre('1.1. Objet', 3),

            P('L’avancement physique exprime le degré de réalisation matérielle du projet, indépendamment de toute considération financière. Il répond à la seule question de savoir ce qui est effectivement construit, et constitue la grandeur de référence à laquelle sont comparés le décaissement, le délai consommé et la valeur acquise.'),

            Titre('1.2. Formule et cascade de repli', 3),

            P('Le calcul procède par agrégation pondérée des ouvrages composant le projet.'),
            F('avancement = Σ (pondérationᵢ × avancementᵢ) ÷ Σ (pondérationᵢ)'),

            P('Trois situations sont prévues, dans cet ordre. Si les ouvrages portent une pondération, la moyenne pondérée s’applique. À défaut de pondération, une moyenne simple est calculée sur les seuls ouvrages ayant reçu une saisie. Si aucun ouvrage n’est saisi, la dernière valeur relevée au niveau du projet est retenue.'),

            P('Cette cascade garantit qu’une valeur reste disponible quel que soit le degré de détail de la saisie. Elle a pour contrepartie que deux projets peuvent afficher un avancement calculé sur des bases différentes, point sur lequel il convient de rester vigilant.'),

            P('La somme des pondérations ne peut excéder cent pour cent, le serveur refusant toute saisie qui porterait le total au-delà en indiquant le solde disponible. Elle n’est en revanche pas contrainte de l’atteindre : la saisie des ouvrages étant progressive, un total inférieur reste admis, l’application se bornant à signaler que l’avancement consolidé n’est alors pas représentatif.'),

            Titre('1.3. Origine des pondérations', 3),

            P('La pondération n’est pas établie par l’application, et c’est un point de méthode important. Elle est reprise de la base de calcul d’avancement de la mission de contrôle, qui l’établit et la maintient d’un mois sur l’autre à partir des montants du devis quantitatif estimatif. L’application ne construit donc pas une clé de répartition : elle applique celle du contrôle, ce qui garantit que son avancement consolidé se compare à celui du rapport mensuel.'),

            Titre('1.4. Limites', 3),

            P('Le mode de calcul de l’avancement d’un ouvrage n’est pas normalisé. Un taux peut être établi au prorata des quantités exécutées, au prorata du montant des travaux réalisés, ou par appréciation directe du responsable de suivi. Ces trois méthodes ne donnent pas le même résultat pour un même ouvrage. L’application agrège fidèlement ce qu’on lui saisit ; elle ne peut pas garantir l’homogénéité de la convention employée.'),

            P('L’avancement déclaré n’est pas vérifié. L’application contrôle la cohérence formelle de la saisie — bornes, unicité par période et par ouvrage, appartenance de l’ouvrage au projet — mais non sa conformité à l’état réel du chantier. Un avancement surestimé produit des indicateurs exacts à partir de données fausses, et améliore mécaniquement la valeur acquise, donc les indices de performance.'),

            // ══════════════════════════════════════════ 2. fraîcheur
            Titre('2. La fraîcheur de la donnée', 2),
            Titre('2.1. Objet', 3),

            P('Cet indicateur ne mesure pas le projet mais la qualité de ce que l’on en sait. Il répond à une question préalable à toute lecture des autres cartes : les valeurs affichées décrivent-elles la situation d’aujourd’hui ou celle d’il y a trois mois ?'),

            P('Sa présence dans la fiche projet répond directement à l’un des dysfonctionnements diagnostiqués : le délai de remontée de l’information, qui réduit l’utilité de la donnée sans que le lecteur en soit averti.'),

            Titre('2.2. Formules', 3),

            F('ancienneté = nombre de jours entre la dernière date de mesure et la date du jour'),
            F('taux de couverture = (ouvrages saisis sur les 30 derniers jours ÷ nombre total d’ouvrages) × 100'),

            P('Les deux grandeurs sont affichées conjointement, et cette conjonction est essentielle. L’ancienneté porte sur la saisie la plus récente, quelle qu’elle soit : un seul ouvrage mis à jour suffit à faire paraître le projet frais. Le taux de couverture corrige cette illusion en indiquant quelle proportion du chantier a réellement été actualisée.'),

            Titre('2.3. Seuils', 3),

            Legende('Tableau 1 — Niveaux de fraîcheur', true),
            Tableau(['Niveau', 'Ancienneté', 'Signification'], [
                ['Fraîche', '30 jours ou moins', 'Cohérent avec la périodicité mensuelle de remontée prévue'],
                ['À rafraîchir', '31 à 60 jours', 'Une échéance de remontée a été manquée'],
                ['Périmée', 'Plus de 60 jours', 'Au moins deux échéances manquées ; les autres cartes deviennent peu fiables'],
            ], [16, 20, 64], [0, 1]),
            Legende('Source : élaboration personnelle'),

            P('Le seuil de trente jours n’est pas arbitraire : il correspond à la périodicité mensuelle des rapports d’activité prévue par le manuel de procédures. Une donnée de plus d’un mois signale donc un manquement à une obligation existante, et non une simple préférence de l’outil.'),

            Titre('2.4. Exemple', 3),

            P('Soit un projet comportant douze ouvrages, dont trois ont été mis à jour au cours des trente derniers jours, la plus récente de ces saisies remontant à quatre jours.'),
            F('ancienneté = 4 jours → niveau « fraîche »     taux de couverture = 3 ÷ 12 × 100 = 25 %'),

            P('La carte affiche donc une donnée fraîche, alors que les trois quarts du chantier n’ont pas été actualisés depuis plus d’un mois. C’est exactement la situation que le taux de couverture est destiné à révéler.'),

            Titre('2.5. Limites', 3),

            P('L’indicateur ne distingue pas l’absence de saisie de l’absence d’activité. Un chantier à l’arrêt régulièrement constaté devrait donner lieu à des relevés inchangés plutôt qu’à une absence de relevé. Il est utile de rappeler aux agents qu’une saisie confirmant l’absence de progression est une information, tandis qu’une absence de saisie n’en est pas une.'),

            P('Le niveau de fraîcheur ne dégrade pas automatiquement les autres cartes. Un indice de performance de coût calculé sur des données vieilles de quatre-vingts jours s’affiche avec la même apparence qu’un indice calculé sur des données du jour. Il serait pertinent de signaler visuellement les indicateurs adossés à une donnée périmée.'),

            P('Une exception a toutefois été introduite, et elle mérite d’être signalée : la valeur acquise temporelle, exposée plus loin, arrête son décompte du temps écoulé à la date du dernier relevé et non à la date du jour. Sans cette précaution, l’ancienneté de la saisie viendrait s’ajouter au retard mesuré, et un relevé vieux de trois mois ferait apparaître trois mois de retard qui n’ont jamais été constatés.'),

            // ══════════════════════════════════════════ 3. performance
            Titre('3. Les indicateurs de performance et de projection', 2),
            Titre('3.1. Le socle commun : la méthode de la valeur acquise', 3),

            P('Trois grandeurs élémentaires alimentent les indices de performance.'),

            Legende('Tableau 2 — Valeurs élémentaires', true),
            Tableau(['Symbole', 'Désignation', 'Signification'], [
                ['PV', 'Valeur planifiée', 'Budget du travail qui aurait dû être réalisé à la date considérée'],
                ['EV', 'Valeur acquise', 'Budget du travail effectivement réalisé à la date considérée'],
                ['AC', 'Coût réel', 'Dépense effectivement engagée à la date considérée'],
            ], [12, 24, 64], [0]),
            Legende('Source : Project Management Institute, Guide PMBOK'),

            P('Ces trois valeurs sont portées par chaque ouvrage et non par le seul projet, ce qui permet de calculer un indice de performance par ouvrage autant que pour l’ensemble. La valeur planifiée et la valeur acquise d’un ouvrage se déduisent de son avancement, planifié ou constaté, appliqué à son enveloppe.'),

            F('PVᵢ = enveloppeᵢ × avancement planifiéᵢ     EVᵢ = enveloppeᵢ × avancement constatéᵢ'),

            P('L’enveloppe retenue est le montant contractuel de l’ouvrage, tel qu’il figure au devis quantitatif estimatif. Ce choix n’est pas indifférent : la pondération physique d’un ouvrage et son poids financier ne se confondent pas, de sorte qu’une répartition du marché au prorata de la pondération physique donnerait une valeur acquise faussée. À défaut de montant contractuel connu, l’application se replie sur cette répartition au prorata.'),

            P('Il en résulte que l’avancement exprimé en pourcentage et l’avancement exprimé en valeur ne coïncident pas nécessairement. L’écart entre les deux n’est pas une erreur : il indique que les ouvrages les plus avancés ne sont pas ceux qui pèsent le plus lourd financièrement, ou l’inverse.'),

            P('Les indices sont calculés sur les cumuls de l’ensemble des relevés, et non sur le dernier relevé seul. Cette agrégation lisse les irrégularités de période et donne une mesure de tendance plutôt qu’un instantané.'),

            Titre('3.2. L’indice de performance des délais', 3),

            F('SPI = Σ EV ÷ Σ PV'),

            P('Un indice supérieur à l’unité signale que le travail réalisé dépasse le travail planifié à la même date ; le projet est en avance. Un indice inférieur à l’unité signale un retard. La carte est affichée en vert au-dessus de l’unité, en ambre en dessous.'),

            P('Une limite doit être connue absolument. Cet indice tend mécaniquement vers l’unité à l’approche de l’achèvement, y compris pour un projet très en retard : en fin de projet, la valeur acquise rejoint nécessairement la valeur planifiée totale, puisque tout le travail finit par être réalisé. Le SPI vaut alors exactement un, quel que soit le retard accumulé. Cet indice ne doit donc jamais être lu seul.'),

            P('C’est précisément cette limite qui a conduit à retenir la valeur acquise temporelle, exposée au point 3.4, laquelle mesure le retard en unités de temps et n’est pas affectée par ce biais. La fiche projet présente en outre, sur la même ligne de lecture, l’avancement physique et l’écart physique-budget, qui ne présentent pas non plus ce défaut.'),

            Titre('3.3. L’indice de performance des coûts', 3),

            F('CPI = Σ EV ÷ Σ AC'),

            P('Un indice supérieur à l’unité signale que la valeur produite dépasse la dépense engagée ; le projet est sous budget. Un indice inférieur signale un surcoût. Le coût final estimé s’en déduit.'),

            F('EAC = marché actualisé ÷ CPI'),

            P('Deux limites affectent cet indice, dont la seconde a été découverte à l’usage et mérite une attention particulière.'),

            P('La première est de fiabilité : l’indice hérite de la qualité de la valeur acquise. Lorsque celle-ci est dérivée de l’avancement physique déclaré, un avancement surestimé la gonfle et améliore artificiellement l’indice. Un projet mal suivi peut ainsi afficher une excellente performance de coût. C’est là que la fraîcheur de la donnée et l’écart physique-budget prennent tout leur sens : ils repèrent les situations où les déclarations ne sont pas corroborées.'),

            P('La seconde est structurelle, et elle limite considérablement la portée de l’indice sur un marché de travaux à prix unitaires. L’entreprise y facture la valeur des travaux qu’elle a exécutés : le coût réel suit donc par construction la valeur acquise, et l’indice ne peut s’écarter durablement de l’unité. Un indice voisin de un ne traduit alors aucune performance particulière ; il traduit la mécanique du marché. L’indice conserve une utilité comme contrôle de cohérence — une divergence signalerait une facturation déconnectée de l’avancement constaté — mais le signal de coût doit être cherché ailleurs, dans les avenants et dans l’écart entre le marché initial et le coût final estimé.'),

            P('Il faut enfin rappeler que le budget à l’achèvement auquel se compare la performance n’est pas une ligne budgétaire mais le montant du marché actualisé, c’est-à-dire le montant initial augmenté des avenants approuvés. Tout avenant modifie donc la référence, sans que cela constitue en soi une contre-performance.'),

            Titre('3.4. La fin projetée et la valeur acquise temporelle', 3),

            P('Cette carte extrapole la date d’achèvement du projet au rythme réellement constaté, et la compare à l’échéance contractuelle actualisée. La méthode retenue est celle de la valeur acquise temporelle, qui transpose la valeur acquise du registre des montants à celui des durées.'),

            P('Le principe est le suivant. Plutôt que de comparer un travail réalisé à un travail planifié, on cherche la date à laquelle le planning prévoyait d’atteindre l’avancement aujourd’hui constaté. Le temps ainsi acquis, rapporté au temps effectivement écoulé, donne un indice de performance des délais exprimé en durée.'),

            Legende('Tableau 3 — Grandeurs de la valeur acquise temporelle', true),
            Tableau(['Symbole', 'Désignation', 'Détermination'], [
                ['ES', 'Temps acquis', 'Date à laquelle la courbe planifiée atteignait l’avancement constaté, obtenue par interpolation entre deux relevés mensuels'],
                ['AT', 'Temps écoulé', 'Durée séparant le démarrage de la date d’arrêté des données, c’est-à-dire du dernier relevé'],
                ['PD', 'Durée planifiée', 'Durée du planning de référence, mesurée sur l’échéance contractuelle initiale'],
            ], [12, 20, 68], [0]),
            Legende('Source : d’après Lipke (2003)'),

            F('SPI(t) = ES ÷ AT     durée estimée = PD ÷ SPI(t)'),
            F('date projetée = date de démarrage + durée estimée     retard = date projetée − échéance actualisée'),

            P('Deux précisions de conception en découlent, qui distinguent la réalité du chantier de sa situation contractuelle. La durée planifiée se mesure sur l’échéance initiale, faute de quoi l’on étirerait une courbe planifiée qui n’a pas été rebasée ; le retard, lui, se mesure sur l’échéance actualisée par les avenants de délai. Un avenant de délai ne déplace donc pas la date projetée, qui ne dépend que du rythme observé : il déplace l’échéance à laquelle on la compare, et réduit d’autant le retard affiché.'),

            P('Le temps écoulé s’arrête à la date du dernier relevé et non à la date du jour. Cette précaution évite de comparer deux instants différents : mesurer un avancement au 30 juin et un temps écoulé au 17 août reviendrait à ajouter au retard toute l’ancienneté de la saisie, laquelle est déjà signalée par l’indicateur de fraîcheur.'),

            Titre('3.5. Exemple', 3),

            P('Soit un projet démarré le 4 novembre 2024, dont l’échéance contractuelle est fixée à vingt-quatre mois, et dont l’avancement constaté au 30 juin 2026 atteint 32,13 pour cent. La courbe planifiée situait cet avancement aux alentours du dixième mois.'),
            F('ES = 312 jours     AT = 603 jours     SPI(t) = 312 ÷ 603 = 0,517'),
            F('durée estimée = 730 ÷ 0,517 ≈ 1 412 jours     retard projeté ≈ 682 jours'),

            P('Le décalage instantané, différence entre le temps écoulé et le temps acquis, s’établit à 291 jours, soit neuf mois et six dixièmes — valeur à rapprocher des neuf mois lus graphiquement par la mission de contrôle sur la même courbe.'),

            Legende('Tableau 4 — Seuils de la fin projetée', true),
            Tableau(['Niveau', 'Retard projeté', 'Traitement'], [
                ['Dans les temps', '15 jours ou moins', 'Vert'],
                ['À surveiller', '16 à 60 jours', 'Ambre'],
                ['Critique', 'plus de 60 jours', 'Rouge'],
            ], [24, 40, 36], [0, 1, 2]),
            Legende('Source : élaboration personnelle'),

            Titre('3.6. Apport de la méthode retenue et limites subsistantes', 3),

            P('La première version de cet indicateur extrapolait linéairement le rythme observé depuis le premier jour. Cette méthode a été abandonnée : l’avancement d’un chantier suit une courbe en S — lent pendant l’installation, rapide en régime établi, de nouveau lent aux finitions — de sorte qu’une extrapolation linéaire produit une date excessivement pessimiste en début de projet. La valeur acquise temporelle corrige ce défaut en se référant à la courbe planifiée elle-même, qui a la forme du chantier, plutôt qu’à un rythme moyen.'),

            P('Trois limites subsistent néanmoins. La projection n’est calculable que si une courbe planifiée existe, c’est-à-dire si des avancements prévus ont été saisis mois après mois : à défaut, la carte porte un tiret. Elle demeure ensuite d’autant plus incertaine que l’avancement est faible, l’interpolation portant alors sur les premiers points de la courbe. Elle suppose enfin que le rythme observé se maintienne, hypothèse qu’aucune méthode ne peut lever et qu’il convient d’énoncer plutôt que de laisser croire à une prédiction.'),

            Titre('3.7. L’écart physique-budget', 3),

            P('Cet indicateur mesure le décalage entre ce qui est construit et ce qui est payé. Il porte le nom parlant d’effet de façade : il révèle les opérations dont les décaissements progressent plus vite que les ouvrages.'),

            F('écart (en points) = avancement physique − taux d’exécution budgétaire'),

            Legende('Tableau 5 — Lecture de l’écart', true),
            Tableau(['Signe', 'Interprétation'], [
                ['Écart négatif', 'Le décaissement devance la réalisation : risque d’avances non justifiées ou de surfacturation'],
                ['Écart positif', 'Des travaux sont réalisés mais non encore payés : risque de tension de trésorerie pour l’entreprise'],
            ], [20, 80]),
            Legende('Source : élaboration personnelle'),

            Legende('Tableau 6 — Seuils de l’écart physique-budget', true),
            Tableau(['Niveau', 'Écart absolu', 'Traitement'], [
                ['Aligné', '10 points ou moins', 'Situation normale ; aucun signalement'],
                ['À surveiller', '11 à 20 points', 'Signalement en ambre'],
                ['Critique', 'plus de 20 points', 'Signalement en rouge'],
            ], [22, 26, 52], [0, 1]),
            Legende('Source : élaboration personnelle'),

            P('Un écart défavorable au maître d’ouvrage est signalé en rouge ou en ambre ; l’écart inverse, qui pèse sur l’entreprise, est signalé à titre informatif. Cette asymétrie est assumée : l’outil est celui du maître d’ouvrage.'),

            Titre('3.8. Le retard au démarrage des ouvrages', 3),

            P('Ce dernier indicateur a été ajouté en cours de développement, à la suite d’un constat de terrain : plusieurs ouvrages du chantier retenu comme cas d’étude avaient commencé plusieurs mois après la date portée au planning contractuel, sans que rien dans l’application ne permît de le savoir. L’avancement consolidé enregistrait fidèlement la conséquence — un taux faible — mais aucune donnée n’en désignait la cause.'),

            P('Chaque ouvrage porte désormais son calendrier contractuel : durée, dates de début et de fin prévues, dates de début et de fin réellement constatées. Le retard au démarrage s’en déduit.'),

            F('retard = date de début réelle − date de début prévue'),
            F('si l’ouvrage n’a pas démarré : retard = date du jour − date de début prévue'),

            P('Un démarrage anticipé n’est pas compté comme un retard, la valeur étant bornée à zéro. Un ouvrage non démarré voit en revanche son retard croître chaque jour, ce qui est le comportement attendu : l’attente se prolonge tant que le chantier ne s’ouvre pas.'),

            P('La portée de cet indicateur diffère de celle des précédents, et c’est ce qui fait son intérêt. Les indices de performance mesurent un effet ; le retard au démarrage désigne une cause, et il la localise sur un ouvrage précis, c’est-à-dire sur un objet à propos duquel une décision peut être prise. La tolérance est paramétrable, fixée par défaut à quinze jours, et l’alerte correspondante devient critique au-delà d’un trimestre.'),

            // ══════════════════════════════════════════ 4. financiers
            Titre('4. Les indicateurs financiers contractuels', 2),
            Titre('4.1. La base commune : le marché actualisé', 3),

            P('Les indicateurs de cette famille se rapportent à une même référence, qui n’est jamais saisie mais toujours déduite.'),

            F('marché actualisé = montant initial du marché + Σ des avenants approuvés'),

            P('Ce choix de conception est déterminant. En retenant le montant actualisé plutôt que le montant initial, l’application garantit que les taux affichés coïncident à tout instant avec la situation juridique du marché. Un avenant approuvé modifie immédiatement la base de calcul, sans intervention manuelle et sans risque de divergence entre le suivi et les pièces contractuelles. Le même principe vaut pour les délais : l’échéance actualisée est la date de fin initiale augmentée des prolongations accordées.'),

            P('Il en résulte qu’un taux peut varier sans qu’aucun mouvement financier ne se soit produit : l’approbation d’un avenant en hausse augmente le dénominateur et fait mécaniquement baisser le taux d’encaissement. Ce comportement est correct, mais il doit être connu de ceux qui lisent les cartes.'),

            P('Seul un avenant approuvé est enregistrable. Une demande en instruction auprès du bailleur ne peut être portée au marché, sous peine d’afficher une échéance ou un montant qui n’existent pas encore.'),

            Titre('4.2. Le reste à facturer', 3),

            F('montant = marché actualisé − Σ des montants bruts des décomptes'),
            F('taux = (montant ÷ marché actualisé) × 100'),

            P('Le calcul retient les montants bruts des décomptes, c’est-à-dire avant déduction des récupérations d’avance et des pénalités. Ce choix est cohérent : ce que l’on veut mesurer est le volume de travaux constatés, non la somme effectivement décaissée à ce titre.'),

            Titre('4.3. L’encaissé', 3),

            F('montant = Σ des montants bruts facturés + avances accordées'),
            F('taux = (montant ÷ marché actualisé) × 100'),

            P('L’inclusion des avances — de démarrage comme d’approvisionnement — constitue le point essentiel. Une avance est versée avant tout travail exécuté ; l’ignorer reviendrait à sous-estimer l’engagement financier réel du maître d’ouvrage, parfois de plusieurs milliards.'),

            P('Le taux d’encaissement et le taux de reste à facturer ne se complètent pas à cent pour cent, et cette apparente contradiction doit être documentée dans l’interface. Les deux cartes mesurent des grandeurs différentes : le reste à facturer ne porte que sur les travaux constatés par décompte, tandis que l’encaissé intègre en outre les avances. Un projet dont aucun décompte n’a été établi mais qui a reçu des avances affichera ainsi cent pour cent de reste à facturer et un encaissement non nul. À défaut d’explication, tout lecteur attentif conclura à une anomalie de calcul, et la crédibilité de l’ensemble des cartes s’en trouvera atteinte.'),

            Titre('4.4. L’exposition aux avances', 3),

            F('montant = max(0 ; avances accordées − Σ des récupérations opérées sur les décomptes)'),
            F('taux = (montant restant ÷ avances accordées) × 100'),

            P('Cet indicateur mesure le risque financier porté par le maître d’ouvrage : les sommes avancées à l’entreprise que celle-ci n’a pas encore remboursées. La borne inférieure à zéro empêche l’affichage d’une exposition négative en cas de sur-récupération, situation qui relèverait d’une anomalie de saisie plutôt que d’une réalité financière.'),

            P('La carte est affichée sur fond rouge tant qu’il subsiste des avances à rembourser, et redevient neutre une fois le solde apuré. Ce traitement binaire est volontairement sévère : il maintient l’exposition sous les yeux du gestionnaire jusqu’à son extinction complète.'),

            Titre('4.5. Lecture croisée', 3),

            P('Prises isolément, ces cartes renseignent sur des flux. Leur intérêt réside dans leur rapprochement mutuel et avec l’avancement physique.'),

            Legende('Tableau 7 — Configurations caractéristiques', true),
            Tableau(['Configuration observée', 'Lecture'], [
                ['Encaissement élevé, avancement faible', 'Le décaissement devance la réalisation ; vérifier la justification des avances et la réalité du service fait'],
                ['Reste à facturer élevé, avancement élevé', 'Des travaux réalisés ne sont pas facturés ; risque de rattrapage brutal et de tension de trésorerie'],
                ['Exposition stable malgré des décomptes', 'Les récupérations d’avance ne sont pas opérées sur les décomptes ; vérifier leur établissement'],
                ['Exposition apurée, avancement moyen', 'Situation normale d’un marché en régime établi'],
            ], [34, 66]),
            Legende('Source : élaboration personnelle'),

            Titre('4.6. Limites', 3),

            P('Les cartes dépendent entièrement de l’enregistrement des décomptes. Un décompte transmis mais non saisi produit mécaniquement une surestimation du reste à facturer et une sous-estimation de l’encaissement. La règle d’alerte relative aux décomptes en attente répond partiellement à ce risque.'),

            P('La notion d’avance recouvre plusieurs réalités. Avance de démarrage et avance d’approvisionnement obéissent à des règles de récupération distinctes ; leur agrégation dans une carte unique simplifie la lecture mais masque cette distinction.'),

            P('Le taux d’encaissement peut dépasser cent pour cent, situation théoriquement possible en cas d’avances élevées cumulées à une facturation avancée, et qui doit être traitée explicitement dans l’affichage.'),

            // ══════════════════════════════════════════ 5. alertes
            Titre('5. Les alertes, les seuils et la sémantique de l’affichage', 2),
            Titre('5.1. La carte des alertes ouvertes', 3),

            F('valeur = nombre d’alertes du projet dont l’indicateur de résolution est faux'),

            P('Le fond de la carte devient rouge dès qu’une alerte au moins est ouverte, et reste neutre lorsque aucune ne l’est. Ce traitement binaire est délibéré : le nombre importe moins que l’existence.'),

            Titre('5.2. Le cycle de vie d’une alerte', 3),

            P('Les onze règles de détection sont réévaluées à chaque saisie et à chaque import. Trois mécanismes gouvernent le cycle de vie des alertes qu’elles produisent.'),

            Puce('La non-duplication : une alerte déjà ouverte pour un projet et un motif donnés n’est pas recréée à chaque évaluation, seule sa gravité étant réajustée si l’écart s’aggrave.'),
            Puce('La clôture automatique : dès que la condition ayant déclenché l’alerte cesse d’être remplie, celle-ci est refermée sans intervention.'),
            Puce('La traçabilité du traitement : chaque alerte peut être commentée puis marquée comme traitée, avec consignation d’une note de résolution.'),

            P('La clôture automatique mérite d’être soulignée. Sans elle, la liste se remplirait progressivement de signalements périmés et perdrait toute valeur d’avertissement. Un dispositif d’alerte qui ne se vide jamais cesse d’être lu.'),

            Titre('5.3. Les seuils : nature et fondement', 3),

            P('Il importe d’énoncer clairement un point de méthode. Aucun référentiel de management de projet ne fixe de valeur numérique de déclenchement d’alerte. Les normes imposent à l’organisation de définir des seuils de contrôle et d’analyser les écarts qui les dépassent ; elles laissent la valeur à son appréciation.'),

            P('Le caractère paramétrable des seuils n’est donc pas une faiblesse de conception : c’est précisément ce que le référentiel attend. Inscrire des valeurs fixes dans le code reviendrait à imposer à l’organisation un jugement qui lui appartient. Les seuils sont en conséquence rassemblés dans un écran d’administration, où ils sont modifiables sans intervention sur le code, chacun étant borné et documenté.'),

            Legende('Tableau 8 — Nature des seuils employés', true),
            Tableau(['Catégorie', 'Statut et conséquence'], [
                ['Seuils réglementaires', 'Fixés par un texte en vigueur. Ils s’imposent et ne sont pas négociables. Le cumul des avenants en relève.'],
                ['Seuils de référence professionnelle', 'Issus d’un référentiel documenté mais non contraignant. Ils fournissent un ordre de grandeur défendable.'],
                ['Conventions internes', 'Définies par l’unité de gestion. Elles doivent être assumées comme telles, et pouvoir être modifiées à l’expérience.'],
            ], [30, 70]),
            Legende('Source : élaboration personnelle'),

            P('Il est essentiel, dans toute présentation de l’outil, de ne pas présenter une convention interne comme une norme. Un seuil affirmé comme normatif sans fondement est une affirmation vérifiable, et donc contestable.'),

            Titre('5.4. Recommandation de paramétrage', 3),

            Puce('Les seuils relevant d’un texte réglementaire doivent être paramétrés à la valeur du texte, et un second seuil d’anticipation placé en deçà afin que l’alerte se déclenche avant que le plafond ne soit atteint.'),
            Puce('Les seuils de référence professionnelle doivent être documentés avec leur source, de sorte que la justification survive au départ de celui qui les a fixés.'),
            Puce('Les conventions internes doivent être revues périodiquement au vu du taux de déclenchement observé. Un seuil qui déclenche sur tous les projets ne discrimine rien ; un seuil qui ne déclenche jamais ne sert à rien.'),

            Titre('5.5. Le code couleur', 3),

            P('La couleur d’une carte traduit un niveau de gravité et non une valeur brute. Elle permet de repérer l’anomalie sans lire le chiffre, ce qui est l’objet même d’un tableau de bord.'),

            Legende('Tableau 9 — Récapitulatif des seuils d’affichage', true),
            Tableau(['Carte', 'Niveaux'], [
                ['SPI et CPI', 'Vert si l’indice atteint ou dépasse l’unité, ambre en dessous'],
                ['Fraîcheur de la donnée', 'Fraîche jusqu’à 30 jours, à rafraîchir de 31 à 60, périmée au-delà'],
                ['Écart physique-budget', 'Aligné jusqu’à 10 points, à surveiller de 11 à 20, critique au-delà'],
                ['Fin projetée', 'Dans les temps jusqu’à 15 jours de retard, à surveiller de 16 à 60, critique au-delà'],
                ['Retard au démarrage d’un ouvrage', 'Toléré jusqu’à 15 jours, signalé au-delà, critique passé un trimestre'],
                ['Alertes ouvertes', 'Rouge dès une alerte ouverte, neutre sinon'],
                ['Exposition aux avances', 'Rouge tant que des avances restent dues, neutre une fois apurées'],
            ], [32, 68]),
            Legende('Source : élaboration personnelle'),

            P('Une précaution d’accessibilité s’impose : la couleur ne doit jamais constituer le seul porteur de l’information. Un libellé textuel de niveau doit l’accompagner, afin que la carte reste lisible en impression noir et blanc comme par un utilisateur atteint de dyschromatopsie.'),

            Titre('5.6. La sémantique du tiret', 3),

            P('Un tiret ne signale jamais une valeur nulle. Il indique que l’indicateur n’est pas calculable en l’état. Cette distinction est fondamentale et doit être comprise de tous les utilisateurs.'),

            Legende('Tableau 10 — Causes d’un indicateur non calculable', true),
            Tableau(['Cause', 'Cartes concernées', 'Donnée manquante à saisir'], [
                ['Division par zéro', 'SPI, CPI', 'Aucun relevé financier : valeur planifiée ou coût réel cumulé nul'],
                ['Absence de mesure de référence', 'Fraîcheur de la donnée', 'Aucune saisie d’avancement physique jamais enregistrée'],
                ['Courbe planifiée absente', 'Fin projetée', 'Aucun avancement prévu saisi : le temps acquis ne peut être situé'],
                ['Avancement nul', 'Fin projetée', 'Aucune progression constatée : le temps acquis est nul'],
                ['Grandeurs toutes nulles', 'Écart physique-budget', 'Avancement et taux de décaissement simultanément nuls'],
                ['Date de début prévue absente', 'Retard au démarrage', 'Calendrier contractuel de l’ouvrage non renseigné'],
            ], [26, 24, 50]),
            Legende('Source : élaboration personnelle'),

            P('Le tiret constitue ainsi une information à part entière : il désigne précisément la donnée qu’il reste à saisir pour que l’indicateur devienne exploitable. Il conviendrait de faire apparaître cette cause au survol de la carte, afin que l’utilisateur sache quoi faire plutôt que de constater une absence.'),

            P('À l’inverse, un zéro affiché est une véritable mesure. Un avancement physique de zéro pour cent signifie que des ouvrages existent, qu’ils ont été saisis, et qu’aucun n’a progressé. La différence entre les deux affichages est celle qui sépare l’ignorance du constat.'),

            // ══════════════════════════════════════════ principe général
            Titre('6. Principe général de la fiche indicateurs', 2),

            P('Aucune des dix cartes n’est renseignée à la main. Toutes sont recalculées à chaque saisie et à chaque import, à partir des avancements physiques, des relevés financiers, des décomptes, des avenants, des calendriers d’ouvrages, des jalons et des alertes.'),

            P('Ce principe emporte trois conséquences pratiques. La fiche ne peut pas diverger des données de base, puisqu’elle en procède intégralement. Aucun écart ne peut apparaître entre les valeurs affichées, celles des rapports exportés et celles qui déclenchent les alertes, ces trois usages partageant les mêmes calculs. Enfin, la qualité des indicateurs ne dépend plus que de la régularité et de la sincérité des saisies — ce que l’outil ne peut pas garantir, et qui relève de l’organisation.'),

            P('Une composition antérieure prévoyait en outre un score de santé synthétique, agrégeant en une note unique les composantes de délai, de coût, de fraîcheur et d’alerte. Elle a été abandonnée. Une note unique donne l’illusion de la synthèse tout en masquant ce qui la compose : deux projets peuvent obtenir le même score pour des raisons opposées, et le lecteur perd précisément l’information dont il a besoin pour décider. La fiche présente désormais les composantes elles-mêmes, dont la lecture conjointe est plus exigeante mais ne trompe pas.'),
        ],
    }],
});

const chemin = process.argv[2];
Packer.toBuffer(doc).then((b) => {
    writeFileSync(chemin, b);
    console.log('Écrit : ' + chemin);
});
