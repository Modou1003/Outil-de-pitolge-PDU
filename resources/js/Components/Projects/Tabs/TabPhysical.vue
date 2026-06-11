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

const openCreate = () => { editing.value = null; showModal.value = true; };
const openEdit = (p) => { editing.value = p; showModal.value = true; };
const remove = (p) => {
    if (!confirm(`Supprimer le relevé de ${p.period} ?`)) return;
    router.delete(route('projects.physical.destroy', [props.project.id, p.id]), { preserveScroll: true });
};

const sorted = computed(() =>
    [...props.progresses].sort((a, b) => a.period.localeCompare(b.period))
);
const historySorted = computed(() =>
    [...sorted.value].sort((a, b) => {
        const dateDiff = new Date(b.measurement_date).getTime() - new Date(a.measurement_date).getTime();
        if (dateDiff !== 0) return dateDiff;

        return String(b.period).localeCompare(String(a.period));
    })
);

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

const latest = computed(() => historySorted.value[0] ?? null);
const varianceColor = (v) => v >= 0 ? 'text-emerald-600' : 'text-red-600';
</script>

<template>
    <div class="space-y-4">
        <!-- KPIs -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase text-gray-500">Avancement réel</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700">{{ latest?.actual_percentage?.toFixed(1) ?? '0' }}%</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase text-gray-500">Avancement prévu</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700">{{ latest?.planned_percentage?.toFixed(1) ?? '0' }}%</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase text-gray-500">Écart</p>
                <p class="mt-1 text-2xl font-bold" :class="varianceColor(latest?.variance ?? 0)">
                    {{ (latest?.variance ?? 0) >= 0 ? '+' : '' }}{{ latest?.variance?.toFixed(1) ?? '0' }} pts
                </p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase text-gray-500">Mesures</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ sorted.length }}</p>
            </div>
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
                            <td :colspan="canWrite ? 7 : 6" class="px-4 py-6 text-center text-sm text-gray-500">Aucune donnée.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <PhysicalProgressModal
            :show="showModal"
            :project-id="project.id"
            :progress="editing"
            :lots="lots"
            @close="showModal = false"
        />
    </div>
</template>
