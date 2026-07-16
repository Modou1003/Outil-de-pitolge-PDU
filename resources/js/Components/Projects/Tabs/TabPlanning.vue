<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/Composables/useAuth';
import BuildingWorkModal from '@/Components/Projects/Forms/BuildingWorkModal.vue';
import LotFormModal from '@/Components/Projects/Forms/LotFormModal.vue';
import MilestoneFormModal from '@/Components/Projects/Forms/MilestoneFormModal.vue';

const props = defineProps({
    project: { type: Object, required: true },
    building_works: { type: Array, default: () => [] },
    lots: { type: Array, default: () => [] },
    milestones: { type: Array, required: true },
});

const { hasPermission } = useAuth();
const canManage = computed(() => hasPermission('manage_physical'));

// ── Navigation maître → détail ────────────────────────────────────────────
const selectedWorkId = ref(null);
const selectedWork = computed(() => props.building_works.find((w) => w.id === selectedWorkId.value) || null);
const openWork = (w) => { selectedWorkId.value = w.id; };
const backToList = () => { selectedWorkId.value = null; };

// Lots et jalons rattachés à l'ouvrage sélectionné
const workLots = computed(() => props.lots.filter((l) => l.building_work_id === selectedWorkId.value));
const workMilestones = computed(() => props.milestones.filter((m) => m.building_work_id === selectedWorkId.value));

const lotsCount = (w) => props.lots.filter((l) => l.building_work_id === w.id).length;
const milestonesCount = (w) => props.milestones.filter((m) => m.building_work_id === w.id).length;


// ── Modales ────────────────────────────────────────────────────────────────
const showBuildingWorkModal = ref(false);
const editingWork = ref(null);
const openCreateBuildingWork = () => { editingWork.value = null; showBuildingWorkModal.value = true; };
const openEditBuildingWork = (w) => { editingWork.value = w; showBuildingWorkModal.value = true; };
const removeBuildingWork = (w) => {
    if (!confirm(`Supprimer l'ouvrage « ${w.name} » ? Ses lots et jalons seront également supprimés.`)) return;
    if (selectedWorkId.value === w.id) selectedWorkId.value = null;
    router.delete(route('projects.building-works.destroy', [props.project.id, w.id]), { preserveScroll: true });
};

const workStatusOptions = [
    { value: 'not_started', label: 'Non commencé' },
    { value: 'in_progress', label: 'En cours' },
    { value: 'on_hold', label: 'En pause' },
    { value: 'completed', label: 'Terminé' },
    { value: 'cancelled', label: 'Annulé' },
];
const updateBuildingWorkStatus = (w, status) => {
    if (status === w.status) return;
    router.put(
        route('projects.building-works.update', [props.project.id, w.id]),
        { name: w.name, status },
        {
            preserveScroll: true,
            onError: (errors) => { if (errors.status) alert(errors.status); },
        },
    );
};

const showLotModal = ref(false);
const editingLot = ref(null);
const openCreateLot = () => { editingLot.value = null; showLotModal.value = true; };
const openEditLot = (l) => { editingLot.value = l; showLotModal.value = true; };
const removeLot = (l) => {
    if (!confirm(`Supprimer le lot ${l.code} ?`)) return;
    router.delete(route('projects.lots.destroy', [props.project.id, l.id]), { preserveScroll: true });
};

const showMilestoneModal = ref(false);
const editingMilestone = ref(null);
const openCreateMilestone = () => { editingMilestone.value = null; showMilestoneModal.value = true; };
const openEditMilestone = (m) => { editingMilestone.value = m; showMilestoneModal.value = true; };
const removeMilestone = (m) => {
    if (!confirm(`Supprimer le jalon « ${m.name} » ?`)) return;
    router.delete(route('projects.milestones.destroy', [props.project.id, m.id]), { preserveScroll: true });
};
const markReached = (m) => {
    const today = new Date().toISOString().slice(0, 10);
    const date = prompt('Date à laquelle le jalon a été atteint (YYYY-MM-DD) :', today);
    if (!date) return;
    router.patch(route('projects.milestones.reach', [props.project.id, m.id]), { actual_date: date }, { preserveScroll: true });
};

// ── Styles ───────────────────────────────────────────────────────────────
const statusColors = {
    not_started: 'bg-gray-300',
    in_progress: 'bg-amber-400',
    on_hold: 'bg-orange-400',
    completed: 'bg-emerald-500',
    cancelled: 'bg-red-400',
};

const statusBadge = {
    not_started: 'bg-gray-100 text-gray-700',
    in_progress: 'bg-amber-100 text-amber-700',
    on_hold: 'bg-orange-100 text-orange-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
};

const milestoneStyle = {
    pending: 'border-indigo-400 bg-white text-indigo-700',
    reached: 'border-emerald-500 bg-emerald-50 text-emerald-800',
    missed: 'border-red-500 bg-red-50 text-red-800',
    cancelled: 'border-gray-300 bg-gray-50 text-gray-600',
};

const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—';

// ── Gantt (limité à l'ouvrage sélectionné) ─────────────────────────────────
const timeRange = computed(() => {
    const dates = [];
    workLots.value.forEach((l) => {
        if (l.planned_start_date) dates.push(new Date(l.planned_start_date).getTime());
        if (l.planned_end_date) dates.push(new Date(l.planned_end_date).getTime());
    });
    workMilestones.value.forEach((m) => {
        if (m.planned_date) dates.push(new Date(m.planned_date).getTime());
    });
    if (!dates.length) return null;
    const min = Math.min(...dates);
    const max = Math.max(...dates);
    return { min, max, span: Math.max(1, max - min) };
});

const barLeft = (lot) => {
    if (!timeRange.value || !lot.planned_start_date) return '0%';
    const start = new Date(lot.planned_start_date).getTime();
    return `${((start - timeRange.value.min) / timeRange.value.span) * 100}%`;
};

const barWidth = (lot) => {
    if (!timeRange.value || !lot.planned_start_date || !lot.planned_end_date) return '5%';
    const start = new Date(lot.planned_start_date).getTime();
    const end = new Date(lot.planned_end_date).getTime();
    return `${((end - start) / timeRange.value.span) * 100}%`;
};

const milestoneLeft = (m) => {
    if (!timeRange.value || !m.planned_date) return '0%';
    const d = new Date(m.planned_date).getTime();
    return `${((d - timeRange.value.min) / timeRange.value.span) * 100}%`;
};

const monthsAxis = computed(() => {
    if (!timeRange.value) return [];
    const labels = [];
    const start = new Date(timeRange.value.min);
    start.setDate(1);
    const end = new Date(timeRange.value.max);
    while (start <= end) {
        labels.push({
            label: start.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' }),
            position: ((start.getTime() - timeRange.value.min) / timeRange.value.span) * 100,
        });
        start.setMonth(start.getMonth() + 1);
    }
    return labels;
});
</script>

<template>
    <div class="space-y-4">
        <!-- ═══════════ VUE LISTE DES OUVRAGES ═══════════ -->
        <div v-if="!selectedWork" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Ouvrages ({{ building_works.length }})</h3>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-700"
                    @click="openCreateBuildingWork"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Ajouter un ouvrage
                </button>
            </div>

            <div v-if="!building_works.length" class="px-5 py-16 text-center text-sm text-gray-500">
                Aucun ouvrage pour ce projet. Cliquez sur « Ajouter un ouvrage » pour commencer.
            </div>

            <ul v-else class="divide-y divide-gray-100">
                <li
                    v-for="w in building_works"
                    :key="w.id"
                    class="flex cursor-pointer items-center justify-between gap-3 px-5 py-4 transition hover:bg-indigo-50/50"
                    @click="openWork(w)"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m4-14h2m-2 4h2m6-4h2m-2 4h2" /></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-900">{{ w.name }}</p>
                            <p class="truncate text-xs text-gray-500">
                                <span class="font-mono">{{ w.code }}</span>
                                · {{ lotsCount(w) }} lot{{ lotsCount(w) > 1 ? 's' : '' }}
                                · {{ milestonesCount(w) }} jalon{{ milestonesCount(w) > 1 ? 's' : '' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <select
                            v-if="canManage"
                            :value="w.status"
                            class="cursor-pointer rounded-full border-0 py-0.5 pl-2 pr-6 text-[10px] font-medium focus:ring-2 focus:ring-indigo-300"
                            :class="statusBadge[w.status]"
                            title="Changer le statut de l'ouvrage"
                            @click.stop
                            @change="updateBuildingWorkStatus(w, $event.target.value)"
                        >
                            <option v-for="s in workStatusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <span v-else class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusBadge[w.status]">{{ w.status_label }}</span>
                        <template v-if="canManage">
                            <button class="rounded p-1 text-gray-400 hover:bg-indigo-100 hover:text-indigo-700" title="Modifier" @click.stop="openEditBuildingWork(w)">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828A2 2 0 0110 16.5L6 17l.5-4a2 2 0 01.586-1.414z" /></svg>
                            </button>
                            <button class="rounded p-1 text-gray-400 hover:bg-red-100 hover:text-red-700" title="Supprimer" @click.stop="removeBuildingWork(w)">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" /></svg>
                            </button>
                        </template>
                        <svg class="h-5 w-5 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </div>
                </li>
            </ul>
        </div>

        <!-- ═══════════ VUE DÉTAIL D'UN OUVRAGE (Gantt : lots + jalons) ═══════════ -->
        <template v-else>
            <!-- En-tête détail -->
            <div class="flex items-center justify-between rounded-xl bg-white px-5 py-3 shadow-sm ring-1 ring-gray-200">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                        @click="backToList"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        Ouvrages
                    </button>
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-semibold text-gray-900">{{ selectedWork.name }}</h3>
                        <p class="truncate text-xs text-gray-500"><span class="font-mono">{{ selectedWork.code }}</span> — {{ selectedWork.description || 'Aucune description' }}</p>
                    </div>
                </div>
                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusBadge[selectedWork.status]">{{ selectedWork.status_label }}</span>
            </div>

            <!-- Gantt de l'ouvrage -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Gantt — {{ selectedWork.name }}</h3>
                </div>
                <div v-if="!workLots.length" class="py-16 text-center text-sm text-gray-500">
                    Aucun lot défini pour cet ouvrage. Ajoutez des lots ci-dessous pour construire le planning.
                </div>
                <div v-else class="overflow-x-auto">
                    <div class="flex min-w-[800px]">
                        <div class="w-56 shrink-0 border-r border-gray-100">
                            <div class="h-8 border-b border-gray-100 bg-gray-50" />
                            <div v-for="lot in workLots" :key="lot.id" class="flex h-12 items-center border-b border-gray-100 px-3 text-xs">
                                <div class="min-w-0">
                                    <p class="truncate font-mono font-semibold text-gray-900">{{ lot.code }}</p>
                                    <p class="truncate text-[11px] text-gray-600">{{ lot.name }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="relative flex-1">
                            <div class="relative flex h-8 items-end border-b border-gray-100 bg-gray-50 text-[10px] text-gray-500">
                                <span
                                    v-for="(m, i) in monthsAxis"
                                    :key="i"
                                    class="absolute -translate-x-1/2 pb-1"
                                    :style="{ left: `${m.position}%` }"
                                >{{ m.label }}</span>
                            </div>
                            <div v-for="lot in workLots" :key="lot.id" class="relative h-12 border-b border-gray-100">
                                <div
                                    class="absolute top-3 h-6 rounded-md opacity-80 shadow"
                                    :class="statusColors[lot.status]"
                                    :style="{ left: barLeft(lot), width: barWidth(lot) }"
                                    :title="lot.name"
                                />
                                <div
                                    v-for="mst in workMilestones.filter((x) => {
                                        if (!x.planned_date || !timeRange) return false;
                                        if (x.project_lot_id === lot.id) return true;
                                        if (x.project_lot_id === null) {
                                            return new Date(x.planned_date) >= new Date(lot.planned_start_date) &&
                                                   new Date(x.planned_date) <= new Date(lot.planned_end_date);
                                        }
                                        return false;
                                    })"
                                    :key="'m-'+mst.id"
                                    class="absolute top-3 h-6 w-1 bg-purple-500"
                                    :style="{ left: milestoneLeft(mst) }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lots de l'ouvrage -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Lots ({{ workLots.length }})</h3>
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-700"
                        @click="openCreateLot"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        Ajouter un lot
                    </button>
                </div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Code</th><th class="px-4 py-2">Nom</th>
                            <th class="px-4 py-2">Statut</th>
                            <th class="px-4 py-2">Période</th>
                            <th v-if="canManage" class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="l in workLots" :key="l.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ l.code }}</td>
                            <td class="px-4 py-2">{{ l.name }}</td>
                            <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusBadge[l.status]">{{ l.status_label }}</span></td>
                            <td class="px-4 py-2 text-xs text-gray-600">{{ formatDate(l.planned_start_date) }} → {{ formatDate(l.planned_end_date) }}</td>
                            <td v-if="canManage" class="px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button class="rounded p-1 text-gray-500 hover:bg-indigo-50 hover:text-indigo-700" title="Modifier" @click="openEditLot(l)">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828A2 2 0 0110 16.5L6 17l.5-4a2 2 0 01.586-1.414z" /></svg>
                                    </button>
                                    <button class="rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-700" title="Supprimer" @click="removeLot(l)">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!workLots.length">
                            <td :colspan="canManage ? 5 : 4" class="px-4 py-6 text-center text-sm text-gray-500">Aucun lot.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Jalons de l'ouvrage -->
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Jalons ({{ workMilestones.length }})</h3>
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-700"
                        @click="openCreateMilestone"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        Nouveau jalon
                    </button>
                </div>
                <ol v-if="workMilestones.length" class="relative space-y-4 border-l-2 border-gray-200 pl-6">
                    <li v-for="m in workMilestones" :key="m.id" class="relative">
                        <span class="absolute -left-[9px] top-1 h-3 w-3 rounded-full border-2" :class="m.is_critical ? 'border-red-500 bg-red-500' : 'border-indigo-500 bg-white'" />
                        <div class="rounded-lg border p-3" :class="milestoneStyle[m.status]">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold">{{ m.name }}</p>
                                <span class="text-xs">
                                    {{ m.status_label }}
                                    <span v-if="m.is_late" class="ml-2 rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">En retard</span>
                                    <span v-if="m.is_critical" class="ml-2 text-red-600">★ critique</span>
                                </span>
                            </div>
                            <p class="mt-1 text-xs">Prévu : {{ formatDate(m.planned_date) }}<span v-if="m.actual_date"> · Atteint : {{ formatDate(m.actual_date) }}</span></p>
                            <div v-if="canManage" class="mt-2 flex flex-wrap gap-2">
                                <button v-if="m.status === 'pending'" class="rounded-md bg-emerald-600 px-2 py-1 text-[11px] font-medium text-white hover:bg-emerald-700" @click="markReached(m)">
                                    Valider (atteint)
                                </button>
                                <button class="rounded-md bg-white px-2 py-1 text-[11px] font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50" @click="openEditMilestone(m)">Modifier</button>
                                <button class="rounded-md bg-white px-2 py-1 text-[11px] font-medium text-red-600 ring-1 ring-red-200 hover:bg-red-50" @click="removeMilestone(m)">Supprimer</button>
                            </div>
                        </div>
                    </li>
                </ol>
                <p v-else class="py-6 text-center text-sm text-gray-500">Aucun jalon défini pour cet ouvrage.</p>
            </div>
        </template>

        <!-- ═══════════ MODALES ═══════════ -->
        <BuildingWorkModal
            :show="showBuildingWorkModal"
            :project="project"
            :work="editingWork"
            @close="showBuildingWorkModal = false"
        />
        <LotFormModal
            :show="showLotModal"
            :project-id="project.id"
            :lot="editingLot"
            :building-work-id="selectedWorkId"
            @close="showLotModal = false"
        />
        <MilestoneFormModal
            :show="showMilestoneModal"
            :project-id="project.id"
            :milestone="editingMilestone"
            :lots="workLots"
            :building-work-id="selectedWorkId"
            @close="showMilestoneModal = false"
        />
    </div>
</template>
