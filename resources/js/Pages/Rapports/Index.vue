<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAuth } from '@/Composables/useAuth';

const props = defineProps({
    projects: { type: Array, required: true },
    stats: { type: Object, required: true },
});

const { hasRole } = useAuth();
const canGenerate = computed(() => !hasRole('visiteur'));

const selectedProjectId = ref(props.projects[0]?.id ?? null);
const selectedProject = computed(() => props.projects.find((p) => p.id === selectedProjectId.value));

const generatingProject = ref(false);
const generatingGlobal = ref(false);

const downloadProjectReport = () => {
    if (!selectedProjectId.value) return;
    generatingProject.value = true;
    const url = route('rapports.projet', selectedProjectId.value);
    window.location.href = url;
    setTimeout(() => (generatingProject.value = false), 2500);
};

const downloadGlobalReport = () => {
    generatingGlobal.value = true;
    window.location.href = route('rapports.global');
    setTimeout(() => (generatingGlobal.value = false), 2500);
};

const generatingExcelProject = ref(false);
const generatingExcelGlobal = ref(false);

const downloadProjectExcel = () => {
    if (!selectedProjectId.value) return;
    generatingExcelProject.value = true;
    window.location.href = route('rapports.excel.projet', selectedProjectId.value);
    setTimeout(() => (generatingExcelProject.value = false), 2500);
};

const downloadGlobalExcel = () => {
    generatingExcelGlobal.value = true;
    window.location.href = route('rapports.excel.global');
    setTimeout(() => (generatingExcelGlobal.value = false), 2500);
};
</script>

<template>
    <Head title="Rapports" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Rapports & Exports</h2>
                <p class="text-sm text-gray-500">Génération de rapports PDF et exports Excel pour le comité de pilotage et le ministère.</p>
            </div>
        </template>

        <div class="space-y-6">
            <!-- KPI -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Projets</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.total_projects }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Actifs</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600">{{ stats.active_projects }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Terminés</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-600">{{ stats.completed_projects }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Alertes ouvertes</p>
                    <p class="mt-1 text-2xl font-bold" :class="stats.open_alerts > 0 ? 'text-red-600' : 'text-gray-900'">{{ stats.open_alerts }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Rapport projet -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Rapport détaillé d'un projet</h3>
                            <p class="mt-1 text-sm text-gray-500">Fiche A4 portrait : KPI EVM, lots, jalons, indicateurs, alertes.</p>
                        </div>
                        <span class="rounded-lg bg-indigo-50 p-2 text-indigo-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        </span>
                    </div>

                    <div class="mt-5">
                        <label class="mb-1 block text-xs font-medium text-gray-700">Projet</label>
                        <select v-model="selectedProjectId" class="w-full rounded-md border-gray-300 text-sm">
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.code }} — {{ p.title }}</option>
                        </select>
                    </div>

                    <div v-if="selectedProject" class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                        <p>Statut : <span class="font-medium">{{ selectedProject.status }}</span></p>
                        <p>Avancement : <span class="font-medium">{{ Number(selectedProject.progress_percentage).toFixed(1) }}%</span></p>
                    </div>

                    <button
                        type="button"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="!canGenerate || generatingProject || !selectedProjectId"
                        @click="downloadProjectReport"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" /></svg>
                        {{ generatingProject ? 'Génération…' : 'Télécharger le PDF' }}
                    </button>
                    <p v-if="!canGenerate" class="mt-2 text-center text-xs text-red-600">Les visiteurs ne peuvent pas générer de rapports.</p>
                </div>

                <!-- Rapport global -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Rapport global du portefeuille</h3>
                            <p class="mt-1 text-sm text-gray-500">Vue A4 paysage : synthèse globale, ventilation par région, tableau détaillé.</p>
                        </div>
                        <span class="rounded-lg bg-emerald-50 p-2 text-emerald-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h2v6H9zm4 0v-4h2v4h-2zm4 0V9h2v8h-2zM5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        </span>
                    </div>

                    <ul class="mt-5 space-y-2 text-sm text-gray-700">
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-600">✓</span> Synthèse : {{ stats.total_projects }} projets, {{ stats.active_projects }} actifs</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-600">✓</span> Ventilation budgétaire par région</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-600">✓</span> Tableau détaillé avec barres d'avancement</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-600">✓</span> Compteur d'alertes critiques</li>
                    </ul>

                    <button
                        type="button"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="!canGenerate || generatingGlobal"
                        @click="downloadGlobalReport"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" /></svg>
                        {{ generatingGlobal ? 'Génération…' : 'Télécharger le PDF' }}
                    </button>
                </div>
            </div>

            <!-- Exports Excel -->
            <h3 class="text-base font-semibold text-gray-900">Exports Excel (XLSX)</h3>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Excel projet -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Export Excel d'un projet</h3>
                            <p class="mt-1 text-sm text-gray-500">5 feuilles : Infos, Avancement physique, Avancement financier, Jalons, Courbe S.</p>
                        </div>
                        <span class="rounded-lg bg-emerald-50 p-2 text-emerald-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h4l3-7 4 14 3-7h4" /></svg>
                        </span>
                    </div>

                    <div class="mt-5">
                        <label class="mb-1 block text-xs font-medium text-gray-700">Projet</label>
                        <select v-model="selectedProjectId" class="w-full rounded-md border-gray-300 text-sm">
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.code }} — {{ p.title }}</option>
                        </select>
                    </div>

                    <button
                        type="button"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="!canGenerate || generatingExcelProject || !selectedProjectId"
                        @click="downloadProjectExcel"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" /></svg>
                        {{ generatingExcelProject ? 'Génération…' : 'Télécharger le XLSX' }}
                    </button>
                    <p v-if="!canGenerate" class="mt-2 text-center text-xs text-red-600">Les visiteurs ne peuvent pas générer d'exports.</p>
                </div>

                <!-- Excel global -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Export Excel global du programme</h3>
                            <p class="mt-1 text-sm text-gray-500">2 feuilles : Synthèse programme (1 ligne/projet) + Courbe S consolidée.</p>
                        </div>
                        <span class="rounded-lg bg-teal-50 p-2 text-teal-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h2v6H9zm4 0v-4h2v4h-2zm4 0V9h2v8h-2zM5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        </span>
                    </div>

                    <ul class="mt-5 space-y-2 text-sm text-gray-700">
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-600">✓</span> Tous les projets avec KPI (CPI, SPI, alertes)</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-600">✓</span> Formatage conditionnel : retards en rouge, à jour en vert</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-600">✓</span> Données brutes pour pivot et consolidation</li>
                    </ul>

                    <button
                        type="button"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-teal-700 disabled:opacity-50"
                        :disabled="!canGenerate || generatingExcelGlobal"
                        @click="downloadGlobalExcel"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" /></svg>
                        {{ generatingExcelGlobal ? 'Génération…' : 'Télécharger le XLSX' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
