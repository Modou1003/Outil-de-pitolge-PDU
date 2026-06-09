<script setup>
import Modal from '@/Components/UI/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: [Number, String], required: true },
    progress: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const currentPeriod = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

const form = useForm({
    period: currentPeriod(),
    measurement_date: new Date().toISOString().slice(0, 10),
    planned_value: 0,
    earned_value: 0,
    actual_cost: 0,
    observations: '',
});

watch(() => props.show, (v) => {
    if (v) {
        if (props.progress) {
            form.period = props.progress.period;
            form.measurement_date = props.progress.measurement_date;
            form.planned_value = props.progress.planned_value;
            form.earned_value = props.progress.earned_value;
            form.actual_cost = props.progress.actual_cost;
            form.observations = props.progress.observations ?? '';
        } else {
            form.reset();
            form.period = currentPeriod();
            form.measurement_date = new Date().toISOString().slice(0, 10);
        }
        form.clearErrors();
    }
});

const cpi = computed(() => {
    const ac = Number(form.actual_cost);
    return ac > 0 ? (Number(form.earned_value) / ac) : 0;
});
const spi = computed(() => {
    const pv = Number(form.planned_value);
    return pv > 0 ? (Number(form.earned_value) / pv) : 0;
});
const cv = computed(() => Number(form.earned_value) - Number(form.actual_cost));
const sv = computed(() => Number(form.earned_value) - Number(form.planned_value));

const badge = (val) => val >= 1 ? 'bg-emerald-100 text-emerald-700' : val >= 0.9 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700';
const moneyColor = (val) => val >= 0 ? 'text-emerald-600' : 'text-red-600';

const fmt = (n) => new Intl.NumberFormat('fr-FR').format(Math.round(n));

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => emit('close') };
    if (props.progress) {
        form.put(route('projects.financial.update', [props.projectId, props.progress.id]), opts);
    } else {
        form.post(route('projects.financial.store', props.projectId), opts);
    }
};
</script>

<template>
    <Modal :show="show" :title="progress ? 'Modifier le relevé financier' : 'Saisir un avancement financier'" size="xl" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Planned Value (PV) — FCFA</label>
                    <input v-model.number="form.planned_value" type="number" min="0" step="1" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.planned_value" class="mt-1 text-xs text-red-600">{{ form.errors.planned_value }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Earned Value (EV) — FCFA</label>
                    <input v-model.number="form.earned_value" type="number" min="0" step="1" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.earned_value" class="mt-1 text-xs text-red-600">{{ form.errors.earned_value }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Actual Cost (AC) — FCFA</label>
                    <input v-model.number="form.actual_cost" type="number" min="0" step="1" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.actual_cost" class="mt-1 text-xs text-red-600">{{ form.errors.actual_cost }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs sm:grid-cols-4">
                <div>
                    <p class="text-gray-500">CPI</p>
                    <span class="inline-block rounded-md px-2 py-0.5 text-sm font-semibold" :class="badge(cpi)">{{ cpi.toFixed(2) }}</span>
                </div>
                <div>
                    <p class="text-gray-500">SPI</p>
                    <span class="inline-block rounded-md px-2 py-0.5 text-sm font-semibold" :class="badge(spi)">{{ spi.toFixed(2) }}</span>
                </div>
                <div>
                    <p class="text-gray-500">CV</p>
                    <p class="text-sm font-semibold" :class="moneyColor(cv)">{{ cv >= 0 ? '+' : '' }}{{ fmt(cv) }}</p>
                </div>
                <div>
                    <p class="text-gray-500">SV</p>
                    <p class="text-sm font-semibold" :class="moneyColor(sv)">{{ sv >= 0 ? '+' : '' }}{{ fmt(sv) }}</p>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Observations (optionnel)</label>
                <textarea v-model="form.observations" rows="2" class="w-full rounded-md border-gray-300 text-sm" placeholder="Contexte, justifications, alertes…" />
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
