<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TabGeneral from '@/Components/Projects/Tabs/TabGeneral.vue';
import TabPhysical from '@/Components/Projects/Tabs/TabPhysical.vue';
import TabFinancial from '@/Components/Projects/Tabs/TabFinancial.vue';
import TabPlanning from '@/Components/Projects/Tabs/TabPlanning.vue';
import TabDocuments from '@/Components/Projects/Tabs/TabDocuments.vue';

const props = defineProps({
    project: { type: Object, required: true },
    building_works: { type: Array, default: () => [] },
    lots: { type: Array, required: true },
    milestones: { type: Array, required: true },
    physical_progresses: { type: Array, required: true },
    financial_progresses: { type: Array, required: true },
    indicator_trackings: { type: Array, required: true },
    indicator_catalog: { type: Array, default: () => [] },
    alerts: { type: Array, required: true },
    documents: { type: Array, default: () => [] },
    document_categories: { type: Object, default: () => ({}) },
    kpis: { type: Object, required: true },
    team_candidates: { type: Array, default: () => [] },
    can_manage_team: { type: Boolean, default: false },
    can_manage_indicators: { type: Boolean, default: false },
});

const latestPhysicalReal = computed(() => {
    const rows = Array.isArray(props.physical_progresses) ? props.physical_progresses : [];
    const sorted = [...rows].sort((a, b) => {
        const da = new Date(a?.measurement_date).getTime();
        const db = new Date(b?.measurement_date).getTime();
        const dateDiff = db - da;
        if (dateDiff !== 0) return dateDiff;
        return (Number(b?.id) || 0) - (Number(a?.id) || 0);
    });
    const latest = sorted[0];
    const v = latest?.actual_percentage;
    return typeof v === 'number' ? v : (v !== null && v !== undefined ? Number(v) : null);
});

// Avancement physique projet = dernière valeur « réelle moyenne » de la courbe
// en S (moyenne des saisies de la dernière période, sur les ouvrages physiques).
const latestAggregateReal = computed(() => {
    const physicalIds = new Set((props.lots || []).filter((l) => l.kind === 'physical').map((l) => Number(l.id)));
    const rows = (props.physical_progresses || []).filter((p) => p.project_lot_id != null && physicalIds.has(Number(p.project_lot_id)));
    if (!rows.length) return null;
    const grouped = new Map();
    rows.forEach((p) => {
        const key = String(p.period ?? '');
        if (!key) return;
        if (!grouped.has(key)) grouped.set(key, { sum: 0, count: 0 });
        const b = grouped.get(key);
        b.sum += Number(p.actual_percentage ?? 0);
        b.count += 1;
    });
    const periods = [...grouped.keys()].sort((a, b) => String(a).localeCompare(String(b)));
    if (!periods.length) return null;
    const b = grouped.get(periods[periods.length - 1]);
    return b.count ? b.sum / b.count : null;
});

// Indice de fraîcheur / fiabilité de la donnée
const freshness = computed(() => props.kpis?.data_freshness ?? null);
const freshnessStyle = {
    fresh: { bg: 'bg-emerald-50', ring: 'ring-emerald-200', text: 'text-emerald-700', label: 'text-emerald-600' },
    stale: { bg: 'bg-amber-50', ring: 'ring-amber-200', text: 'text-amber-700', label: 'text-amber-600' },
    critical: { bg: 'bg-red-50', ring: 'ring-red-200', text: 'text-red-700', label: 'text-red-600' },
    none: { bg: 'bg-white', ring: 'ring-gray-200', text: 'text-gray-400', label: 'text-gray-500' },
};
const freshnessTitle = computed(() => {
    const f = freshness.value;
    if (!f || !f.last_update) return "Aucun avancement physique saisi — les indicateurs ne reflètent pas le terrain.";
    const d = new Date(f.last_update).toLocaleDateString('fr-FR');
    const cov = f.coverage_rate !== null ? ` · ${f.lots_recent}/${f.lots_total} lot(s) à jour sur 30 j (${f.coverage_rate}%)` : '';
    return `Dernière saisie : ${d}${cov}`;
});

// Décalage physico-financier (effet de façade)
const physFin = computed(() => props.kpis?.physical_financial ?? null);
const physFinClasses = computed(() => {
    const p = physFin.value;
    const gray = { bg: 'bg-white', ring: 'ring-gray-200', text: 'text-gray-400', label: 'text-gray-500' };
    if (!p || p.level === 'none') return gray;
    if (p.level === 'aligned') return { bg: 'bg-emerald-50', ring: 'ring-emerald-200', text: 'text-emerald-700', label: 'text-emerald-600' };
    if (p.direction === 'overspend') {
        return p.level === 'critical'
            ? { bg: 'bg-red-50', ring: 'ring-red-200', text: 'text-red-700', label: 'text-red-600' }
            : { bg: 'bg-amber-50', ring: 'ring-amber-200', text: 'text-amber-700', label: 'text-amber-600' };
    }
    // underspend : réalisation en avance sur les paiements (informatif)
    return { bg: 'bg-sky-50', ring: 'ring-sky-200', text: 'text-sky-700', label: 'text-sky-600' };
});
const physFinTitle = computed(() => {
    const p = physFin.value;
    if (!p || p.level === 'none') return "Avancement physique et décaissement non renseignés.";
    const base = `Physique ${p.physical}% vs décaissé ${p.financial}%`;
    const eff = p.ratio !== null ? ` · efficience ${p.ratio}` : '';
    if (p.direction === 'overspend') return `${base}${eff} — décaissement en avance sur la réalisation (risque de surfacturation / avances).`;
    if (p.direction === 'underspend') return `${base}${eff} — réalisation en avance sur les paiements (décaissements en retard).`;
    return `${base}${eff} — physique et budget alignés.`;
});

// Score de santé global
const health = computed(() => props.kpis?.health ?? null);
const healthStyle = {
    healthy: { bar: 'bg-emerald-500', text: 'text-emerald-700', ring: 'ring-emerald-200', bg: 'bg-emerald-50', label: 'Bonne santé' },
    fair: { bar: 'bg-lime-500', text: 'text-lime-700', ring: 'ring-lime-200', bg: 'bg-lime-50', label: 'Correcte' },
    at_risk: { bar: 'bg-amber-500', text: 'text-amber-700', ring: 'ring-amber-200', bg: 'bg-amber-50', label: 'À risque' },
    critical: { bar: 'bg-red-500', text: 'text-red-700', ring: 'ring-red-200', bg: 'bg-red-50', label: 'Critique' },
    unknown: { bar: 'bg-gray-300', text: 'text-gray-500', ring: 'ring-gray-200', bg: 'bg-white', label: 'Non évaluable' },
};
const healthLevel = computed(() => health.value?.level ?? 'unknown');
const healthTitle = computed(() => {
    const h = health.value;
    if (!h || h.score === null) return "Pas assez de données pour évaluer la santé du projet.";
    const labels = { schedule: 'Planning', cost: 'Coût', facade: 'Façade', data: 'Donnée', milestones: 'Jalons', alerts: 'Alertes' };
    const parts = Object.entries(h.components || {})
        .filter(([, v]) => v !== null)
        .map(([k, v]) => `${labels[k]} ${v}`);
    return parts.length ? `Composantes — ${parts.join(' · ')}` : '';
});

// Date de fin projetée (au rythme réel)
const forecast = computed(() => props.kpis?.forecast_completion ?? null);
const forecastClasses = computed(() => {
    const f = forecast.value;
    const gray = { bg: 'bg-white', ring: 'ring-gray-200', text: 'text-gray-400', label: 'text-gray-500' };
    if (!f || f.level === 'none') return gray;
    if (f.level === 'done') return { bg: 'bg-emerald-50', ring: 'ring-emerald-200', text: 'text-emerald-700', label: 'text-emerald-600' };
    if (f.level === 'on_track') return { bg: 'bg-emerald-50', ring: 'ring-emerald-200', text: 'text-emerald-700', label: 'text-emerald-600' };
    if (f.level === 'watch') return { bg: 'bg-amber-50', ring: 'ring-amber-200', text: 'text-amber-700', label: 'text-amber-600' };
    return { bg: 'bg-red-50', ring: 'ring-red-200', text: 'text-red-700', label: 'text-red-600' };
});
const forecastMonth = (d) => d ? new Date(d).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' }) : '—';
const forecastValue = computed(() => {
    const f = forecast.value;
    if (!f) return '—';
    if (f.level === 'done') return 'Terminé';
    if (f.level === 'none') return '—';
    return forecastMonth(f.projected_end_date);
});
const forecastTitle = computed(() => {
    const f = forecast.value;
    if (!f) return '';
    if (f.level === 'done') return 'Chantier physiquement terminé (100 %).';
    if (f.level === 'none') {
        return f.reason === 'no_dates'
            ? "Dates de début/fin planifiées manquantes."
            : "Avancement physique insuffisant pour projeter une date.";
    }
    const proj = new Date(f.projected_end_date).toLocaleDateString('fr-FR');
    const plan = new Date(f.planned_end_date).toLocaleDateString('fr-FR');
    const d = f.delay_days;
    const verdict = d > 0 ? `retard projeté de ${d} j` : (d < 0 ? `avance de ${Math.abs(d)} j` : 'dans les temps');
    return `Au rythme actuel : fin le ${proj} (planifié : ${plan}) — ${verdict}.`;
});

const tabs = [
    { id: 'general', label: 'Informations générales', icon: 'info' },
    { id: 'physical', label: 'Avancement physique', icon: 'chart' },
    { id: 'financial', label: 'Avancement financier', icon: 'coins' },
    { id: 'planning', label: 'Planning', icon: 'calendar' },
    { id: 'documents', label: 'Documents', icon: 'file' },
];

const icons = {
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    chart: 'M3 3v18h18M7 14l4-4 4 4 5-5',
    coins: 'M12 8c-2.21 0-4 1.12-4 2.5S9.79 13 12 13s4 1.12 4 2.5S14.21 18 12 18m0-10V6m0 14v-2m0 0c-2.76 0-5-1.79-5-4m10 0c0 2.21-2.24 4-5 4',
    calendar: 'M8 7V3m8 4V3M3 11h18M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z',
    file: 'M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9l-7-7z M13 2v7h7',
};

const activeTab = ref('general');

const statusLabels = { draft: 'Brouillon', submitted: 'Soumis', approved: 'Approuvé', in_progress: 'En cours', on_hold: 'En pause', completed: 'Terminé', cancelled: 'Annulé', archived: 'Archivé' };
const statusBadges = { draft: 'bg-gray-100 text-gray-700', submitted: 'bg-blue-100 text-blue-700', approved: 'bg-indigo-100 text-indigo-700', in_progress: 'bg-amber-100 text-amber-700', on_hold: 'bg-orange-100 text-orange-700', completed: 'bg-emerald-100 text-emerald-700', cancelled: 'bg-red-100 text-red-700', archived: 'bg-slate-100 text-slate-700' };

const breadcrumbs = computed(() => ([
    { label: 'Accueil', href: route('dashboard') },
    { label: 'Projets', href: route('dashboard') },
    { label: props.project.code },
]));

const exportExcel = () => {
    window.location.href = route('projects.export', props.project.id);
};
</script>

<template>
    <Head :title="`${project.code} — ${project.title}`" />

    <AuthenticatedLayout :breadcrumbs="breadcrumbs">
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-mono text-xs text-gray-500">{{ project.code }}</p>
                    <h2 class="truncate text-xl font-semibold text-gray-900">{{ project.title }}</h2>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-full px-2 py-0.5 font-medium" :class="statusBadges[project.status]">
                            {{ statusLabels[project.status] }}
                        </span>
                        <span class="text-gray-500">{{ project.university?.name }}</span>
                        <span v-if="project.is_overdue" class="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-700">En retard</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div
                        v-if="health"
                        class="min-w-[150px] rounded-lg px-3 py-2 ring-1"
                        :class="[healthStyle[healthLevel].bg, healthStyle[healthLevel].ring]"
                        :title="healthTitle"
                    >
                        <p class="text-[10px] uppercase tracking-wide" :class="healthStyle[healthLevel].text">Santé du projet</p>
                        <div class="flex items-baseline gap-1">
                            <p class="text-lg font-bold" :class="healthStyle[healthLevel].text">
                                {{ health.score !== null ? health.score : '—' }}<span v-if="health.score !== null" class="text-xs font-medium">/100</span>
                            </p>
                            <span class="text-[10px] font-medium" :class="healthStyle[healthLevel].text">{{ healthStyle[healthLevel].label }}</span>
                        </div>
                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                            <div class="h-full transition-all" :class="healthStyle[healthLevel].bar" :style="{ width: (health.score ?? 0) + '%' }" />
                        </div>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-gray-200" title="Dernière valeur réelle moyenne saisie (dernière période de la courbe en S)">
                        <p class="text-[10px] uppercase tracking-wide text-gray-500">Avancement physique</p>
                        <p class="font-semibold text-gray-900">{{ latestAggregateReal !== null ? `${latestAggregateReal.toFixed(1)}%` : '—' }}</p>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-gray-200">
                        <p class="text-[10px] uppercase tracking-wide text-gray-500">SPI</p>
                        <p class="font-semibold" :class="kpis.spi && kpis.spi >= 1 ? 'text-emerald-600' : 'text-amber-600'">
                            {{ kpis.spi ? kpis.spi.toFixed(2) : '—' }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-gray-200">
                        <p class="text-[10px] uppercase tracking-wide text-gray-500">CPI</p>
                        <p class="font-semibold" :class="kpis.cpi && kpis.cpi >= 1 ? 'text-emerald-600' : 'text-amber-600'">
                            {{ kpis.cpi ? kpis.cpi.toFixed(2) : '—' }}
                        </p>
                    </div>
                    <div v-if="kpis.alerts_open > 0" class="rounded-lg bg-red-50 px-3 py-2 ring-1 ring-red-200">
                        <p class="text-[10px] uppercase tracking-wide text-red-600">Alertes ouvertes</p>
                        <p class="font-semibold text-red-700">{{ kpis.alerts_open }}</p>
                    </div>
                    <div
                        v-if="freshness"
                        class="rounded-lg px-3 py-2 ring-1"
                        :class="[freshnessStyle[freshness.level].bg, freshnessStyle[freshness.level].ring]"
                        :title="freshnessTitle"
                    >
                        <p class="text-[10px] uppercase tracking-wide" :class="freshnessStyle[freshness.level].label">Fraîcheur donnée</p>
                        <p class="font-semibold" :class="freshnessStyle[freshness.level].text">
                            <template v-if="freshness.days_since !== null">
                                {{ freshness.days_since }} j
                                <span v-if="freshness.coverage_rate !== null" class="text-[10px] font-normal opacity-75">· {{ freshness.coverage_rate }}%</span>
                            </template>
                            <template v-else>—</template>
                        </p>
                    </div>
                    <div
                        v-if="physFin"
                        class="rounded-lg px-3 py-2 ring-1"
                        :class="[physFinClasses.bg, physFinClasses.ring]"
                        :title="physFinTitle"
                    >
                        <p class="text-[10px] uppercase tracking-wide" :class="physFinClasses.label">Écart physique/budget</p>
                        <p class="font-semibold" :class="physFinClasses.text">
                            <template v-if="physFin.level !== 'none'">
                                {{ physFin.gap > 0 ? '+' : '' }}{{ physFin.gap }} pts
                            </template>
                            <template v-else>—</template>
                        </p>
                    </div>
                    <div
                        v-if="forecast"
                        class="rounded-lg px-3 py-2 ring-1"
                        :class="[forecastClasses.bg, forecastClasses.ring]"
                        :title="forecastTitle"
                    >
                        <p class="text-[10px] uppercase tracking-wide" :class="forecastClasses.label">Fin projetée</p>
                        <p class="font-semibold capitalize" :class="forecastClasses.text">
                            {{ forecastValue }}
                            <span
                                v-if="forecast.level !== 'none' && forecast.level !== 'done' && forecast.delay_days > 0"
                                class="text-[10px] font-normal opacity-75"
                            >· +{{ forecast.delay_days }} j</span>
                        </p>
                    </div>
                    <a
                        :href="route('rapports.projet', project.id)"
                        class="inline-flex items-center gap-1.5 self-start rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" /></svg>
                        PDF
                    </a>
                    <button
                        type="button"
                        @click.prevent="exportExcel"
                        class="inline-flex items-center gap-1.5 self-start rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5" /></svg>
                        Excel
                    </button>
                </div>
            </div>
        </template>

        <!-- Barre de tabs -->
        <div class="mb-4 overflow-x-auto rounded-xl bg-gradient-to-r from-green-900 to-emerald-800 shadow-lg">
            <nav class="flex min-w-max">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    type="button"
                    class="relative flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-medium uppercase tracking-wide transition"
                    :class="activeTab === t.id
                        ? 'border-orange-400 text-white bg-white/10'
                        : 'border-transparent text-green-100 hover:bg-white/10 hover:text-white'"
                    @click="activeTab = t.id"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="icons[t.icon]" />
                    </svg>
                    {{ t.label }}
                </button>
            </nav>
        </div>

        <!-- Contenu du tab actif -->
        <div>
            <TabGeneral
                v-if="activeTab === 'general'"
                :project="project"
                :alerts="alerts"
                :team-candidates="team_candidates"
                :can-manage-team="can_manage_team"
            />
            <TabPhysical v-else-if="activeTab === 'physical'" :project="project" :progresses="physical_progresses" :lots="lots" />
            <TabFinancial v-else-if="activeTab === 'financial'" :project="project" :progresses="financial_progresses" :kpis="kpis" :lots="lots" />
            <TabPlanning v-else-if="activeTab === 'planning'" :project="project" :building_works="building_works" :lots="lots" :milestones="milestones" />
            <TabDocuments v-else-if="activeTab === 'documents'" :project="project" :documents="documents" :categories="document_categories" />
        </div>
    </AuthenticatedLayout>
</template>
