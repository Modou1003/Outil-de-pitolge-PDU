<script setup>
import { computed } from 'vue';
import { Pie } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    ArcElement,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, ArcElement);

const props = defineProps({
    breakdown: {
        type: Object,
        required: true,
    },
});

const labels = {
    construction: 'Construction',
    rehabilitation: 'Réhabilitation',
    equipement: 'Équipement',
    formation: 'Formation',
    recherche: 'Recherche',
    numerique: 'Numérique',
};

const colors = {
    construction: '#3b82f6',
    rehabilitation: '#f59e0b',
    equipement: '#10b981',
    formation: '#8b5cf6',
    recherche: '#ec4899',
    numerique: '#06b6d4',
};

const chartData = computed(() => {
    const keys = Object.keys(props.breakdown);
    return {
        labels: keys.map((k) => labels[k] ?? k),
        datasets: [
            {
                backgroundColor: keys.map((k) => colors[k] ?? '#9ca3af'),
                data: keys.map((k) => props.breakdown[k]),
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'right' },
    },
};
</script>

<template>
    <div class="h-80">
        <Pie :data="chartData" :options="chartOptions" />
    </div>
</template>
