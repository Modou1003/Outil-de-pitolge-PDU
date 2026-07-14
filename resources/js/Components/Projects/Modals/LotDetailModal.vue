<script setup>
import Modal from '@/Components/UI/Modal.vue';
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    lot: { type: Object, default: null },
    milestones: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'edit', 'add-milestone']);

const lotMilestones = computed(() => {
    if (!props.lot) return [];
    return props.milestones.filter(m => m.project_lot_id === props.lot.id || (m.project_lot_id === null && props.lot.planned_start_date && props.lot.planned_end_date && 
        new Date(m.planned_date) >= new Date(props.lot.planned_start_date) && 
        new Date(m.planned_date) <= new Date(props.lot.planned_end_date)));
});

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

const milestoneStatus = {
    pending: 'En attente',
    reached: 'Atteint',
    missed: 'Manqué',
    cancelled: 'Annulé',
};

const milestoneColors = {
    pending: 'border-indigo-400 bg-white text-indigo-700',
    reached: 'border-emerald-500 bg-emerald-50 text-emerald-800',
    missed: 'border-red-500 bg-red-50 text-red-800',
    cancelled: 'border-gray-300 bg-gray-50 text-gray-600',
};

const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—';

const isOverdue = (m) => {
    if (!m.planned_date || m.status !== 'pending') return false;
    return new Date(m.planned_date) < new Date();
};
</script>

<template>
    <Modal :show="show" :title="`Ouvrage ${lot?.code ?? '—'}`" size="xl" @close="emit('close')">
        <div v-if="lot" class="space-y-6">
            <!-- Infos du lot -->
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-medium text-gray-500">Code</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ lot.code }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Nom</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ lot.name }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Statut</p>
                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusColors[lot.status]">
                        {{ statusLabels[lot.status] }}
                    </span>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Pondération</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ lot.weight_percentage }}%</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Avancement</p>
                    <div class="mt-1 flex items-center gap-2">
                        <div class="flex-1 rounded-full bg-gray-200 h-2">
                            <div class="rounded-full h-2 bg-indigo-600" :style="{ width: `${lot.progress_percentage}%` }" />
                        </div>
                        <p class="text-sm font-semibold text-gray-900">{{ lot.progress_percentage.toFixed(0) }}%</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Période planifiée</p>
                    <p class="mt-1 text-sm text-gray-900">{{ formatDate(lot.planned_start_date) }} → {{ formatDate(lot.planned_end_date) }}</p>
                </div>
            </div>

            <div v-if="lot.description" class="border-t border-gray-100 pt-4">
                <p class="text-xs font-medium text-gray-500">Description</p>
                <p class="mt-1 text-sm text-gray-700">{{ lot.description }}</p>
            </div>

            <!-- Jalons associés -->
            <div class="border-t border-gray-100 pt-4">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-xs font-semibold uppercase text-gray-700">Jalons ({{ lotMilestones.length }})</h4>
                    <button
                        type="button"
                        class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                        @click="emit('add-milestone')"
                    >
                        Ajouter
                    </button>
                </div>
                <div v-if="lotMilestones.length" class="space-y-2">
                    <div
                        v-for="m in lotMilestones"
                        :key="m.id"
                        class="rounded-lg border-2 p-3"
                        :class="milestoneColors[m.status]"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold">{{ m.name }}</p>
                                <p class="mt-0.5 text-xs opacity-75">
                                    Prévu : {{ formatDate(m.planned_date) }}
                                    <span v-if="m.actual_date"> · Atteint : {{ formatDate(m.actual_date) }}</span>
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                :class="{
                                    'bg-indigo-200 text-indigo-700': m.status === 'pending',
                                    'bg-emerald-200 text-emerald-700': m.status === 'reached',
                                    'bg-red-200 text-red-700': m.status === 'missed',
                                    'bg-gray-200 text-gray-700': m.status === 'cancelled',
                                }"
                            >
                                {{ milestoneStatus[m.status] }}
                                <span v-if="isOverdue(m)" class="ml-1">🔴</span>
                                <span v-if="m.is_critical" class="ml-1">★</span>
                            </span>
                        </div>
                    </div>
                </div>
                <p v-else class="rounded-lg bg-gray-50 px-3 py-4 text-center text-sm text-gray-500">
                    Aucun jalon associé à cet ouvrage.
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
