<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    projects: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const page = usePage();
const showCreateModal = ref(false);
const createForm = ref({
    code: '',
    title: '',
    university_id: '',
    start_date: '',
    end_date: '',
    planned_completion_date: '',
    status: 'draft',
    type: 'construction',
    budget_allocated: '',
});
const formErrors = computed(() => page.props.errors || {});
const canCreateProject = computed(() => page.props.permissions?.includes('create_project'));
const canDeleteProject = computed(() => page.props.permissions?.includes('delete_project'));

const statusFilter = ref('');
const typeFilter = ref('');
const regionFilter = ref('');
const search = ref('');

const statusLabels = {
    draft: 'Brouillon', submitted: 'Soumis', approved: 'Approuvé',
    in_progress: 'En cours', on_hold: 'En pause', completed: 'Terminé',
    cancelled: 'Annulé', archived: 'Archivé',
};

const statusBadge = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-blue-100 text-blue-700',
    approved: 'bg-indigo-100 text-indigo-700',
    in_progress: 'bg-amber-100 text-amber-700',
    on_hold: 'bg-orange-100 text-orange-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
    archived: 'bg-slate-100 text-slate-700',
};

const typeLabels = {
    construction: 'Construction', rehabilitation: 'Réhabilitation',
    equipement: 'Équipement', formation: 'Formation',
    recherche: 'Recherche', numerique: 'Numérique',
};

const regions = computed(() => {
    const set = new Set();
    props.filters.universities.forEach((u) => u.region && set.add(u.region));
    return [...set].sort();
});

const filtered = computed(() =>
    props.projects.filter((p) => {
        if (statusFilter.value && p.status !== statusFilter.value) return false;
        if (typeFilter.value && p.type !== typeFilter.value) return false;
        if (regionFilter.value && p.region !== regionFilter.value) return false;
        if (search.value) {
            const q = String(search.value).trim().toLowerCase();
            if (q) {
                const hay = `${p.code ?? ''} ${p.title ?? ''} ${p.university_acronym ?? ''} ${p.region ?? ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
        }
        return true;
    })
);

const resetFilters = () => {
    statusFilter.value = '';
    typeFilter.value = '';
    regionFilter.value = '';
    search.value = '';
};

const clampPct = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return 0;
    return Math.max(0, Math.min(100, n));
};

const fmtPct = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return '—';
    return `${n.toFixed(1)}%`;
};

const openProject = (p) => {
    router.visit('/projects/' + p.id, { preserveScroll: true });
};

const openCreate = () => {
    showCreateModal.value = true;
};

const closeCreate = () => {
    showCreateModal.value = false;
    createForm.value = {
        code: '',
        title: '',
        university_id: '',
        start_date: '',
        end_date: '',
        planned_completion_date: '',
        status: 'draft',
        type: 'construction',
        budget_allocated: '',
    };
};

const submitCreate = () => {
    router.post(route('projects.store'), createForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            closeCreate();
        },
    });
};

const canEditProject = computed(() => page.props.permissions?.includes('create_project'));

const updateStatus = (p, newStatus) => {
    router.patch(route('projects.status.update', p.id), { status: newStatus }, {
        preserveScroll: true,
    });
};

const deleteProject = (p) => {
    if (!confirm(`Supprimer le projet ${p.code} ? Cette action peut être annulée via la corbeille.`)) {
        return;
    }
    router.delete(route('projects.destroy', p.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-emerald-50 p-4 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="grid gap-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-end">
                    <div class="min-w-[180px]">
                        <label class="block text-xs font-semibold text-gray-700">Recherche</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Code, titre, site, région…"
                            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        />
                    </div>
                    <div class="min-w-[170px]">
                        <label class="block text-xs font-semibold text-gray-700">Statut</label>
                        <select
                            v-model="statusFilter"
                            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        >
                            <option value="">Tous</option>
                            <option v-for="s in filters.statuses" :key="s" :value="s">{{ statusLabels[s] }}</option>
                        </select>
                    </div>
                    <div class="min-w-[170px]">
                        <label class="block text-xs font-semibold text-gray-700">Type</label>
                        <select
                            v-model="typeFilter"
                            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        >
                            <option value="">Tous</option>
                            <option v-for="t in filters.types" :key="t" :value="t">{{ typeLabels[t] }}</option>
                        </select>
                    </div>
                    <div class="min-w-[170px]">
                        <label class="block text-xs font-semibold text-gray-700">Région</label>
                        <select
                            v-model="regionFilter"
                            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        >
                            <option value="">Toutes</option>
                            <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="canCreateProject"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        @click="openCreate"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Ajouter
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200"
                        @click="resetFilters"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M6.5 17.5a7 7 0 010-11m11 0a7 7 0 010 11" />
                        </svg>
                        Réinitialiser
                    </button>
                </div>
            </div>
            <div class="mt-3 text-xs text-gray-600">
                <span class="font-semibold text-gray-900">{{ filtered.length }}</span> projet{{ filtered.length > 1 ? 's' : '' }} affiché{{ filtered.length > 1 ? 's' : '' }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Projet</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Site</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Physique</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Financier</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Statut</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Alertes</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 bg-white">
                    <tr v-if="filtered.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">Aucun projet ne correspond aux filtres.</td>
                    </tr>
                    <tr
                        v-for="p in filtered"
                        :key="p.id"
                        class="cursor-pointer transition hover:bg-gray-50"
                        @click="openProject(p)"
                    >
                        <td class="px-4 py-2">
                            <div class="font-medium text-gray-900">{{ p.title }}</div>
                            <div class="text-xs text-gray-500">{{ p.code }}</div>
                        </td>
                        <td class="px-4 py-2 text-gray-700">
                            {{ p.university_acronym }}
                            <div class="text-xs text-gray-500">{{ p.region }}</div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <div class="h-2.5 w-28 overflow-hidden rounded-full bg-gray-200">
                                    <div
                                        class="h-full bg-gradient-to-r from-emerald-500 to-emerald-400"
                                        :style="{ width: clampPct(p.progress_percentage) + '%' }"
                                        :title="fmtPct(p.progress_percentage)"
                                    ></div>
                                </div>
                                <span class="tabular-nums text-xs font-medium text-gray-700">{{ fmtPct(p.progress_percentage) }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <div class="h-2.5 w-28 overflow-hidden rounded-full bg-gray-200">
                                    <div
                                        class="h-full bg-gradient-to-r from-indigo-500 to-indigo-400"
                                        :style="{ width: clampPct(p.budget_execution_rate) + '%' }"
                                        :title="fmtPct(p.budget_execution_rate)"
                                    ></div>
                                </div>
                                <span class="tabular-nums text-xs font-medium text-gray-700">{{ fmtPct(p.budget_execution_rate) }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2" @click.stop>
                            <select
                                v-if="canEditProject"
                                :value="p.status"
                                @change="updateStatus(p, $event.target.value)"
                                class="rounded-full border-0 px-2 py-0.5 text-xs font-medium cursor-pointer focus:ring-2 focus:ring-indigo-300"
                                :class="statusBadge[p.status]"
                            >
                                <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <span v-else class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadge[p.status]">
                                {{ statusLabels[p.status] }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <span v-if="p.alerts_count > 0" class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">
                                {{ p.alerts_count }}
                            </span>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                                    @click.stop="openProject(p)"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Voir
                                </button>
                                <button
                                    v-if="canDeleteProject"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 shadow-sm transition hover:bg-red-100"
                                    @click.stop="deleteProject(p)"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" />
                                    </svg>
                                    Supprimer
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <teleport to="body">
        <div v-if="showCreateModal && canCreateProject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Ajouter un projet</h3>
                        <p class="mt-1 text-sm text-gray-500">Créez un nouveau projet directement depuis le tableau de bord.</p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeCreate">✕</button>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Code</label>
                        <input v-model="createForm.code" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        <p v-if="formErrors.code" class="mt-1 text-xs text-red-600">{{ formErrors.code[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Titre</label>
                        <input v-model="createForm.title" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        <p v-if="formErrors.title" class="mt-1 text-xs text-red-600">{{ formErrors.title[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Université</label>
                        <select v-model="createForm.university_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Sélectionner</option>
                            <option v-for="u in filters.universities" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <p v-if="formErrors.university_id" class="mt-1 text-xs text-red-600">{{ formErrors.university_id[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date de début</label>
                        <input v-model="createForm.start_date" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        <p v-if="formErrors.start_date" class="mt-1 text-xs text-red-600">{{ formErrors.start_date[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date de fin prévue</label>
                        <input v-model="createForm.end_date" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        <p v-if="formErrors.end_date" class="mt-1 text-xs text-red-600">{{ formErrors.end_date[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date de livraison prévue</label>
                        <input v-model="createForm.planned_completion_date" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        <p v-if="formErrors.planned_completion_date" class="mt-1 text-xs text-red-600">{{ formErrors.planned_completion_date[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Statut</label>
                        <select v-model="createForm.status" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="draft">Brouillon</option>
                            <option value="submitted">Soumis</option>
                            <option value="approved">Approuvé</option>
                            <option value="in_progress">En cours</option>
                            <option value="on_hold">En pause</option>
                            <option value="completed">Terminé</option>
                            <option value="cancelled">Annulé</option>
                            <option value="archived">Archivé</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <select v-model="createForm.type" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="construction">Construction</option>
                            <option value="rehabilitation">Réhabilitation</option>
                            <option value="equipement">Équipement</option>
                            <option value="formation">Formation</option>
                            <option value="recherche">Recherche</option>
                            <option value="numerique">Numérique</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Budget total alloué (XOF)</label>
                        <input v-model="createForm.budget_allocated" type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        <p v-if="formErrors.budget_allocated" class="mt-1 text-xs text-red-600">{{ formErrors.budget_allocated[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Devise</label>
                        <input type="text" value="XOF" class="mt-1 w-full rounded-md border-gray-300 bg-gray-100 text-gray-700 shadow-sm" disabled />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeCreate">Annuler</button>
                    <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="submitCreate">Créer</button>
                </div>
            </div>
        </div>
    </teleport>
</template>
