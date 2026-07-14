<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/Composables/useAuth';
import BuildingWorkModal from '@/Components/Projects/Forms/BuildingWorkModal.vue';
import BuildingWorkDetailModal from '@/Components/Projects/Modals/BuildingWorkDetailModal.vue';
import LotFormModal from '@/Components/Projects/Forms/LotFormModal.vue';
import MilestoneFormModal from '@/Components/Projects/Forms/MilestoneFormModal.vue';

const props = defineProps({
    project: { type: Object, required: true },
    buildingWorks: { type: Array, default: () => [] },
    lots: { type: Array, default: () => [] },
    milestones: { type: Array, required: true },
});

const { hasPermission } = useAuth();
const canManage = computed(() => hasPermission('manage_physical'));

// Modales et états
const showBuildingWorkModal = ref(false);
const showBuildingWorkDetailModal = ref(false);
const showLotModal = ref(false);
const showMilestoneModal = ref(false);

const selectedBuildingWork = ref(null);
const editingLot = ref(null);
const editingMilestone = ref(null);
const selectedLotForMilestone = ref(null);

// Actions sur ouvrages
const openCreateBuildingWork = () => { showBuildingWorkModal.value = true; };
const openViewBuildingWork = (work) => { selectedBuildingWork.value = work; showBuildingWorkDetailModal.value = true; };
const openEditBuildingWork = (work) => { router.visit(route('projects.building-works.show', [props.project.id, work.id])); };
const removeBuildingWork = (work) => {
    if (!confirm(`Supprimer l'ouvrage « ${work.name} » ?`)) return;
    router.delete(route('projects.building-works.destroy', [props.project.id, work.id]), { preserveScroll: true });
};

// Actions sur lots
const openCreateLot = () => { 
    editingLot.value = { building_work_id: selectedBuildingWork.value?.id };
    showBuildingWorkDetailModal.value = false;
    showLotModal.value = true; 
};
const openEditLot = (lot) => { editingLot.value = lot; showLotModal.value = true; };
const removeLot = (lot) => {
    if (!confirm(`Supprimer le lot « ${lot.name} » ?`)) return;
    router.delete(route('projects.lots.destroy', [props.project.id, lot.id]), { preserveScroll: true });
};

// Actions sur jalons
const openCreateMilestone = () => { 
    editingMilestone.value = { building_work_id: selectedBuildingWork.value?.id };
    showBuildingWorkDetailModal.value = false;
    showMilestoneModal.value = true; 
};
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

const statusBadge = {
    not_started: 'bg-gray-100 text-gray-700',
    in_progress: 'bg-amber-100 text-amber-700',
    on_hold: 'bg-orange-100 text-orange-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
};

const statusLabels = {
    not_started: 'Non démarré',
    in_progress: 'En cours',
    on_hold: 'En pause',
    completed: 'Terminé',
    cancelled: 'Annulé',
};

const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—';

// Filtrer les lots et jalons pour l'ouvrage sélectionné
const workLots = computed(() => {
    if (!selectedBuildingWork.value) return [];
    return props.lots.filter(l => l.building_work_id === selectedBuildingWork.value.id);
});

const workMilestones = computed(() => {
    if (!selectedBuildingWork.value) return [];
    return props.milestones.filter(m => m.building_work_id === selectedBuildingWork.value.id);
});
</script>

<template>
    <div class="space-y-6">
        <!-- Liste des ouvrages -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Ouvrages ({{ buildingWorks.length }})</h3>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-700"
                    @click="openCreateBuildingWork"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Ajouter un ouvrage
                </button>
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm" v-if="buildingWorks.length">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Nom</th>
                        <th class="px-4 py-2 text-right">Avancement</th>
                        <th class="px-4 py-2">Statut</th>
                        <th class="px-4 py-2">Période</th>
                        <th class="px-4 py-2 text-right">Budget</th>
                        <th v-if="canManage" class="px-4 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="work in buildingWorks" :key="work.id" class="cursor-pointer hover:bg-indigo-50" @click="openViewBuildingWork(work)">
                        <td class="px-4 py-2 font-semibold text-gray-900">{{ work.name }}</td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex items-center gap-2 justify-end">
                                <div class="w-16 rounded-full bg-gray-200 h-2">
                                    <div class="rounded-full h-2 bg-indigo-600" :style="{ width: `${work.progress_percentage}%` }" />
                                </div>
                                <span class="text-xs font-semibold text-gray-900">{{ work.progress_percentage.toFixed(0) }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusBadge[work.status]">
                                {{ statusLabels[work.status] }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-600">{{ formatDate(work.planned_start_date) }} → {{ formatDate(work.planned_end_date) }}</td>
                        <td class="px-4 py-2 text-right text-xs font-semibold text-gray-900">
                            {{ work.budget ? `${Number(work.budget).toLocaleString('fr-FR')} FCFA` : '—' }}
                        </td>
                        <td v-if="canManage" class="px-4 py-2 text-right" @click.stop>
                            <div class="flex justify-end gap-1">
                                <button class="rounded p-1 text-gray-500 hover:bg-indigo-50 hover:text-indigo-700" title="Modifier" @click="openEditBuildingWork(work)">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828A2 2 0 0110 16.5L6 17l.5-4a2 2 0 01.586-1.414z" />
                                    </svg>
                                </button>
                                <button class="rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-700" title="Supprimer" @click="removeBuildingWork(work)">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="py-12 text-center text-sm text-gray-500">
                Aucun ouvrage défini pour ce projet.
                <br>
                <button v-if="canManage" class="mt-2 text-indigo-600 font-medium hover:text-indigo-700" @click="openCreateBuildingWork">
                    Créer le premier ouvrage →
                </button>
            </div>
        </div>

        <!-- Modales -->
        <BuildingWorkModal
            :show="showBuildingWorkModal"
            :project-id="project.id"
            @close="showBuildingWorkModal = false"
        />

        <BuildingWorkDetailModal
            :show="showBuildingWorkDetailModal"
            :work="selectedBuildingWork"
            :lots="workLots"
            :milestones="workMilestones"
            @close="showBuildingWorkDetailModal = false"
            @edit="openEditBuildingWork(selectedBuildingWork); showBuildingWorkDetailModal = false"
            @add-lot="openCreateLot"
            @add-milestone="openCreateMilestone"
        />

        <LotFormModal
            :show="showLotModal"
            :project-id="project.id"
            :lot="editingLot"
            @close="showLotModal = false"
        />

        <MilestoneFormModal
            :show="showMilestoneModal"
            :project-id="project.id"
            :milestone="editingMilestone"
            :lots="workLots"
            @close="showMilestoneModal = false"
        />
    </div>
</template>
