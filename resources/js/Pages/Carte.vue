<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { LMap, LTileLayer, LCircleMarker, LPopup, LTooltip } from '@vue-leaflet/vue-leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    sites: { type: Array, required: true },
    stats: { type: Object, required: true },
});

const center = ref([7.54, -5.55]);
const zoom = ref(7);

const statusColors = {
    in_progress: '#f59e0b',
    on_hold: '#f97316',
    approved: '#6366f1',
    completed: '#10b981',
    draft: '#9ca3af',
    submitted: '#3b82f6',
    cancelled: '#ef4444',
    archived: '#64748b',
    none: '#cbd5e1',
};

const statusLabels = {
    in_progress: 'En cours',
    on_hold: 'En pause',
    approved: 'Approuvé',
    completed: 'Terminé',
    draft: 'Brouillon',
    submitted: 'Soumis',
    cancelled: 'Annulé',
    archived: 'Archivé',
    none: 'Aucun projet',
};

const markerRadius = (site) => {
    if (site.projects_total === 0) return 8;
    return Math.min(18, 8 + site.projects_total * 2);
};

const activeFilter = ref('all');
const filteredSites = computed(() => {
    if (activeFilter.value === 'all') return props.sites;
    if (activeFilter.value === 'active') return props.sites.filter((s) => s.projects_active > 0);
    if (activeFilter.value === 'completed') return props.sites.filter((s) => s.projects_completed > 0);
    return props.sites;
});

const openProject = (id) => {
    router.visit(route('dashboard'));
};
</script>

<template>
    <Head title="Carte des sites PDU" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Accueil', href: route('dashboard') }, { label: 'Carte' }]">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Carte des sites PDU-CI</h2>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="activeFilter === 'all' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'"
                        @click="activeFilter = 'all'"
                    >Tous ({{ sites.length }})</button>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="activeFilter === 'active' ? 'bg-amber-500 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'"
                        @click="activeFilter = 'active'"
                    >Sites actifs</button>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="activeFilter === 'completed' ? 'bg-emerald-500 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'"
                        @click="activeFilter = 'completed'"
                    >Avec livrables</button>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <!-- Stats -->
            <div class="space-y-3 lg:col-span-1">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Sites universitaires</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ stats.total_sites }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Projets PDU</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ stats.total_projects }}</p>
                    <div class="mt-2 flex gap-3 text-xs">
                        <span class="text-amber-600">● {{ stats.active_projects }} actifs</span>
                        <span class="text-emerald-600">● {{ stats.completed_projects }} terminés</span>
                    </div>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Légende</p>
                    <ul class="space-y-1.5 text-xs">
                        <li v-for="(color, key) in statusColors" :key="key" class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full border border-white shadow" :style="{ backgroundColor: color }" />
                            <span class="text-gray-700">{{ statusLabels[key] }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Carte -->
            <div class="h-[600px] overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 lg:col-span-3">
                <LMap :zoom="zoom" :center="center" :use-global-leaflet="false" :options="{ scrollWheelZoom: true }" style="height: 100%; width: 100%;">
                    <LTileLayer
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    />
                    <LCircleMarker
                        v-for="site in filteredSites"
                        :key="site.id"
                        :lat-lng="[site.latitude, site.longitude]"
                        :radius="markerRadius(site)"
                        :color="statusColors[site.dominant_status]"
                        :fill-color="statusColors[site.dominant_status]"
                        :fill-opacity="0.7"
                        :weight="2"
                    >
                        <LTooltip>{{ site.acronym }} · {{ site.projects_total }} projet(s)</LTooltip>
                        <LPopup>
                            <div class="min-w-[220px] space-y-2">
                                <div>
                                    <p class="text-sm font-bold text-gray-900">{{ site.name }}</p>
                                    <p class="text-xs text-gray-500">{{ site.location }} · {{ site.region }}</p>
                                </div>
                                <div class="grid grid-cols-3 gap-2 border-y border-gray-100 py-2 text-center">
                                    <div><p class="text-lg font-bold text-gray-900">{{ site.projects_total }}</p><p class="text-[10px] uppercase text-gray-500">Total</p></div>
                                    <div><p class="text-lg font-bold text-amber-600">{{ site.projects_active }}</p><p class="text-[10px] uppercase text-gray-500">Actifs</p></div>
                                    <div><p class="text-lg font-bold text-emerald-600">{{ site.projects_completed }}</p><p class="text-[10px] uppercase text-gray-500">Finis</p></div>
                                </div>
                                <div v-if="site.avg_progress > 0">
                                    <p class="text-xs text-gray-600">Avancement moyen : <span class="font-semibold">{{ site.avg_progress }}%</span></p>
                                </div>
                                <ul v-if="site.projects.length" class="max-h-32 space-y-1 overflow-y-auto text-xs">
                                    <li v-for="p in site.projects" :key="p.id" class="flex items-center gap-1.5 text-gray-700">
                                        <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: statusColors[p.status] }" />
                                        <span class="truncate font-mono">{{ p.code }}</span>
                                        <span class="ml-auto text-gray-400">{{ Math.round(p.progress_percentage) }}%</span>
                                    </li>
                                </ul>
                            </div>
                        </LPopup>
                    </LCircleMarker>
                </LMap>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
