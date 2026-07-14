<script setup>
import Modal from '@/Components/UI/Modal.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    work: { type: Object, default: null },
    lots: { type: Array, default: () => [] },
    milestones: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'edit', 'add-lot', 'add-milestone']);

const showAddLot = ref(false);
const showAddMilestone = ref(false);

const statusColors = {
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

const workLots = computed(() => {
    if (!props.work) return [];
    return props.lots.filter(l => l.building_work_id === props.work.id);
});

const workMilestones = computed(() => {
    if (!props.work) return [];
    return props.milestones.filter(m => {
        // Jalons assignés à l'ouvrage
        if (m.building_work_id === props.work.id) return true;
        return false;
    });
});
</script>

<template>
    <Modal :show="show" :title="`Ouvrage : ${work?.code ?? '—'} — ${work?.name ?? '—'}`" size="xl" @close="emit('close')">
        <div v-if="work" class="space-y-6">
            <!-- Infos de l'ouvrage -->
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-medium text-gray-500">Statut</p>
                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusColors[work.status]">
                        {{ statusLabels[work.status] }}
                    </span>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Avancement</p>
                    <div class="mt-1 flex items-center gap-2">
                        <div class="flex-1 rounded-full bg-gray-200 h-2">
                            <div class="rounded-full h-2 bg-indigo-600" :style="{ width: `${work.progress_percentage}%` }" />
                        </div>
                        <p class="text-sm font-semibold text-gray-900">{{ work.progress_percentage.toFixed(0) }}%</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Période planifiée</p>
                    <p class="mt-1 text-sm text-gray-900">{{ formatDate(work.planned_start_date) }} → {{ formatDate(work.planned_end_date) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Budget</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ work.budget ? `${Number(work.budget).toLocaleString('fr-FR')} FCFA` : '—' }}</p>
                </div>
            </div>

            <div v-if="work.description" class="border-t border-gray-100 pt-4">
                <p class="text-xs font-medium text-gray-500">Description</p>
                <p class="mt-1 text-sm text-gray-700">{{ work.description }}</p>
            </div>

            <!-- Lots (Work Packages) -->
            <div class="border-t border-gray-100 pt-4">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-xs font-semibold uppercase text-gray-700">Lots ({{ workLots.length }})</h4>
                    <button
                        type="button"
                        class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                        @click="emit('add-lot')"
                    >
                        Ajouter
                    </button>
                </div>
                <div v-if="workLots.length" class="space-y-2">
                    <div v-for="lot in workLots" :key="lot.id" class="rounded-lg border border-gray-200 p-3 hover:bg-gray-50">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-semibold text-gray-900">{{ lot.code }} — {{ lot.name }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    Pondération: {{ lot.weight_percentage }}% | Avancement: {{ lot.progress_percentage.toFixed(0) }}%
                                </p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusColors[lot.status]">
                                {{ statusLabels[lot.status] }}
                            </span>
                        </div>
                    </div>
                </div>
                <p v-else class="rounded-lg bg-gray-50 px-3 py-4 text-center text-sm text-gray-500">
                    Aucun lot défini. Cliquez sur "Ajouter" pour en créer un.
                </p>
            </div>

            <!-- Jalons -->
            <div class="border-t border-gray-100 pt-4">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-xs font-semibold uppercase text-gray-700">Jalons ({{ workMilestones.length }})</h4>
                    <button
                        type="button"
                        class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                        @click="emit('add-milestone')"
                    >
                        Ajouter
                    </button>
                </div>
                <div v-if="workMilestones.length" class="space-y-2">
                    <div v-for="m in workMilestones" :key="m.id" class="rounded-lg border-l-4 border-indigo-500 bg-indigo-50 p-3">
                        <p class="font-semibold text-indigo-900">{{ m.name }}</p>
                        <p class="mt-0.5 text-xs text-indigo-700">
                            Prévu: {{ formatDate(m.planned_date) }}
                            <span v-if="m.actual_date"> | Atteint: {{ formatDate(m.actual_date) }}</span>
                            <span v-if="m.is_critical" class="ml-1 font-semibold">★ critique</span>
                        </p>
                    </div>
                </div>
                <p v-else class="rounded-lg bg-gray-50 px-3 py-4 text-center text-sm text-gray-500">
                    Aucun jalon défini. Cliquez sur "Ajouter" pour en créer un.
                </p>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    @click="emit('close')"
                >
                    Fermer
                </button>
                <button
                    type="button"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    @click="emit('edit')"
                >
                    Modifier l'ouvrage
                </button>
            </div>
        </template>
    </Modal>
</template>
