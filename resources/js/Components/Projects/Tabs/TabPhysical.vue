<script setup>
import { computed, ref } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement,
    Title, Tooltip, Legend, Filler,
} from 'chart.js';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/Composables/useAuth';
import PhysicalProgressModal from '@/Components/Projects/Forms/PhysicalProgressModal.vue';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

const props = defineProps({
    project: { type: Object, required: true },
    progresses: { type: Array, required: true },
    lots: { type: Array, default: () => [] },
});

const { hasPermission } = useAuth();
const canWrite = computed(() => hasPermission('manage_physical'));

const showModal = ref(false);
const editing = ref(null);
const selectedLotId = ref(null);

const openCreate = () => { editing.value = null; showModal.value = true; };
const openEdit = (p) => { editing.value = p; showModal.value = true; };
const remove = (p) => {
    if (!confirm(`Supprimer le relevé de ${p.period} ?`)) return;
    router.delete(route('projects.physical.destroy', [props.project.id, p.id]), { preserveScroll: true });
};

// Seuls les ouvrages d'avancement (kind = physical) sont pilotés ici,
// pas les lots du planning.
const ouvrages = computed(() => props.lots.filter((l) => l.kind === 'physical'));
const physicalLotIds = computed(() => new Set(ouvrages.value.map((l) => Number(l.id))));

const currentLot = computed(() => {
    if (selectedLotId.value === null || selectedLotId.value === undefined) return null;
    return props.lots.find((l) => Number(l.id) === Number(selectedLotId.value)) ?? null;
});

const filteredProgresses = computed(() => {
    if (selectedLotId.value === null || selectedLotId.value === undefined) return [];
    return props.progresses.filter((p) => Number(p.project_lot_id) === Number(selectedLotId.value));
});

const aggregateByPeriod = computed(() => {
    const rows = props.progresses.filter((p) => p.project_lot_id != null && physicalLotIds.value.has(Number(p.project_lot_id)));
    const grouped = new Map();

    rows.forEach((p) => {
        const key = String(p.period ?? '');
        if (!key) return;
        if (!grouped.has(key)) {
            grouped.set(key, { period: key, planned: 0, actual: 0, count: 0 });
        }
        const bucket = grouped.get(key);
        bucket.planned += Number(p.planned_percentage ?? 0);
        bucket.actual += Number(p.actual_percentage ?? 0);
        bucket.count += 1;
    });

    return [...grouped.values()]
        .filter((x) => x.count > 0)
        .map((x) => {
            const planned = x.planned / x.count;
            const actual = x.actual / x.count;
            return {
                period: x.period,
                planned_percentage: planned,
                actual_percentage: actual,
                variance: actual - planned,
            };
        })
        .sort((a, b) => String(a.period).localeCompare(String(b.period)));
});

const aggregateHistorySorted = computed(() =>
    [...aggregateByPeriod.value].sort((a, b) => String(b.period).localeCompare(String(a.period)))
);

const aggregateChartData = computed(() => ({
    labels: aggregateByPeriod.value.map((p) => p.period),
    datasets: [
        {
            label: 'Prévu moyen (%)',
            data: aggregateByPeriod.value.map((p) => p.planned_percentage),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            fill: false,
            tension: 0.3,
            borderDash: [6, 4],
            pointRadius: 3,
        },
        {
            label: 'Réel moyen (%)',
            data: aggregateByPeriod.value.map((p) => p.actual_percentage),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.15)',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
        },
    ],
}));

const sorted = computed(() =>
    [...filteredProgresses.value].sort((a, b) => a.period.localeCompare(b.period))
);

const historySorted = computed(() =>
    [...sorted.value].sort((a, b) => {
        const dateDiff = new Date(b.measurement_date).getTime() - new Date(a.measurement_date).getTime();
        if (dateDiff !== 0) return dateDiff;
        return String(b.period).localeCompare(String(a.period));
    })
);

const latest = computed(() => historySorted.value[0] ?? null);

const chartData = computed(() => ({
    labels: sorted.value.map((p) => p.period),
    datasets: [
        {
            label: 'Prévu (%)',
            data: sorted.value.map((p) => p.planned_percentage),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            fill: false,
            tension: 0.3,
            borderDash: [6, 4],
            pointRadius: 3,
        },
        {
            label: 'Réel (%)',
            data: sorted.value.map((p) => p.actual_percentage),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.15)',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { position: 'top' },
        title: { display: true, text: 'Courbe en S — avancement physique cumulé' },
        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.raw?.toFixed(1) ?? 0}%` } },
    },
    scales: {
        y: { min: 0, max: 100, ticks: { callback: (v) => `${v}%` } },
    },
};

const varianceColor = (v) => v >= 0 ? 'text-emerald-600' : 'text-red-600';

const generateLotCode = (name) => {
    const base = String(name)
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .slice(0, 8);
    const fallbackBase = base || 'LOT';
    const existing = new Set(props.lots.map((l) => String(l.code)));

    for (let i = 0; i < 10; i++) {
        const suffix = Math.floor(Math.random() * 900 + 100);
        const candidate = `${fallbackBase}${suffix}`.slice(0, 32);
        if (!existing.has(candidate)) return candidate;
    }
    return `${fallbackBase}${Date.now()}`.slice(0, 32);
};

const addLot = () => {
    if (!canWrite.value) return;
    const name = prompt("Nom de l'ouvrage :");
    if (!name) return;

    const code = generateLotCode(name);

    router.post(
        route('projects.lots.store', props.project.id),
        {
            code,
            name,
            kind: 'physical',
            weight_percentage: 0,
            status: 'not_started',
            sort_order: ouvrages.value.length,
        },
        { preserveScroll: true },
    );
};
</script>

<template>
    <div class="space-y-4">
        <!-- Vue liste (choix de l'ouvrage) -->
        <div v-if="selectedLotId === null" class="space-y-4">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Ouvrages physiques ({{ ouvrages.length }})</h3>
                    <button
                        v-if="canWrite"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-700"
                        @click="addLot"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        Ajouter un ouvrage physique
                    </button>
                </div>

                <div class="px-5 py-3 text-xs text-gray-500">
                    Ajoute un ouvrage, puis clique sur son nom pour saisir son avancement physique.
                </div>
            </div>

            <div v-if="!ouvrages.length" class="rounded-xl border-2 border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500">
                Aucun ouvrage pour l'instant. Clique sur <span class="font-semibold">Ajouter un ouvrage physique</span>.
            </div>

            <div v-else class="space-y-2">
                <button
                    v-for="l in ouvrages"
                    :key="l.id"
                    type="button"
                    class="w-full rounded-xl bg-white px-5 py-3 text-left shadow-sm ring-1 ring-gray-200 transition hover:shadow-md"
                    @click="selectedLotId = l.id"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-xs font-semibold text-gray-900">{{ l.code }}</p>
                            <p class="truncate text-sm text-gray-700">{{ l.name }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700">
                                {{ (l.progress_percentage ?? 0).toFixed(0) }}%
                            </span>
                            <span class="text-xs text-gray-400">Ouvrir</span>
                        </div>
                    </div>
                </button>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <div v-if="!aggregateByPeriod.length" class="py-16 text-center text-sm text-gray-500">
                    Aucune donnée.
                </div>
                <div v-else class="h-80">
                    <Line :data="aggregateChartData" :options="chartOptions" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Données moyennes des ouvrages</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-2">Période</th>
                                <th class="px-4 py-2 text-right">Prévu</th>
                                <th class="px-4 py-2 text-right">Réel</th>
                                <th class="px-4 py-2 text-right">Écart</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="p in aggregateHistorySorted" :key="`agg-${p.period}`" class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono text-xs">{{ p.period }}</td>
                                <td class="px-4 py-2 text-right text-indigo-700">{{ p.planned_percentage.toFixed(1) }}%</td>
                                <td class="px-4 py-2 text-right font-medium text-emerald-700">{{ p.actual_percentage.toFixed(1) }}%</td>
                                <td class="px-4 py-2 text-right font-semibold" :class="varianceColor(p.variance)">
                                    {{ p.variance >= 0 ? '+' : '' }}{{ p.variance.toFixed(1) }}
                                </td>
                            </tr>
                            <tr v-if="!aggregateHistorySorted.length">
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">Aucune donnée.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Vue détail (chart + historique, comme sur la 3e image) -->
        <div v-else class="space-y-4">
            <div class="flex items-start justify-between gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <div class="min-w-0">
                    <p class="font-mono text-xs text-gray-500">{{ currentLot?.code ?? '' }}</p>
                    <p class="truncate text-sm font-semibold text-gray-900">{{ currentLot?.name ?? '' }}</p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="selectedLotId = null"
                >
                    ← Retour
                </button>
            </div>

            <!-- Chart -->
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <div v-if="sorted.length === 0" class="py-20 text-center text-sm text-gray-500">
                    Aucune mesure d'avancement physique pour l'instant.
                </div>
                <div v-else class="h-80">
                    <Line :data="chartData" :options="chartOptions" />
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Historique des mesures</h3>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-500">{{ sorted.length }} relevé(s)</span>
                        <button
                            v-if="canWrite"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-700"
                            @click="openCreate"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                            Saisir
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-2">Période</th>
                                <th class="px-4 py-2">Ouvrage</th>
                                <th class="px-4 py-2">Date mesure</th>
                                <th class="px-4 py-2 text-right">Prévu</th>
                                <th class="px-4 py-2 text-right">Réel</th>
                                <th class="px-4 py-2 text-right">Écart</th>
                                <th class="px-4 py-2">Observations</th>
                                <th v-if="canWrite" class="px-4 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="p in historySorted" :key="p.id" class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono text-xs">{{ p.period }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span class="font-mono">{{ currentLot?.code ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ new Date(p.measurement_date).toLocaleDateString('fr-FR') }}</td>
                                <td class="px-4 py-2 text-right text-indigo-700">{{ p.planned_percentage.toFixed(1) }}%</td>
                                <td class="px-4 py-2 text-right font-medium text-emerald-700">{{ p.actual_percentage.toFixed(1) }}%</td>
                                <td class="px-4 py-2 text-right font-semibold" :class="varianceColor(p.variance)">
                                    {{ p.variance >= 0 ? '+' : '' }}{{ p.variance.toFixed(1) }}
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ p.observations || '—' }}</td>
                                <td v-if="canWrite" class="px-4 py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        <button class="rounded p-1 text-gray-500 hover:bg-indigo-50 hover:text-indigo-700" title="Modifier" @click="openEdit(p)">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828A2 2 0 0110 16.5L6 17l.5-4a2 2 0 01.586-1.414z" /></svg>
                                        </button>
                                        <button class="rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-700" title="Supprimer" @click="remove(p)">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!sorted.length">
                                <td :colspan="canWrite ? 8 : 7" class="px-4 py-6 text-center text-sm text-gray-500">Aucune donnée.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <PhysicalProgressModal
                :show="showModal"
                :project-id="project.id"
                :progress="editing"
                :lots="ouvrages"
                :default-lot-id="selectedLotId"
                @close="showModal = false"
            />
        </div>
    </div>
</template>
