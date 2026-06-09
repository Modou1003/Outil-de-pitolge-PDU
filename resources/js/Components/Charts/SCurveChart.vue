<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Filler,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale, Filler);

const props = defineProps({
    series: {
        type: Array,
        required: true,
    },
});

const formatMonth = (ym) => {
    const [year, month] = ym.split('-');
    const names = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
    return names[parseInt(month, 10) - 1] + ' ' + year.slice(2);
};

const chartData = computed(() => ({
    labels: props.series.map((s) => formatMonth(s.month)),
    datasets: [
        {
            label: 'Décaissement cumulé (FCFA)',
            data: props.series.map((s) => s.cumulative),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.2)',
            fill: true,
            tension: 0.35,
            pointRadius: 3,
            pointBackgroundColor: '#6366f1',
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (v) => {
                    if (v >= 1e9) return (v / 1e9).toFixed(1) + ' Mds';
                    if (v >= 1e6) return (v / 1e6).toFixed(0) + ' M';
                    if (v >= 1e3) return (v / 1e3).toFixed(0) + ' K';
                    return v;
                },
            },
        },
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const v = ctx.parsed.y;
                    return ' ' + new Intl.NumberFormat('fr-FR').format(v) + ' FCFA';
                },
            },
        },
    },
};
</script>

<template>
    <div class="h-80">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
