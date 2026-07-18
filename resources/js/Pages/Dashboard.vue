<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import ProjectsTable from '@/Components/Dashboard/ProjectsTable.vue';

defineProps({
    projects: { type: Array, required: true },
    filters: { type: Object, required: true },
});
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
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <ProjectsTable :projects="projects" :filters="filters" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
