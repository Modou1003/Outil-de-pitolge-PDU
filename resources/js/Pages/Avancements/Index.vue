<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/Composables/useAuth';
import AppLayout from '@/Layouts/AppLayout.vue';
import PhysicalProgressModal from '@/Components/Projects/Forms/PhysicalProgressModal.vue';
import FinancialProgressModal from '@/Components/Projects/Forms/FinancialProgressModal.vue';

const props = defineProps({
    projects: { type: Array, required: true },
    physicalProgresses: { type: Array, default: () => [] },
    financialProgresses: { type: Array, default: () => [] },
});

const { hasPermission } = useAuth();
const canWrite = computed(() => hasPermission('manage_physical'));        // tableau avancement physique
const canWriteFinancial = computed(() => hasPermission('manage_finances')); // tableau avancement financier

const activeTab = ref('physical');
const showPhysicalModal = ref(false);
const showFinancialModal = ref(false);
const editingPhysical = ref(null);
const editingFinancial = ref(null);
const selectedProject = ref(null);

const sortedProjects = computed(() => [...(props.projects || [])].sort((a, b) => a.title.localeCompare(b.title)));

const openPhysicalCreate = (project) => {
    selectedProject.value = project;
    editingPhysical.value = null;
    showPhysicalModal.value = true;
};

const openPhysicalEdit = (project, progress) => {
    selectedProject.value = project;
    editingPhysical.value = progress;
    showPhysicalModal.value = true;
};

const removePhysical = (project, progress) => {
    if (!confirm(`Supprimer la mesure d'avancement physique du ${progress.period} ?`)) return;
    router.delete(route('projects.physical.destroy', [project.id, progress.id]), { preserveScroll: true });
};

const openFinancialCreate = (project) => {
    selectedProject.value = project;
    editingFinancial.value = null;
    showFinancialModal.value = true;
};

const openFinancialEdit = (project, progress) => {
    selectedProject.value = project;
    editingFinancial.value = progress;
    showFinancialModal.value = true;
};

const removeFinancial = (project, progress) => {
    if (!confirm(`Supprimer la mesure d'avancement financier du ${progress.period} ?`)) return;
    router.delete(route('projects.financial.destroy', [project.id, progress.id]), { preserveScroll: true });
};

const projectPhysicalProgresses = computed(() =>
    selectedProject.value ? props.physicalProgresses.filter((p) => p.project_id === selectedProject.value.id) : []
);

const projectFinancialProgresses = computed(() =>
    selectedProject.value ? props.financialProgresses.filter((p) => p.project_id === selectedProject.value.id) : []
);

const latestPhysical = computed(() => {
    const sorted = [...projectPhysicalProgresses.value].sort((a, b) => b.period.localeCompare(a.period));
    return sorted[0] ?? null;
});

const latestFinancial = computed(() => {
    const sorted = [...projectFinancialProgresses.value].sort((a, b) => b.period.localeCompare(a.period));
    return sorted[0] ?? null;
});
</script>

<template>
    <AppLayout title="Saisie d'avancement">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="rounded-xl bg-gradient-to-r from-indigo-50 to-emerald-50 px-6 py-4 ring-1 ring-gray-200">
                <h1 class="text-2xl font-bold text-gray-900">Saisie d'avancement</h1>
                <p class="mt-1 text-sm text-gray-600">Enregistrez l'avancement physique et financier de vos projets</p>
            </div>

            <!-- Project Selector -->
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-100 px-6 py-4">
                    <label class="block text-sm font-semibold text-gray-700">Sélectionner un projet</label>
                    <select
                        v-model="selectedProject"
                        class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                    >
                        <option :value="null">— Choisir un projet —</option>
                        <option v-for="p in sortedProjects" :key="p.id" :value="p">
                            [{{ p.code }}] {{ p.title }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="selectedProject" class="space-y-6">
                <!-- Project Info Card -->
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <p class="text-xs font-medium uppercase text-gray-500">Code</p>
                        <p class="mt-1 text-lg font-bold text-gray-900">{{ selectedProject.code }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <p class="text-xs font-medium uppercase text-gray-500">Titre</p>
                        <p class="mt-1 text-lg font-bold text-gray-900">{{ selectedProject.title }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <p class="text-xs font-medium uppercase text-gray-500">Avancement global</p>
                        <p class="mt-1 text-lg font-bold text-emerald-700">{{ selectedProject.progress_percentage ?? 0 }}%</p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="flex border-b border-gray-100">
                        <button
                            @click="activeTab = 'physical'"
                            class="flex-1 border-b-2 px-6 py-3 text-center font-medium transition"
                            :class="activeTab === 'physical'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-600 hover:text-gray-900'"
                        >
                            <svg class="mb-1 inline h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Avancement physique
                        </button>
                        <button
                            @click="activeTab = 'financial'"
                            class="flex-1 border-b-2 px-6 py-3 text-center font-medium transition"
                            :class="activeTab === 'financial'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-600 hover:text-gray-900'"
                        >
                            <svg class="mb-1 inline h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Avancement financier
                        </button>
                    </div>

                    <!-- Physical Progress Tab -->
                    <div v-if="activeTab === 'physical'" class="p-6">
                        <!-- KPIs -->
                        <div class="mb-6 grid gap-3 md:grid-cols-3">
                            <div class="rounded-lg bg-indigo-50 p-3">
                                <p class="text-xs font-medium text-indigo-700">Dernière mesure</p>
                                <p class="mt-1 text-xl font-bold text-indigo-900">{{ latestPhysical?.actual_percentage?.toFixed(1) ?? '—' }}%</p>
                            </div>
                            <div class="rounded-lg bg-emerald-50 p-3">
                                <p class="text-xs font-medium text-emerald-700">Prévu</p>
                                <p class="mt-1 text-xl font-bold text-emerald-900">{{ latestPhysical?.planned_percentage?.toFixed(1) ?? '—' }}%</p>
                            </div>
                            <div class="rounded-lg" :class="(latestPhysical?.variance ?? 0) >= 0 ? 'bg-green-50' : 'bg-red-50'">
                                <p :class="(latestPhysical?.variance ?? 0) >= 0 ? 'text-green-700' : 'text-red-700'" class="text-xs font-medium">Écart</p>
                                <p :class="(latestPhysical?.variance ?? 0) >= 0 ? 'text-green-900' : 'text-red-900'" class="mt-1 text-xl font-bold">
                                    {{ (latestPhysical?.variance ?? 0) >= 0 ? '+' : '' }}{{ latestPhysical?.variance?.toFixed(1) ?? '—' }}
                                </p>
                            </div>
                        </div>

                        <!-- Physical Progress Table -->
                        <div class="overflow-hidden rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-3">
                                <h3 class="text-sm font-semibold text-gray-700">Historique des mesures physiques</h3>
                                <button
                                    v-if="canWrite"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-700"
                                    @click="openPhysicalCreate(selectedProject)"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Ajouter
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            <th class="px-4 py-2">Période</th>
                                            <th class="px-4 py-2">Date mesure</th>
                                            <th class="px-4 py-2 text-right">Prévu</th>
                                            <th class="px-4 py-2 text-right">Réel</th>
                                            <th class="px-4 py-2 text-right">Écart</th>
                                            <th class="px-4 py-2">Observations</th>
                                            <th v-if="canWrite" class="px-4 py-2 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <tr v-for="p in projectPhysicalProgresses" :key="p.id" class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-mono text-xs">{{ p.period }}</td>
                                            <td class="px-4 py-2 text-xs text-gray-600">{{ new Date(p.measurement_date).toLocaleDateString('fr-FR') }}</td>
                                            <td class="px-4 py-2 text-right text-indigo-700">{{ p.planned_percentage.toFixed(1) }}%</td>
                                            <td class="px-4 py-2 text-right font-medium text-emerald-700">{{ p.actual_percentage.toFixed(1) }}%</td>
                                            <td class="px-4 py-2 text-right font-semibold" :class="p.variance >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                                {{ p.variance >= 0 ? '+' : '' }}{{ p.variance.toFixed(1) }}
                                            </td>
                                            <td class="px-4 py-2 text-xs text-gray-600">{{ p.observations || '—' }}</td>
                                            <td v-if="canWrite" class="px-4 py-2 text-right">
                                                <div class="flex justify-end gap-1">
                                                    <button
                                                        class="rounded p-1 text-gray-500 hover:bg-indigo-50 hover:text-indigo-700"
                                                        title="Modifier"
                                                        @click="openPhysicalEdit(selectedProject, p)"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828A2 2 0 0110 16.5L6 17l.5-4a2 2 0 01.586-1.414z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        class="rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-700"
                                                        title="Supprimer"
                                                        @click="removePhysical(selectedProject, p)"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!projectPhysicalProgresses.length">
                                            <td :colspan="canWrite ? 7 : 6" class="px-4 py-6 text-center text-sm text-gray-500">
                                                Aucune mesure d'avancement physique.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Progress Tab -->
                    <div v-if="activeTab === 'financial'" class="p-6">
                        <!-- KPIs -->
                        <div class="mb-6 grid gap-3 md:grid-cols-3">
                            <div class="rounded-lg bg-indigo-50 p-3">
                                <p class="text-xs font-medium text-indigo-700">Dépensé</p>
                                <p class="mt-1 text-xl font-bold text-indigo-900">{{ latestFinancial?.actual_percentage?.toFixed(1) ?? '—' }}%</p>
                            </div>
                            <div class="rounded-lg bg-emerald-50 p-3">
                                <p class="text-xs font-medium text-emerald-700">Prévu</p>
                                <p class="mt-1 text-xl font-bold text-emerald-900">{{ latestFinancial?.planned_percentage?.toFixed(1) ?? '—' }}%</p>
                            </div>
                            <div class="rounded-lg" :class="(latestFinancial?.variance ?? 0) >= 0 ? 'bg-green-50' : 'bg-red-50'">
                                <p :class="(latestFinancial?.variance ?? 0) >= 0 ? 'text-green-700' : 'text-red-700'" class="text-xs font-medium">Écart</p>
                                <p :class="(latestFinancial?.variance ?? 0) >= 0 ? 'text-green-900' : 'text-red-900'" class="mt-1 text-xl font-bold">
                                    {{ (latestFinancial?.variance ?? 0) >= 0 ? '+' : '' }}{{ latestFinancial?.variance?.toFixed(1) ?? '—' }}
                                </p>
                            </div>
                        </div>

                        <!-- Financial Progress Table -->
                        <div class="overflow-hidden rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-3">
                                <h3 class="text-sm font-semibold text-gray-700">Historique des mesures financières</h3>
                                <button
                                    v-if="canWriteFinancial"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-700"
                                    @click="openFinancialCreate(selectedProject)"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Ajouter
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            <th class="px-4 py-2">Période</th>
                                            <th class="px-4 py-2">Date mesure</th>
                                            <th class="px-4 py-2 text-right">Prévu</th>
                                            <th class="px-4 py-2 text-right">Réel</th>
                                            <th class="px-4 py-2 text-right">Écart</th>
                                            <th class="px-4 py-2">Observations</th>
                                            <th v-if="canWriteFinancial" class="px-4 py-2 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <tr v-for="p in projectFinancialProgresses" :key="p.id" class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-mono text-xs">{{ p.period }}</td>
                                            <td class="px-4 py-2 text-xs text-gray-600">{{ new Date(p.measurement_date).toLocaleDateString('fr-FR') }}</td>
                                            <td class="px-4 py-2 text-right text-indigo-700">{{ p.planned_percentage.toFixed(1) }}%</td>
                                            <td class="px-4 py-2 text-right font-medium text-emerald-700">{{ p.actual_percentage.toFixed(1) }}%</td>
                                            <td class="px-4 py-2 text-right font-semibold" :class="p.variance >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                                {{ p.variance >= 0 ? '+' : '' }}{{ p.variance.toFixed(1) }}
                                            </td>
                                            <td class="px-4 py-2 text-xs text-gray-600">{{ p.observations || '—' }}</td>
                                            <td v-if="canWriteFinancial" class="px-4 py-2 text-right">
                                                <div class="flex justify-end gap-1">
                                                    <button
                                                        class="rounded p-1 text-gray-500 hover:bg-indigo-50 hover:text-indigo-700"
                                                        title="Modifier"
                                                        @click="openFinancialEdit(selectedProject, p)"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828A2 2 0 0110 16.5L6 17l.5-4a2 2 0 01.586-1.414z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        class="rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-700"
                                                        title="Supprimer"
                                                        @click="removeFinancial(selectedProject, p)"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!projectFinancialProgresses.length">
                                            <td :colspan="canWriteFinancial ? 7 : 6" class="px-4 py-6 text-center text-sm text-gray-500">
                                                Aucune mesure d'avancement financier.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modals -->
                <PhysicalProgressModal
                    v-if="selectedProject"
                    :show="showPhysicalModal"
                    :project-id="selectedProject.id"
                    :progress="editingPhysical"
                    :lots="selectedProject.lots ?? []"
                    @close="showPhysicalModal = false"
                />

                <FinancialProgressModal
                    v-if="selectedProject"
                    :show="showFinancialModal"
                    :project-id="selectedProject.id"
                    :progress="editingFinancial"
                    @close="showFinancialModal = false"
                />
            </div>

            <div v-else class="rounded-xl border-2 border-dashed border-gray-300 px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <p class="mt-4 text-gray-600">Sélectionnez un projet pour commencer</p>
            </div>
        </div>
    </AppLayout>
</template>
