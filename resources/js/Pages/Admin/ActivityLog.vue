<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminTabs from '@/Components/Admin/AdminTabs.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    stats: { type: Object, required: true },
});

const form = reactive({
    user_id: props.filters.user_id ?? '',
    project_id: props.filters.project_id ?? '',
    model: props.filters.model ?? '',
    action: props.filters.action ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const apply = () => {
    const query = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
    router.get(route('admin.activites.index'), query, { preserveState: true, preserveScroll: true });
};

const reset = () => {
    Object.keys(form).forEach((k) => { form[k] = ''; });
    router.get(route('admin.activites.index'), {}, { preserveState: true });
};

const goToPage = (page) => {
    const query = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
    router.get(route('admin.activites.index'), { ...query, page }, { preserveState: true, preserveScroll: true });
};

const hasFilters = computed(() => Object.values(form).some((v) => v !== '' && v !== null));

const breadcrumbs = [
    { label: 'Accueil', href: route('dashboard') },
    { label: 'Administration', href: route('admin.users.index') },
    { label: "Journal d'activité" },
];

// Une couleur par nature d'action, pour repérer les suppressions d'un coup d'œil.
const actionStyle = {
    created: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    updated: 'bg-indigo-100 text-indigo-700 ring-indigo-200',
    deleted: 'bg-red-100 text-red-700 ring-red-200',
};

const fmtDateTime = (d) => {
    if (!d) return '—';
    const x = new Date(d);
    return x.toLocaleDateString('fr-FR') + ' à ' + x.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Journal d'activité" />

    <AuthenticatedLayout :breadcrumbs="breadcrumbs">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Journal d'activité</h2>
        </template>

        <div class="space-y-4">
            <AdminTabs />

            <p class="text-sm text-gray-500">
                Toutes les interventions sur les projets et les comptes, du plus récent au plus ancien.
            </p>

            <!-- Synthèse -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Entrées enregistrées</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.total }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Aujourd'hui</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-700">{{ stats.today }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Utilisateurs actifs</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.contributors }}</p>
                </div>
            </div>

            <!-- Filtres -->
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Utilisateur</label>
                        <select v-model="form.user_id" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Tous</option>
                            <option v-for="u in options.users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-700">Projet</label>
                        <select v-model="form.project_id" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Tous</option>
                            <option v-for="p in options.projects" :key="p.id" :value="p.id">{{ p.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Type</label>
                        <select v-model="form.model" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Tous</option>
                            <option v-for="m in options.models" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Action</label>
                        <select v-model="form.action" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Toutes</option>
                            <option v-for="a in options.actions" :key="a.value" :value="a.value">{{ a.label }}</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="button" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700" @click="apply">
                            Filtrer
                        </button>
                        <button v-if="hasFilters" type="button" class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="reset">
                            Effacer
                        </button>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Du</label>
                        <input v-model="form.from" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Au</label>
                        <input v-model="form.to" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                    </div>
                </div>
            </div>

            <!-- Journal -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">
                        Interventions ({{ logs.total }})
                    </h3>
                    <span class="text-xs text-gray-500">Page {{ logs.current_page }} sur {{ logs.last_page }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Date</th>
                                <th class="px-4 py-2">Utilisateur</th>
                                <th class="px-4 py-2">Action</th>
                                <th class="px-4 py-2">Type</th>
                                <th class="px-4 py-2">Détail</th>
                                <th class="px-4 py-2">Projet</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="l in logs.data" :key="l.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-2 text-xs text-gray-600">{{ fmtDateTime(l.created_at) }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ l.user }}</td>
                                <td class="px-4 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium ring-1" :class="actionStyle[l.action] ?? 'bg-gray-100 text-gray-600 ring-gray-200'">
                                        {{ l.action_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ l.model_label }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ l.description || '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ l.project || '—' }}</td>
                            </tr>
                            <tr v-if="!logs.data.length">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Aucune intervention enregistrée pour ces critères.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="logs.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-5 py-3">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 disabled:opacity-40"
                        :disabled="logs.current_page <= 1"
                        @click="goToPage(logs.current_page - 1)"
                    >
                        Précédent
                    </button>
                    <span class="text-xs text-gray-500">{{ logs.current_page }} / {{ logs.last_page }}</span>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 disabled:opacity-40"
                        :disabled="logs.current_page >= logs.last_page"
                        @click="goToPage(logs.current_page + 1)"
                    >
                        Suivant
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
