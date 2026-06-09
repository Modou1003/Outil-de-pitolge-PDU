<script setup>
import { computed } from 'vue';

const props = defineProps({
    kpis: {
        type: Object,
        required: true,
    },
});

const statusColors = {
    draft: 'bg-gray-400',
    submitted: 'bg-blue-400',
    approved: 'bg-indigo-500',
    in_progress: 'bg-amber-500',
    on_hold: 'bg-orange-400',
    completed: 'bg-emerald-500',
    cancelled: 'bg-red-400',
    archived: 'bg-slate-500',
};

const statusLabels = {
    draft: 'Brouillon',
    submitted: 'Soumis',
    approved: 'Approuvé',
    in_progress: 'En cours',
    on_hold: 'En pause',
    completed: 'Terminé',
    cancelled: 'Annulé',
    archived: 'Archivé',
};

const formatMoney = (amount) => {
    if (amount >= 1e9) return (amount / 1e9).toFixed(2) + ' Mds';
    if (amount >= 1e6) return (amount / 1e6).toFixed(1) + ' M';
    return new Intl.NumberFormat('fr-FR').format(amount);
};

const statusEntries = computed(() =>
    Object.entries(props.kpis.status_breakdown).filter(([, count]) => count > 0)
);
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <!-- Carte 1 : Total Projets + statuts -->
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-500">Total Projets</h3>
                <span class="text-2xl">📋</span>
            </div>
            <p class="mt-2 text-3xl font-bold text-gray-900">{{ kpis.total_projects }}</p>
            <div class="mt-3 flex flex-wrap gap-1.5">
                <span
                    v-for="[status, count] in statusEntries"
                    :key="status"
                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium text-white"
                    :class="statusColors[status]"
                    :title="statusLabels[status]"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-white/70"></span>
                    {{ statusLabels[status] }} : {{ count }}
                </span>
            </div>
        </div>

        <!-- Carte 2 : Avancement moyen -->
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-500">Avancement moyen</h3>
                <span class="text-2xl">📈</span>
            </div>
            <p class="mt-2 text-3xl font-bold text-gray-900">
                {{ kpis.average_progress }}<span class="text-lg text-gray-500">%</span>
            </p>
            <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-gray-200">
                <div
                    class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500 transition-all"
                    :style="{ width: kpis.average_progress + '%' }"
                ></div>
            </div>
            <p class="mt-1 text-xs text-gray-500">Sur l'ensemble des projets PDU</p>
        </div>

        <!-- Carte 3 : Budget engagé vs décaissé -->
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-500">Budget (FCFA)</h3>
                <span class="text-2xl">💰</span>
            </div>
            <div class="mt-2 space-y-1.5">
                <div>
                    <p class="text-xs text-gray-500">Engagé</p>
                    <p class="text-xl font-bold text-gray-900">{{ formatMoney(kpis.budget_allocated_total) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Décaissé</p>
                    <p class="text-xl font-bold text-emerald-600">{{ formatMoney(kpis.budget_spent_total) }}</p>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500">
                Taux d'exécution : <span class="font-semibold">{{ kpis.budget_execution_rate }}%</span>
            </p>
        </div>

        <!-- Carte 4 : Alertes actives -->
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-500">Alertes actives</h3>
                <span class="text-2xl">🔔</span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <p
                    class="text-3xl font-bold"
                    :class="kpis.active_alerts > 0 ? 'text-red-600' : 'text-gray-900'"
                >
                    {{ kpis.active_alerts }}
                </p>
                <span
                    v-if="kpis.active_alerts > 0"
                    class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700"
                >
                    À traiter
                </span>
            </div>
            <p class="mt-2 text-xs text-gray-500">
                {{ kpis.active_alerts > 0 ? 'Nécessitent votre attention' : 'Aucune alerte en cours' }}
            </p>
        </div>
    </div>
</template>
