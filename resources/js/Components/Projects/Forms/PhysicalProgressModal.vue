<script setup>
import Modal from '@/Components/UI/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: [Number, String], required: true },
    progress: { type: Object, default: null },
    lots: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const currentPeriod = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

const form = useForm({
    project_lot_id: null,
    period: currentPeriod(),
    measurement_date: new Date().toISOString().slice(0, 10),
    planned_percentage: 0,
    actual_percentage: 0,
    observations: '',
});

watch(() => props.show, (v) => {
    if (v) {
        if (props.progress) {
            form.project_lot_id = props.progress.project_lot_id;
            form.period = props.progress.period;
            form.measurement_date = props.progress.measurement_date;
            form.planned_percentage = props.progress.planned_percentage;
            form.actual_percentage = props.progress.actual_percentage;
            form.observations = props.progress.observations ?? '';
        } else {
            form.reset();
            form.period = currentPeriod();
            form.measurement_date = new Date().toISOString().slice(0, 10);
        }
        form.clearErrors();
    }
});

const variance = computed(() => Number(form.actual_percentage) - Number(form.planned_percentage));
const varianceColor = computed(() => variance.value >= 0 ? 'text-emerald-600' : 'text-red-600');

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => emit('close') };
    if (props.progress) {
        form.put(route('projects.physical.update', [props.projectId, props.progress.id]), opts);
    } else {
        form.post(route('projects.physical.store', props.projectId), opts);
    }
};
</script>

<template>
    <Modal :show="show" :title="progress ? 'Modifier le relevé physique' : 'Saisir un avancement physique'" size="lg" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Lot concerné (optionnel)</label>
                    <select v-model="form.project_lot_id" class="w-full rounded-md border-gray-300 text-sm">
                        <option :value="null">Projet global</option>
                        <option v-for="l in lots" :key="l.id" :value="l.id">{{ l.code }} — {{ l.name }}</option>
                    </select>
                    <p v-if="form.errors.project_lot_id" class="mt-1 text-xs text-red-600">{{ form.errors.project_lot_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Période (YYYY-MM)</label>
                    <input v-model="form.period" type="month" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.period" class="mt-1 text-xs text-red-600">{{ form.errors.period }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Date de mesure</label>
                    <input v-model="form.measurement_date" type="date" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.measurement_date" class="mt-1 text-xs text-red-600">{{ form.errors.measurement_date }}</p>
                </div>
                <div />
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Avancement prévu (%)</label>
                    <input v-model.number="form.planned_percentage" type="number" max="100" step="any" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.planned_percentage" class="mt-1 text-xs text-red-600">{{ form.errors.planned_percentage }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Avancement réel (%)</label>
                    <input v-model.number="form.actual_percentage" type="number" max="100" step="any" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.actual_percentage" class="mt-1 text-xs text-red-600">{{ form.errors.actual_percentage }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm">
                <p>Écart calculé : <span class="font-bold" :class="varianceColor">{{ variance >= 0 ? '+' : '' }}{{ variance.toFixed(1) }} pts</span></p>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Observations (optionnel)</label>
                <textarea v-model="form.observations" rows="2" class="w-full rounded-md border-gray-300 text-sm" placeholder="Difficultés rencontrées, retards, modifications…" />
                <p v-if="form.errors.observations" class="mt-1 text-xs text-red-600">{{ form.errors.observations }}</p>
            </div>
        </form>

        <template #footer>
            <button type="button" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100" @click="emit('close')">Annuler</button>
            <button
                type="button"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                :disabled="form.processing"
                @click="submit"
            >
                {{ progress ? 'Mettre à jour' : 'Enregistrer' }}
            </button>
        </template>
    </Modal>
</template>
