<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    project: { type: Object, required: true },
    trackings: { type: Array, required: true },
    indicatorCatalog: { type: Array, default: () => [] },
    canManageIndicators: { type: Boolean, default: false },
});

const statusStyle = {
    on_track: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    at_risk: 'bg-amber-100 text-amber-700 ring-amber-200',
    off_target: 'bg-red-100 text-red-700 ring-red-200',
    not_started: 'bg-gray-100 text-gray-600 ring-gray-200',
    completed: 'bg-indigo-100 text-indigo-700 ring-indigo-200',
};

const statusLabel = {
    on_track: 'Sur la trajectoire',
    at_risk: 'À risque',
    off_target: 'Hors cible',
    not_started: 'Non démarré',
    completed: 'Atteint',
};

const progressColor = (rate) => {
    if (rate === null || rate === undefined) return 'bg-gray-300';
    if (rate >= 100) return 'bg-emerald-500';
    if (rate >= 70) return 'bg-indigo-500';
    if (rate >= 40) return 'bg-amber-500';
    return 'bg-red-500';
};

const summary = computed(() => ({
    total: props.trackings.length,
    on_track: props.trackings.filter((t) => t.status === 'on_track' || t.status === 'completed').length,
    at_risk: props.trackings.filter((t) => t.status === 'at_risk').length,
    off_target: props.trackings.filter((t) => t.status === 'off_target').length,
}));

const formatValue = (v, unit) => {
    if (v === null || v === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(v) + (unit ? ` ${unit}` : '');
};

const selectedIndicatorId = ref('');
const processing = ref(false);

const trackedIndicatorIds = computed(() => new Set(props.trackings.map((t) => t.indicator_id)));
const availableIndicators = computed(() =>
    props.indicatorCatalog.filter((indicator) => !trackedIndicatorIds.value.has(indicator.id))
);

const addIndicator = () => {
    if (!props.canManageIndicators || processing.value || !selectedIndicatorId.value) return;

    processing.value = true;
    router.post(route('projects.indicators.store', props.project.id), {
        indicator_id: selectedIndicatorId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIndicatorId.value = '';
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

const removeIndicator = (indicatorId) => {
    if (!props.canManageIndicators || processing.value) return;
    if (!confirm('Supprimer cet indicateur du projet ?')) return;

    processing.value = true;
    router.delete(route('projects.indicators.destroy', [props.project.id, indicatorId]), {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>

<template>
    <div class="space-y-4">
        <div v-if="canManageIndicators" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Ajouter un indicateur</h3>
            <div class="flex flex-col gap-2 sm:flex-row">
                <select v-model="selectedIndicatorId" class="w-full rounded-md border-gray-300 text-sm sm:flex-1">
                    <option value="">Sélectionner un indicateur</option>
                    <option v-for="indicator in availableIndicators" :key="indicator.id" :value="indicator.id">
                        {{ indicator.code }} — {{ indicator.name }}
                    </option>
                </select>
                <button
                    type="button"
                    :disabled="processing || !selectedIndicatorId"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                    @click="addIndicator"
                >
                    Ajouter
                </button>
            </div>
        </div>

        <!-- Résumé -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase text-gray-500">Total suivis</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ summary.total }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-emerald-200">
                <p class="text-xs font-medium uppercase text-emerald-600">Sur trajectoire</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700">{{ summary.on_track }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-amber-200">
                <p class="text-xs font-medium uppercase text-amber-600">À risque</p>
                <p class="mt-1 text-2xl font-bold text-amber-700">{{ summary.at_risk }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-red-200">
                <p class="text-xs font-medium uppercase text-red-600">Hors cible</p>
                <p class="mt-1 text-2xl font-bold text-red-700">{{ summary.off_target }}</p>
            </div>
        </div>

        <!-- Liste -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div
                v-for="t in trackings"
                :key="t.id"
                class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="font-mono text-[10px] text-gray-500">{{ t.code }}</p>
                        <p class="truncate text-sm font-semibold text-gray-900">{{ t.name }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium ring-1"
                            :class="statusStyle[t.status] ?? statusStyle.not_started"
                        >
                            {{ statusLabel[t.status] ?? t.status }}
                        </span>
                        <button
                            v-if="canManageIndicators"
                            type="button"
                            class="rounded-md border border-red-200 px-2 py-0.5 text-[10px] font-medium text-red-600 hover:bg-red-50"
                            @click="removeIndicator(t.indicator_id)"
                        >
                            Supprimer
                        </button>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-3 gap-2 border-y border-gray-100 py-2 text-center text-xs">
                    <div>
                        <p class="text-[10px] uppercase text-gray-500">Cible</p>
                        <p class="mt-0.5 font-bold text-indigo-700">{{ formatValue(t.target_value, t.unit) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase text-gray-500">Actuel</p>
                        <p class="mt-0.5 font-bold text-emerald-700">{{ formatValue(t.actual_value, t.unit) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase text-gray-500">Atteinte</p>
                        <p class="mt-0.5 font-bold text-gray-900">{{ t.achievement_rate !== null ? `${t.achievement_rate}%` : '—' }}</p>
                    </div>
                </div>

                <div class="mt-2">
                    <div class="h-2 overflow-hidden rounded-full bg-gray-200">
                        <div
                            class="h-full transition-all"
                            :class="progressColor(t.achievement_rate)"
                            :style="{ width: `${Math.min(100, t.achievement_rate ?? 0)}%` }"
                        />
                    </div>
                </div>

                <p v-if="t.measurement_date" class="mt-2 text-[10px] text-gray-500">
                    Dernière mesure : {{ new Date(t.measurement_date).toLocaleDateString('fr-FR') }}<span v-if="t.period"> · {{ t.period }}</span>
                </p>
            </div>

            <div v-if="!trackings.length" class="rounded-xl border-2 border-dashed border-gray-200 bg-white p-10 text-center text-gray-500 md:col-span-2">
                Aucun indicateur suivi pour ce projet.
            </div>
        </div>
    </div>
</template>
