<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
});

const chartData = computed(() => ({
    labels: props.items.map((i) => i.label),
    datasets: [
        {
            label: 'Prévu (%)',
            backgroundColor: '#93c5fd',
            borderColor: '#3b82f6',
            borderWidth: 1,
            data: props.items.map((i) => i.planned),
        },
        {
            label: 'Réel (%)',
            backgroundColor: '#86efac',
            borderColor: '#16a34a',
            borderWidth: 1,
            data: props.items.map((i) => i.actual),
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            beginAtZero: true,
            max: 100,
            ticks: { callback: (v) => v + '%' },
        },
    },
    plugins: {
        tooltip: {
            callbacks: {
                title: (ctx) => {
                    const idx = ctx[0].dataIndex;
                    return ctx[0].chart.data.labels[idx];
                },
            },
        },
    },
};
</script>

<template>
    <div class="h-80">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
