<script setup>
import { LMap, LTileLayer, LCircleMarker, LPopup } from '@vue-leaflet/vue-leaflet';
import 'leaflet/dist/leaflet.css';
import { computed } from 'vue';

const props = defineProps({
    latitude: { type: [Number, String], default: null },
    longitude: { type: [Number, String], default: null },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    status: { type: String, default: 'approved' },
    height: { type: String, default: '260px' },
    zoom: { type: Number, default: 11 },
});

const lat = computed(() => (props.latitude !== null ? Number(props.latitude) : null));
const lng = computed(() => (props.longitude !== null ? Number(props.longitude) : null));

const hasCoords = computed(() =>
    lat.value !== null && lng.value !== null &&
    !Number.isNaN(lat.value) && !Number.isNaN(lng.value),
);

const center = computed(() => (hasCoords.value ? [lat.value, lng.value] : [7.54, -5.55]));

const statusColors = {
    in_progress: '#f59e0b',
    on_hold: '#f97316',
    approved: '#6366f1',
    completed: '#10b981',
    draft: '#9ca3af',
    submitted: '#3b82f6',
    cancelled: '#ef4444',
    archived: '#64748b',
};

const color = computed(() => statusColors[props.status] ?? '#6366f1');
</script>

<template>
    <div class="overflow-hidden rounded-lg ring-1 ring-gray-200" :style="{ height }">
        <div v-if="!hasCoords" class="flex h-full items-center justify-center bg-gray-50 text-sm text-gray-500">
            Coordonnées GPS non renseignées
        </div>
        <LMap
            v-else
            :zoom="zoom"
            :center="center"
            :use-global-leaflet="false"
            :options="{ scrollWheelZoom: false, zoomControl: true }"
            style="height: 100%; width: 100%;"
        >
            <LTileLayer
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                attribution='&copy; OpenStreetMap'
            />
            <LCircleMarker
                :lat-lng="center"
                :radius="10"
                :color="color"
                :fill-color="color"
                :fill-opacity="0.7"
                :weight="2"
            >
                <LPopup>
                    <div class="min-w-[180px]">
                        <p class="text-sm font-semibold text-gray-900">{{ title }}</p>
                        <p v-if="subtitle" class="text-xs text-gray-500">{{ subtitle }}</p>
                    </div>
                </LPopup>
            </LCircleMarker>
        </LMap>
    </div>
</template>
