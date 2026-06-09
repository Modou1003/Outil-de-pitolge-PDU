<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    users: { type: Object, required: true },
    projects: { type: Array, required: true },
    roles: { type: Array, default: () => [] },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingUser = ref(null);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role: '',
    assigned_projects: [],
});

const editForm = useForm({
    name: '',
    email: '',
    role: '',
    assigned_projects: [],
});

const roleSearchCreate = ref('');
const roleSearchEdit = ref('');

const roleLabel = (name) => {
    const map = {
        admin: 'Admin',
        directeur: 'Directeur',
        chef_projet: 'Chef de projet',
        agent_financier: 'Agent financier',
        visiteur: 'Visiteur',
        gc_maitre_ouvrage: "Maître d'ouvrage",
        gc_maitre_ouvrage_delegue: "Maître d'ouvrage délégué",
        gc_amo: "AMO (Assistant maître d'ouvrage)",
        gc_maitre_oeuvre: "Maître d'œuvre",
        gc_architecte: 'Architecte',
        gc_bureau_etudes: "Bureau d'études (BET)",
        gc_ingenieur_structure: 'Ingénieur structure',
        gc_ingenieur_geotechnique: 'Ingénieur géotechnique',
        gc_ingenieur_hydraulique: 'Ingénieur hydraulique',
        gc_ingenieur_vrd: 'Ingénieur VRD',
        gc_economiste: 'Économiste de la construction',
        gc_opc: 'OPC (Ordonnancement, Pilotage, Coordination)',
        gc_controle_technique: 'Contrôleur technique',
        gc_coordonnateur_sps_hse: 'Coordonnateur SPS / HSE',
        gc_qhse: 'Responsable QHSE',
        gc_laboratoire_essais: "Laboratoire d'essais",
        gc_topographe_geometre: 'Topographe / Géomètre',
        gc_entreprise_generale: 'Entreprise générale',
        gc_conducteur_travaux: 'Conducteur de travaux',
        gc_chef_chantier: 'Chef de chantier',
        gc_responsable_methodes: 'Responsable méthodes',
        gc_fournisseur: 'Fournisseur',
        gc_sous_traitant: 'Sous-traitant',
        gc_inspection_suivi: 'Inspection / Suivi',
        gc_commission_reception: 'Commission de réception',
    };
    return map[name] ?? String(name ?? '').replaceAll('_', ' ');
};

const rolesForCreate = computed(() => {
    const q = roleSearchCreate.value.trim().toLowerCase();
    if (!q) return props.roles;
    return props.roles.filter((r) => {
        const label = roleLabel(r).toLowerCase();
        return r.toLowerCase().includes(q) || label.includes(q);
    });
});

const rolesForEdit = computed(() => {
    const q = roleSearchEdit.value.trim().toLowerCase();
    if (!q) return props.roles;
    return props.roles.filter((r) => {
        const label = roleLabel(r).toLowerCase();
        return r.toLowerCase().includes(q) || label.includes(q);
    });
});

const editRoleOptions = computed(() => {
    // Keep edit dropdown stable: always show full list when no search is entered.
    return Array.isArray(props.roles) ? props.roles : [];
});

const editModalKey = computed(() => editingUser.value?.id ?? 'no-user');

const roleColors = {
    admin: 'bg-red-100 text-red-700',
    directeur: 'bg-purple-100 text-purple-700',
    chef_projet: 'bg-blue-100 text-blue-700',
    agent_financier: 'bg-green-100 text-green-700',
    visiteur: 'bg-gray-100 text-gray-700',
};

const openCreateModal = () => {
    createForm.reset();
    roleSearchCreate.value = '';
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
};

const openEditModal = (user) => {
    editingUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.role = user.roles[0] || '';
    editForm.assigned_projects = []; // Si applicable
    roleSearchEdit.value = '';
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingUser.value = null;
};

const submitCreate = () => {
    createForm.post(route('admin.users.store'), {
        onSuccess: () => {
            closeCreateModal();
        },
    });
};

const submitEdit = () => {
    editForm.put(route('admin.users.update', editingUser.value.id), {
        onSuccess: () => {
            closeEditModal();
        },
    });
};

const toggleActive = (user) => {
    router.patch(route('admin.users.toggle-active', user.id));
};

const deleteUser = (user) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')) {
        router.delete(route('admin.users.destroy', user.id));
    }
};

const breadcrumbs = [
    { label: 'Accueil', href: route('dashboard') },
    { label: 'Administration', href: route('admin.users.index') },
    { label: 'Utilisateurs' },
];
</script>

<template>
    <Head title="Gestion des utilisateurs" />

    <AuthenticatedLayout :breadcrumbs="breadcrumbs">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Gestion des utilisateurs</h2>
                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Nouveau utilisateur
                </button>
            </div>
        </template>

        <div class="overflow-hidden bg-white shadow-sm ring-1 ring-gray-200 sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nom</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rôle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Dernier login</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Statut</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ user.name }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ user.email }}</td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span
                                v-for="role in user.roles"
                                :key="role"
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="roleColors[role] || 'bg-gray-100 text-gray-700'"
                            >
                                {{ role }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                            {{ user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Jamais' }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="user.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            >
                                {{ user.is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium">
                            <button
                                @click="openEditModal(user)"
                                class="text-indigo-600 hover:text-indigo-900"
                            >
                                Modifier
                            </button>
                            <button
                                @click="toggleActive(user)"
                                class="ml-4 text-yellow-600 hover:text-yellow-900"
                            >
                                {{ user.is_active ? 'Désactiver' : 'Activer' }}
                            </button>
                            <button
                                @click="deleteUser(user)"
                                class="ml-4 text-red-600 hover:text-red-900"
                            >
                                Supprimer
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Affichage de {{ users.from }} à {{ users.to }} sur {{ users.total }} résultats
                </div>
                <div class="flex space-x-1">
                    <button
                        v-for="link in users.links"
                        :key="link.label"
                        :disabled="!link.url"
                        @click="router.get(link.url)"
                        class="px-3 py-2 text-sm border border-gray-300 rounded-md"
                        :class="link.active ? 'bg-indigo-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Modal Création -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeCreateModal">
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeCreateModal"></div>
                <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form @submit.prevent="submitCreate">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Créer un utilisateur</h3>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nom</label>
                                    <input
                                        v-model="createForm.name"
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    <div v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input
                                        v-model="createForm.email"
                                        type="email"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    <div v-if="createForm.errors.email" class="mt-1 text-sm text-red-600">{{ createForm.errors.email }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Mot de passe</label>
                                    <input
                                        v-model="createForm.password"
                                        type="password"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    <div v-if="createForm.errors.password" class="mt-1 text-sm text-red-600">{{ createForm.errors.password }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Rôle</label>
                                    <input
                                        v-model="roleSearchCreate"
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="Rechercher un rôle…"
                                    />
                                    <select
                                        v-model="createForm.role"
                                        class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    >
                                        <option value="">Sélectionner un rôle</option>
                                        <option v-for="r in rolesForCreate" :key="`create-${r}`" :value="r">{{ roleLabel(r) }}</option>
                                    </select>
                                    <div v-if="createForm.errors.role" class="mt-1 text-sm text-red-600">{{ createForm.errors.role }}</div>
                                </div>
                                <!-- Projets assignés si applicable -->
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Créer
                            </button>
                            <button
                                type="button"
                                @click="closeCreateModal"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Édition -->
        <div
            v-if="showEditModal"
            :key="editModalKey"
            class="fixed inset-0 z-50 overflow-y-auto"
            @click.self="closeEditModal"
        >
            <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeEditModal"></div>
                <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form @submit.prevent="submitEdit">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Modifier l'utilisateur</h3>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nom</label>
                                    <input
                                        v-model="editForm.name"
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    <div v-if="editForm.errors.name" class="mt-1 text-sm text-red-600">{{ editForm.errors.name }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input
                                        v-model="editForm.email"
                                        type="email"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    <div v-if="editForm.errors.email" class="mt-1 text-sm text-red-600">{{ editForm.errors.email }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Rôle</label>
                                    <input
                                        v-model="roleSearchEdit"
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="Rechercher un rôle…"
                                    />
                                    <select
                                        v-model="editForm.role"
                                        class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    >
                                        <option value="">Sélectionner un rôle</option>
                                        <option
                                            v-for="r in (roleSearchEdit.trim() ? rolesForEdit : editRoleOptions)"
                                            :key="`edit-${r}`"
                                            :value="r"
                                        >
                                            {{ roleLabel(r) }}
                                        </option>
                                    </select>
                                    <div v-if="editForm.errors.role" class="mt-1 text-sm text-red-600">{{ editForm.errors.role }}</div>
                                </div>
                                <!-- Projets assignés si applicable -->
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button
                                type="submit"
                                :disabled="editForm.processing"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Modifier
                            </button>
                            <button
                                type="button"
                                @click="closeEditModal"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>