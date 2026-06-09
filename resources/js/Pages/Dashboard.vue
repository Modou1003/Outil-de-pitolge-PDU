<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProjectsTable from '@/Components/Dashboard/ProjectsTable.vue';

defineProps({
    projects: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const exportingExcel = ref(false);

const downloadGlobalExcel = () => {
    exportingExcel.value = true;
    window.location.href = route('rapports.excel.global');
    setTimeout(() => (exportingExcel.value = false), 2500);
};
</script>

<template>
    <Head title="Projets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Liste des projets PDU</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ projects.length }} projet{{ projects.length > 1 ? 's' : '' }} au total — cliquez sur une ligne pour consulter le détail.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
                    :disabled="exportingExcel"
                    @click="downloadGlobalExcel"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" /></svg>
                    {{ exportingExcel ? 'Génération…' : 'Exporter Excel' }}
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <ProjectsTable :projects="projects" :filters="filters" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
