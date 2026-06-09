<script setup>
import ProjectMiniMap from '@/Components/Map/ProjectMiniMap.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    project: { type: Object, required: true },
    alerts: { type: Array, default: () => [] },
    teamCandidates: { type: Array, default: () => [] },
    canManageTeam: { type: Boolean, default: false },
});

const formatMoney = (v) => new Intl.NumberFormat('fr-FR').format(v ?? 0);
const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—';
const page = usePage();

const typeLabels = {
    construction: 'Construction', rehabilitation: 'Réhabilitation',
    equipment: 'Équipement', training: 'Formation',
    research: 'Recherche', digital: 'Numérique',
};

const severityStyle = {
    critical: 'border-red-300 bg-red-50 text-red-800',
    warning: 'border-amber-300 bg-amber-50 text-amber-800',
    info: 'border-blue-300 bg-blue-50 text-blue-800',
};

const baseTeamMembers = computed(() => [
    { role: 'Créé par', user: props.project.creator },
    { role: 'Directeur', user: props.project.director },
    { role: 'Chef de projet', user: props.project.project_manager },
    { role: 'Agent financier', user: props.project.financial_agent },
].map((x) => {
    if (x.user) return { role: x.role, kind: 'user', name: x.user.name, source: 'base' };
    if (x.role === 'Directeur' && props.project.director_name) return { role: x.role, kind: 'external', name: props.project.director_name, source: 'base' };
    if (x.role === 'Chef de projet' && props.project.project_manager_name) return { role: x.role, kind: 'external', name: props.project.project_manager_name, source: 'base' };
    if (x.role === 'Agent financier' && props.project.financial_agent_name) return { role: x.role, kind: 'external', name: props.project.financial_agent_name, source: 'base' };
    return null;
}).filter(Boolean));

const extendedTeamMembersDisplay = computed(() => {
    const rows = Array.isArray(props.project.team_members) ? props.project.team_members : [];
    return rows
        .map((m) => {
            const name = m?.user?.name ?? m?.name ?? null;
            if (!name) return null;
            return {
                id: m.id,
                role: m.role_label ?? m.role_key ?? 'Membre',
                kind: m.user ? 'user' : 'external',
                name,
                source: 'extended',
            };
        })
        .filter(Boolean);
});

const teamMembers = computed(() => [...baseTeamMembers.value, ...extendedTeamMembersDisplay.value]);

const canManageTeam = computed(() => {
    const permissions = page.props?.permissions || page.props?.auth?.permissions || [];
    return props.canManageTeam || permissions.includes('create_project');
});

const teamForm = reactive({
    director_id: props.project.director?.id ?? '',
    project_manager_id: props.project.project_manager?.id ?? '',
    financial_agent_id: props.project.financial_agent?.id ?? '',
    director_name: props.project.director_name ?? '',
    project_manager_name: props.project.project_manager_name ?? '',
    financial_agent_name: props.project.financial_agent_name ?? '',
    director_email: props.project.director_email ?? '',
    project_manager_email: props.project.project_manager_email ?? '',
    financial_agent_email: props.project.financial_agent_email ?? '',
    processing: false,
});

const saveTeam = () => {
    if (!canManageTeam.value || teamForm.processing) return;

    teamForm.processing = true;
    router.patch(route('projects.team.update', props.project.id), {
        director_id: teamForm.director_id || null,
        project_manager_id: teamForm.project_manager_id || null,
        financial_agent_id: teamForm.financial_agent_id || null,
        director_name: teamForm.director_name || null,
        project_manager_name: teamForm.project_manager_name || null,
        financial_agent_name: teamForm.financial_agent_name || null,
        director_email: teamForm.director_email || null,
        project_manager_email: teamForm.project_manager_email || null,
        financial_agent_email: teamForm.financial_agent_email || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            teamForm.processing = false;
        },
    });
};

const removeMember = (field) => {
    if (!canManageTeam.value || teamForm.processing) return;
    teamForm[field] = '';
    saveTeam();
};

const initials = (name) => String(name || '?')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .map((s) => s[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

const pickUser = (fieldId, fieldName) => {
    teamForm[fieldName] = '';
    if (fieldId === 'director_id') teamForm.director_email = '';
    if (fieldId === 'project_manager_id') teamForm.project_manager_email = '';
    if (fieldId === 'financial_agent_id') teamForm.financial_agent_email = '';
    if (teamForm[fieldId] === '') return;
};

const typeExternalName = (fieldId, fieldName) => {
    teamForm[fieldId] = '';
    if (!teamForm[fieldName]) return;
};

const ROLE_CATALOG = [
    { key: 'maitre_ouvrage', label: "Maître d'ouvrage" },
    { key: 'maitre_ouvrage_delegue', label: "Maître d'ouvrage délégué" },
    { key: 'amo', label: "AMO (Assistant maître d'ouvrage)" },
    { key: 'maitre_oeuvre', label: "Maître d'œuvre" },
    { key: 'architecte', label: 'Architecte' },
    { key: 'bureau_etudes', label: "Bureau d'études (BET)" },
    { key: 'ingenieur_structure', label: 'Ingénieur structure' },
    { key: 'ingenieur_geotechnique', label: 'Ingénieur géotechnique' },
    { key: 'ingenieur_hydraulique', label: 'Ingénieur hydraulique' },
    { key: 'ingenieur_vrd', label: 'Ingénieur VRD' },
    { key: 'economiste', label: 'Économiste de la construction' },
    { key: 'opc', label: 'OPC (Ordonnancement, Pilotage, Coordination)' },
    { key: 'controle_technique', label: 'Contrôleur technique' },
    { key: 'coordonnateur_sps', label: 'Coordonnateur SPS / HSE' },
    { key: 'qhse', label: 'Responsable QHSE' },
    { key: 'laboratoire', label: "Laboratoire d'essais" },
    { key: 'topographe', label: 'Topographe / Géomètre' },
    { key: 'entreprise_generale', label: 'Entreprise générale' },
    { key: 'conducteur_travaux', label: 'Conducteur de travaux' },
    { key: 'chef_chantier', label: 'Chef de chantier' },
    { key: 'responsable_methodes', label: 'Responsable méthodes' },
    { key: 'fournisseur', label: 'Fournisseur' },
    { key: 'sous_traitant', label: 'Sous-traitant' },
    { key: 'inspection', label: 'Inspection / Suivi' },
    { key: 'reception', label: 'Commission de réception' },
    { key: 'autre', label: 'Autre' },
];

const extendedTeam = reactive({
    members: Array.isArray(props.project.team_members) ? props.project.team_members.map((m) => ({
        id: m.id,
        role_key: m.role_key ?? 'autre',
        role_label: m.role_label ?? 'Autre',
        user_id: m.user?.id ?? '',
        name: m.user?.name ?? (m.name ?? ''),
        organization: m.organization ?? '',
        phone: m.phone ?? '',
        email: m.email ?? '',
        notes: m.notes ?? '',
        sort_order: m.sort_order ?? 0,
    })) : [],
    processing: false,
});

const expandedExtendedIdx = ref(null);

watch(
    () => props.project.team_members,
    (next) => {
        if (!Array.isArray(next)) {
            extendedTeam.members = [];
            return;
        }
        extendedTeam.members = next.map((m) => ({
            id: m.id,
            role_key: m.role_key ?? 'autre',
            role_label: m.role_label ?? 'Autre',
            user_id: m.user?.id ?? '',
            name: m.user?.name ?? (m.name ?? ''),
            organization: m.organization ?? '',
            phone: m.phone ?? '',
            email: m.email ?? '',
            notes: m.notes ?? '',
            sort_order: m.sort_order ?? 0,
        }));

        // Close any open editor when server data refreshes.
        expandedExtendedIdx.value = null;
    },
    { deep: true }
);

const addExtendedMember = () => {
    // Prevent stacking multiple "blank" forms.
    const existingDraftIdx = extendedTeam.members.findIndex((m) => !m.id && !m.user_id && !String(m.name || '').trim());
    if (existingDraftIdx !== -1) {
        expandedExtendedIdx.value = existingDraftIdx;
        return;
    }

    extendedTeam.members.push({
        id: null,
        role_key: 'autre',
        role_label: 'Autre',
        user_id: '',
        name: '',
        organization: '',
        phone: '',
        email: '',
        notes: '',
        sort_order: extendedTeam.members.length,
    });

    expandedExtendedIdx.value = extendedTeam.members.length - 1;
};

const removeExtendedMember = (idx) => {
    extendedTeam.members.splice(idx, 1);
};

const removeExtendedMemberById = (id) => {
    const idx = extendedTeam.members.findIndex((m) => m.id === id);
    if (idx === -1) return;
    removeExtendedMember(idx);
    saveExtendedTeam();
};

const onRoleChange = (m) => {
    const r = ROLE_CATALOG.find((x) => x.key === m.role_key);
    if (r) m.role_label = r.label;
    if (m.role_key !== 'autre') return;
    if (m.role_label && m.role_label !== 'Autre') return;
    m.role_label = 'Autre';
};

const pickExtendedUser = (m) => {
    if (m.user_id) {
        // user selected -> clear free-text name
        m.name = '';
    }
};

const typeExtendedName = (m) => {
    if (m.name && m.name.trim()) {
        m.user_id = '';
    }
};

const saveExtendedTeam = () => {
    if (!canManageTeam.value || extendedTeam.processing) return;
    extendedTeam.processing = true;

    // Drop empty draft rows (no user and no name)
    const normalizedMembers = extendedTeam.members.filter((m) => m.user_id || String(m.name || '').trim());

    const payload = normalizedMembers.map((m, idx) => ({
        id: m.id ?? null,
        role_key: m.role_key,
        role_label: (m.role_key === 'autre' ? (m.role_label || 'Autre') : (ROLE_CATALOG.find((x) => x.key === m.role_key)?.label || m.role_label || 'Autre')),
        user_id: m.user_id || null,
        name: m.user_id ? null : (m.name || null),
        organization: m.organization || null,
        phone: m.phone || null,
        email: m.email || null,
        notes: m.notes || null,
        sort_order: idx,
    }));

    router.patch(route('projects.team-members.update', props.project.id), { members: payload }, {
        preserveScroll: true,
        onSuccess: () => {
            // Close editor; list will refresh from server. Then open a fresh empty form.
            expandedExtendedIdx.value = null;
            // Wait a tick for Inertia props refresh, then open a single new draft row.
            setTimeout(() => {
                addExtendedMember();
            }, 0);
        },
        onFinish: () => { extendedTeam.processing = false; },
    });
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <!-- Carte d'identité -->
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Description</h3>
                <p class="whitespace-pre-line text-sm leading-relaxed text-gray-700">
                    {{ project.description || 'Aucune description fournie.' }}
                </p>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Caractéristiques</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs uppercase text-gray-500">Type</dt><dd class="font-medium text-gray-900">{{ typeLabels[project.type] ?? project.type }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Université</dt><dd class="font-medium text-gray-900">{{ project.university?.name ?? '—' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Localisation</dt><dd class="font-medium text-gray-900">{{ project.university?.location }} · {{ project.university?.region }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Devise</dt><dd class="font-medium text-gray-900">{{ project.currency ?? 'XOF' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Début prévu</dt><dd class="font-medium text-gray-900">{{ formatDate(project.start_date) }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Fin prévue</dt><dd class="font-medium text-gray-900">{{ formatDate(project.end_date) }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Livraison prévue</dt><dd class="font-medium text-gray-900">{{ formatDate(project.planned_completion_date) }}</dd></div>
                </dl>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Budget</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-indigo-50 p-3">
                        <p class="text-xs font-medium text-indigo-700">Alloué</p>
                        <p class="mt-1 text-lg font-bold text-indigo-900">{{ formatMoney(project.budget_allocated) }} <span class="text-xs font-normal">{{ project.currency }}</span></p>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-3">
                        <p class="text-xs font-medium text-amber-700">Décaissé</p>
                        <p class="mt-1 text-lg font-bold text-amber-900">{{ formatMoney(project.budget_spent) }} <span class="text-xs font-normal">{{ project.currency }}</span></p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-3">
                        <p class="text-xs font-medium text-emerald-700">Taux exécution</p>
                        <p class="mt-1 text-lg font-bold text-emerald-900">{{ project.budget_execution_rate?.toFixed(1) }}%</p>
                    </div>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500 transition-all" :style="{ width: `${Math.min(100, project.budget_execution_rate || 0)}%` }" />
                </div>
            </div>

            <div v-if="project.objectives?.length" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Objectifs</h3>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-700">
                    <li v-for="(obj, i) in project.objectives" :key="i">{{ obj }}</li>
                </ul>
            </div>
        </div>

        <!-- Colonne droite : carte + alertes + équipe -->
        <div class="space-y-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Localisation</h3>
                <ProjectMiniMap
                    :latitude="project.university?.latitude"
                    :longitude="project.university?.longitude"
                    :title="project.university?.name"
                    :subtitle="project.university?.location + ' · ' + project.university?.region"
                    :status="project.status"
                    height="220px"
                />
            </div>

            <div v-if="alerts.length" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Alertes ouvertes ({{ alerts.length }})</h3>
                <div class="space-y-2">
                    <div v-for="a in alerts" :key="a.id" class="rounded-lg border-l-2 p-2 text-xs" :class="severityStyle[a.severity]">
                        <p class="font-semibold">{{ a.title }}</p>
                        <p class="mt-0.5 text-gray-700">{{ a.message }}</p>
                    </div>
                </div>
            </div>

            <div v-if="teamMembers.length" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Équipe</h3>
                <ul class="space-y-2 text-sm">
                    <li v-for="(m, i) in teamMembers" :key="i" class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-emerald-500 text-xs font-bold text-white">
                            {{ initials(m.name) }}
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">
                                {{ m.name }}
                                <span v-if="m.kind === 'external'" class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600">Externe</span>
                            </p>
                            <p class="text-xs text-gray-500">{{ m.role }}</p>
                        </div>
                        <button
                            v-if="canManageTeam && m.role !== 'Créé par'"
                            type="button"
                            class="ml-auto rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                            @click="m.source === 'extended'
                                ? removeExtendedMemberById(m.id)
                                : removeMember(m.role === 'Directeur'
                                    ? (props.project.director ? 'director_id' : 'director_name')
                                    : (m.role === 'Chef de projet'
                                        ? (props.project.project_manager ? 'project_manager_id' : 'project_manager_name')
                                        : (props.project.financial_agent ? 'financial_agent_id' : 'financial_agent_name')))"
                        >
                            Supprimer
                        </button>
                    </li>
                </ul>
            </div>

            <div v-if="canManageTeam" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Gérer l'équipe</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium uppercase text-gray-500">Directeur</label>
                        <select v-model="teamForm.director_id" class="mt-1 w-full rounded-md border-gray-300 text-sm" @change="pickUser('director_id','director_name')">
                            <option value="">Aucun</option>
                            <option v-for="u in teamCandidates" :key="`director-${u.id}`" :value="u.id">{{ u.name }}</option>
                        </select>
                        <div class="mt-2 text-xs text-gray-500">Ou saisir un nom (membre externe)</div>
                        <input v-model="teamForm.director_name" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Nom du directeur" @input="typeExternalName('director_id','director_name')" />
                        <input
                            v-model="teamForm.director_email"
                            type="email"
                            class="mt-2 w-full rounded-md border-gray-300 text-sm"
                            placeholder="Email du directeur (pour alertes)"
                            :disabled="!!teamForm.director_id"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase text-gray-500">Chef de projet</label>
                        <select v-model="teamForm.project_manager_id" class="mt-1 w-full rounded-md border-gray-300 text-sm" @change="pickUser('project_manager_id','project_manager_name')">
                            <option value="">Aucun</option>
                            <option v-for="u in teamCandidates" :key="`manager-${u.id}`" :value="u.id">{{ u.name }}</option>
                        </select>
                        <div class="mt-2 text-xs text-gray-500">Ou saisir un nom (membre externe)</div>
                        <input v-model="teamForm.project_manager_name" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Nom du chef de projet" @input="typeExternalName('project_manager_id','project_manager_name')" />
                        <input
                            v-model="teamForm.project_manager_email"
                            type="email"
                            class="mt-2 w-full rounded-md border-gray-300 text-sm"
                            placeholder="Email du chef de projet (pour alertes)"
                            :disabled="!!teamForm.project_manager_id"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase text-gray-500">Agent financier</label>
                        <select v-model="teamForm.financial_agent_id" class="mt-1 w-full rounded-md border-gray-300 text-sm" @change="pickUser('financial_agent_id','financial_agent_name')">
                            <option value="">Aucun</option>
                            <option v-for="u in teamCandidates" :key="`finance-${u.id}`" :value="u.id">{{ u.name }}</option>
                        </select>
                        <div class="mt-2 text-xs text-gray-500">Ou saisir un nom (membre externe)</div>
                        <input v-model="teamForm.financial_agent_name" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Nom de l'agent financier" @input="typeExternalName('financial_agent_id','financial_agent_name')" />
                        <input
                            v-model="teamForm.financial_agent_email"
                            type="email"
                            class="mt-2 w-full rounded-md border-gray-300 text-sm"
                            placeholder="Email de l'agent financier (pour alertes)"
                            :disabled="!!teamForm.financial_agent_id"
                        />
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button
                        type="button"
                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="teamForm.processing"
                        @click="saveTeam"
                    >
                        {{ teamForm.processing ? 'Enregistrement...' : 'Enregistrer les membres' }}
                    </button>
                </div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Équipe étendue (Génie civil)</h3>
                    <button
                        v-if="canManageTeam"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                        @click="addExtendedMember"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Ajouter
                    </button>
                </div>

                <div v-if="!extendedTeam.members.length" class="text-sm text-gray-500">
                    Aucun membre ajouté. Utilise “Ajouter” pour composer l’équipe (internes ou externes).
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="(m, idx) in extendedTeam.members"
                        v-show="expandedExtendedIdx === idx"
                        :key="m.id ?? `new-${idx}`"
                        class="rounded-lg border border-gray-200 p-3"
                    >
                        <!-- Editor (single open at a time) -->
                        <div class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-medium uppercase text-gray-500">Rôle</label>
                                    <select v-model="m.role_key" class="mt-1 w-full rounded-md border-gray-300 text-sm" @change="onRoleChange(m)">
                                        <option v-for="r in ROLE_CATALOG" :key="r.key" :value="r.key">{{ r.label }}</option>
                                    </select>
                                    <input
                                        v-if="m.role_key === 'autre'"
                                        v-model="m.role_label"
                                        type="text"
                                        class="mt-2 w-full rounded-md border-gray-300 text-sm"
                                        placeholder="Préciser le rôle"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-medium uppercase text-gray-500">Personne</label>
                                    <select
                                        v-model="m.user_id"
                                        class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                        @change="pickExtendedUser(m)"
                                        :disabled="!canManageTeam"
                                    >
                                        <option value="">— Externe / non enregistré —</option>
                                        <option v-for="u in teamCandidates" :key="`ext-${idx}-${u.id}`" :value="u.id">{{ u.name }}</option>
                                    </select>
                                    <input
                                        v-model="m.name"
                                        type="text"
                                        class="mt-2 w-full rounded-md border-gray-300 text-sm"
                                        placeholder="Nom et prénom (si externe)"
                                        @input="typeExtendedName(m)"
                                        :disabled="!canManageTeam"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-medium uppercase text-gray-500">Organisation</label>
                                    <input v-model="m.organization" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Entreprise / structure" :disabled="!canManageTeam" />
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium uppercase text-gray-500">Téléphone</label>
                                        <input v-model="m.phone" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="+225 ..." :disabled="!canManageTeam" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium uppercase text-gray-500">Email</label>
                                        <input v-model="m.email" type="email" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="nom@domaine.com" :disabled="!canManageTeam" />
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <div class="text-xs text-gray-500">#{{ idx + 1 }}</div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border border-gray-200 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                        @click="expandedExtendedIdx = null"
                                    >
                                        Fermer
                                    </button>
                                    <button
                                        v-if="canManageTeam"
                                        type="button"
                                        class="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                        @click="m.id ? removeExtendedMemberById(m.id) : removeExtendedMember(idx)"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button
                            v-if="canManageTeam"
                            type="button"
                            class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                            :disabled="extendedTeam.processing"
                            @click="saveExtendedTeam"
                        >
                            {{ extendedTeam.processing ? 'Enregistrement...' : "Enregistrer l'équipe étendue" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
