<script setup>
import { computed, ref } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement,
    Title, Tooltip, Legend, Filler,
} from 'chart.js';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/Composables/useAuth';
import FinancialProgressModal from '@/Components/Projects/Forms/FinancialProgressModal.vue';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

const props = defineProps({
    project: { type: Object, required: true },
    progresses: { type: Array, required: true },
    kpis: { type: Object, required: true },
});

const { hasRole } = useAuth();
const canWrite = computed(() => hasRole(['admin', 'directeur', 'agent_financier']));

const showModal = ref(false);
const editing = ref(null);

const openCreate = () => { editing.value = null; showModal.value = true; };
const openEdit = (p) => { editing.value = p; showModal.value = true; };
const remove = (p) => {
    if (!confirm(`Supprimer le relevé financier de ${p.period} ?`)) return;
    router.delete(route('projects.financial.destroy', [props.project.id, p.id]), { preserveScroll: true });
};

const sorted = computed(() => [...props.progresses].sort((a, b) => a.period.localeCompare(b.period)));

const evmChart = computed(() => ({
    labels: sorted.value.map((p) => p.period),
    datasets: [
        {
            label: 'Valeur planifiée (PV)',
            data: sorted.value.map((p) => p.cumulative_planned_value),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.05)',
            borderDash: [6, 4],
            tension: 0.25,
            pointRadius: 2,
        },
        {
            label: 'Valeur acquise (EV)',
            data: sorted.value.map((p) => p.cumulative_earned_value),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.15)',
            fill: true,
            tension: 0.25,
            pointRadius: 3,
        },
        {
            label: 'Coût réel (AC)',
            data: sorted.value.map((p) => p.cumulative_actual_cost),
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            tension: 0.25,
            pointRadius: 3,
        },
    ],
}));

const evmOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { position: 'top' },
        title: { display: true, text: 'Earned Value Management — PV / EV / AC cumulés' },
        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatMoney(ctx.raw)}` } },
    },
    scales: {
        y: { ticks: { callback: (v) => formatMoney(v) } },
    },
};

const formatMoney = (v) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v ?? 0);
const latest = computed(() => sorted.value[sorted.value.length - 1] ?? null);

const healthBadge = (value, threshold = 1) => {
    if (value === null || value === undefined) return 'bg-gray-100 text-gray-600';
    if (value >= threshold) return 'bg-emerald-100 text-emerald-700';
    if (value >= threshold * 0.9) return 'bg-amber-100 text-amber-700';
    return 'bg-red-100 text-red-700';
};
</script>

<template>
    <div class="space-y-4">
        <!-- KPI EVM -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase text-gray-500">CPI (coût)</p>
                <p class="mt-1 text-2xl font-bold" :class="healthBadge(kpis.cpi)">{{ kpis.cpi ? kpis.cpi.toFixed(2) : '—' }}</p>
                <p class="mt-0.5 text-[11px] text-gray-500">EV / AC</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase text-gray-500">SPI (délai)</p>
                <p class="mt-1 text-2xl font-bold" :class="healthBadge(kpis.spi)">{{ kpis.spi ? kpis.spi.toFixed(2) : '—' }}</p>
                <p class="mt-0.5 text-[11px] text-gray-500">EV / PV</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase text-gray-500">CV</p>
                <p class="mt-1 text-lg font-bold" :class="(kpis.cv ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-700'">
                    {{ formatMoney(kpis.cv ?? 0) }}
                </p>
                <p class="mt-0.5 text-[11px] text-gray-500">Cost variance</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase text-gray-500">SV</p>
                <p class="mt-1 text-lg font-bold" :class="(kpis.sv ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-700'">
                    {{ formatMoney(kpis.sv ?? 0) }}
                </p>
                <p class="mt-0.5 text-[11px] text-gray-500">Schedule variance</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase text-gray-500">EAC</p>
                <p class="mt-1 text-lg font-bold text-gray-900">{{ kpis.eac ? formatMoney(kpis.eac) : '—' }}</p>
                <p class="mt-0.5 text-[11px] text-gray-500">BAC / CPI</p>
            </div>
        </div>

        <!-- Chart EVM -->
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <div v-if="sorted.length === 0" class="py-20 text-center text-sm text-gray-500">
                Aucune donnée financière.
            </div>
            <div v-else class="h-80">
                <Line :data="evmChart" :options="evmOptions" />
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Détail par période ({{ project.currency }})</h3>
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
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-2">Période</th>
                            <th class="px-4 py-2 text-right">PV</th>
                            <th class="px-4 py-2 text-right">EV</th>
                            <th class="px-4 py-2 text-right">AC</th>
                            <th class="px-4 py-2 text-right">CPI</th>
                            <th class="px-4 py-2 text-right">SPI</th>
                            <th v-if="canWrite" class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="p in sorted" :key="p.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ p.period }}</td>
                            <td class="px-4 py-2 text-right text-indigo-700">{{ formatMoney(p.cumulative_planned_value) }}</td>
                            <td class="px-4 py-2 text-right font-medium text-emerald-700">{{ formatMoney(p.cumulative_earned_value) }}</td>
                            <td class="px-4 py-2 text-right text-amber-700">{{ formatMoney(p.cumulative_actual_cost) }}</td>
                            <td class="px-4 py-2 text-right font-semibold" :class="(p.cpi ?? 0) >= 1 ? 'text-emerald-600' : 'text-red-600'">{{ p.cpi ? p.cpi.toFixed(2) : '—' }}</td>
                            <td class="px-4 py-2 text-right font-semibold" :class="(p.spi ?? 0) >= 1 ? 'text-emerald-600' : 'text-red-600'">{{ p.spi ? p.spi.toFixed(2) : '—' }}</td>
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

        <FinancialProgressModal
            :show="showModal"
            :project-id="project.id"
            :progress="editing"
            @close="showModal = false"
        />
    </div>
</template>
