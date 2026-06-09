<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    universities: Object,
});

const pages = computed(() => {
    return props.universities.links.map((link) => ({
        label: link.label,
        url: link.url,
        active: link.active,
    }));
});
</script>

<template>
    <Head title="Universités" />

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-gray-900">Universités</h1>
                <Link href="/universities/create" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Nouvelle université
                </Link>
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Acronyme</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Lieu</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Statut</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="university in universities.data" :key="university.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ university.name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ university.acronym }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ university.location }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ university.status }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <Link :href="`/universities/${university.id}/edit`" class="text-indigo-600 hover:text-indigo-900">Modifier</Link>
                                    <form :action="`/universities/${university.id}`" method="post" class="inline-block ml-4">
                                        <input type="hidden" name="_method" value="delete" />
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <button type="submit" class="text-red-600 hover:text-red-900">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mt-6 flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Page {{ universities.current_page }} sur {{ universities.last_page }}
                        </div>
                        <nav class="flex space-x-2" aria-label="Pagination">
                            <Link
                                v-for="link in pages"
                                :key="link.label"
                                v-if="link.url"
                                :href="link.url"
                                class="rounded-md border px-3 py-1 text-sm"
                                :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            >
                                <span v-html="link.label"></span>
                            </Link>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
