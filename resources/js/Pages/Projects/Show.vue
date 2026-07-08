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
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-gray-200">
                        <p class="text-[10px] uppercase tracking-wide text-gray-500">Avancement</p>
                        <p class="font-semibold text-gray-900">{{ latestPhysicalReal !== null ? `${latestPhysicalReal.toFixed(1)}%` : '—' }}</p>
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
            <TabPlanning v-else-if="activeTab === 'planning'" :project="project" :lots="lots" :milestones="milestones" />
            <TabDocuments v-else-if="activeTab === 'documents'" :project="project" :documents="documents" :categories="document_categories" />
        </div>
    </AuthenticatedLayout>
</template>
