<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    alerts: { type: Object, required: true },
    filters: { type: Object, required: true },
    stats: { type: Object, required: true },
    types: { type: Object, required: true },
    severities: { type: Object, required: true },
});

const page = usePage();
const isSupervisor = computed(() => page.props.auth?.roles?.some((role) => ['admin', 'directeur'].includes(role)));
const canDeleteAlert = isSupervisor;
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);


const status = ref(props.filters.status);
const severity = ref(props.filters.severity);
const type = ref(props.filters.type);

const applyFilters = () => {
    router.get(route('alertes.index'), {
        status: status.value || undefined,
        severity: severity.value || undefined,
        type: type.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const resetFilters = () => {
    status.value = '';
    severity.value = '';
    type.value = '';
    router.get(route('alertes.index'), {}, { preserveState: false });
};

const regenerate = () => {
    router.post(route('alertes.generate'), {}, { preserveScroll: true });
};

const resolveForm = useForm({ note: '' });
const activeAlert = ref(null);

const openResolve = (alert) => {
    activeAlert.value = alert;
    resolveForm.reset();
};

const submitResolve = () => {
    if (!activeAlert.value) return;
    resolveForm.post(route('alertes.resolve', activeAlert.value.id), {
        onSuccess: () => { activeAlert.value = null; },
        preserveScroll: true,
    });
};

const commentBody = ref({});
const commentProcessing = ref(null);

const submitComment = (alert) => {
    const body = (commentBody.value[alert.id] || '').trim();
    if (!body) return;
    commentProcessing.value = alert.id;
    router.post(route('alertes.comments.store', alert.id), { body }, {
        preserveScroll: true,
        onSuccess: () => { commentBody.value[alert.id] = ''; },
        onFinish: () => { commentProcessing.value = null; },
    });
};

const deleteComment = (alert, comment) => {
    if (!confirm('Supprimer cette observation ?')) return;
    router.delete(route('alertes.comments.destroy', [alert.id, comment.id]), { preserveScroll: true });
};

const canDeleteComment = (comment) => isSupervisor.value || comment.user_id === currentUserId.value;

const severityStyle = {
    critical: 'border-red-300 bg-red-50 text-red-800',
    warning: 'border-amber-300 bg-amber-50 text-amber-800',
    info: 'border-blue-300 bg-blue-50 text-blue-800',
};
const severityBadge = {
    critical: 'bg-red-600 text-white',
    warning: 'bg-amber-500 text-white',
    info: 'bg-blue-500 text-white',
};
const severityLevelLabel = {
    critical: 'Danger',
    warning: 'Warning',
    info: 'Info',
};

const deleteAlert = (alert) => {
    if (!confirm(`Supprimer l'alerte « ${alert.title} » ?`)) return;
    router.delete(route('alertes.destroy', alert.id), { preserveScroll: true });
};

const formatDate = (iso) => iso ? new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '';
</script>

<template>
    <Head title="Alertes" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Accueil', href: route('dashboard') }, { label: 'Alertes' }]">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Centre des alertes</h2>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                    @click="regenerate"
                >
                    Analyser maintenant
                </button>
            </div>
        </template>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Ouvertes</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ stats.open }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-red-200">
                <p class="text-xs font-medium uppercase tracking-wide text-red-600">Critiques</p>
                <p class="mt-1 text-3xl font-bold text-red-700">{{ stats.critical }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-amber-200">
                <p class="text-xs font-medium uppercase tracking-wide text-amber-600">Attention</p>
                <p class="mt-1 text-3xl font-bold text-amber-700">{{ stats.warning }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-emerald-200">
                <p class="text-xs font-medium uppercase tracking-wide text-emerald-600">Résolues (30j)</p>
                <p class="mt-1 text-3xl font-bold text-emerald-700">{{ stats.resolved_last_30 }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="mt-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <div>
                <label class="block text-xs font-medium text-gray-600">Statut</label>
                <select v-model="status" @change="applyFilters" class="mt-1 rounded-md border-gray-300 text-sm">
                    <option value="">Tous</option>
                    <option value="open">Ouvertes</option>
                    <option value="resolved">Résolues</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Sévérité</label>
                <select v-model="severity" @change="applyFilters" class="mt-1 rounded-md border-gray-300 text-sm">
                    <option value="">Toutes</option>
                    <option v-for="(label, key) in severities" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Type</label>
                <select v-model="type" @change="applyFilters" class="mt-1 rounded-md border-gray-300 text-sm">
                    <option value="">Tous</option>
                    <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <button type="button" class="ml-auto text-sm text-gray-500 hover:text-gray-800 hover:underline" @click="resetFilters">
                Réinitialiser
            </button>
        </div>

        <!-- List -->
        <div class="mt-4 space-y-3">
            <div v-if="!alerts.data.length" class="rounded-xl border-2 border-dashed border-gray-200 bg-white p-10 text-center text-gray-500">
                Aucune alerte avec ces critères.
            </div>
            <div
                v-for="a in alerts.data"
                :key="a.id"
                class="rounded-xl border-l-4 bg-white p-4 shadow-sm ring-1 ring-gray-200 transition hover:shadow-md"
                :class="severityStyle[a.severity]"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide" :class="severityBadge[a.severity]">
                                {{ severityLevelLabel[a.severity] ?? a.severity_label }}
                            </span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700">{{ a.type_label }}</span>
                            <span v-if="a.is_resolved" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Résolue</span>
                        </div>
                        <p class="mt-2 text-sm font-semibold text-gray-900">{{ a.title }}</p>
                        <p class="mt-1 text-sm text-gray-700">{{ a.message }}</p>
                        <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500">
                            <span v-if="a.project">
                                Projet : <Link :href="route('dashboard')" class="font-mono font-semibold text-indigo-600 hover:underline">{{ a.project.code }}</Link>
                                — {{ a.project.title }}
                            </span>
                            <span>Détectée le {{ formatDate(a.detected_at) }}</span>
                            <span v-if="a.resolved_at">Résolue le {{ formatDate(a.resolved_at) }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="!a.is_resolved"
                            type="button"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700"
                            @click="openResolve(a)"
                        >
                            Résoudre
                        </button>
                        <button
                            v-if="canDeleteAlert"
                            type="button"
                            class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-red-700"
                            @click="deleteAlert(a)"
                        >
                            Supprimer
                        </button>
                    </div>
                </div>

                <!-- Observations / fil de discussion -->
                <div class="mt-3 border-t border-gray-200/70 pt-3">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Observations<span v-if="a.comments && a.comments.length"> ({{ a.comments.length }})</span>
                    </p>

                    <ul v-if="a.comments && a.comments.length" class="space-y-2">
                        <li v-for="c in a.comments" :key="c.id" class="rounded-lg bg-white/70 px-3 py-2 ring-1 ring-gray-200">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm text-gray-800 whitespace-pre-line">{{ c.body }}</p>
                                <button
                                    v-if="canDeleteComment(c)"
                                    type="button"
                                    class="shrink-0 text-xs text-gray-400 hover:text-red-600"
                                    title="Supprimer l'observation"
                                    @click="deleteComment(a, c)"
                                >
                                    ✕
                                </button>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-500">
                                {{ c.author }} — {{ formatDate(c.created_at) }}
                            </p>
                        </li>
                    </ul>
                    <p v-else class="text-xs italic text-gray-400">Aucune observation pour le moment.</p>

                    <form class="mt-2 flex items-start gap-2" @submit.prevent="submitComment(a)">
                        <textarea
                            v-model="commentBody[a.id]"
                            rows="1"
                            placeholder="Ajouter une observation…"
                            class="min-h-[38px] flex-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <button
                            type="submit"
                            class="shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="commentProcessing === a.id || !(commentBody[a.id] || '').trim()"
                        >
                            Envoyer
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="alerts.links && alerts.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="(link, i) in alerts.links"
                :key="i"
                :href="link.url ?? '#'"
                v-html="link.label"
                class="rounded-md border px-3 py-1 text-sm"
                :class="[link.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50', !link.url ? 'pointer-events-none opacity-40' : '']"
            />
        </div>

        <!-- Modal resolve -->
        <div v-if="activeAlert" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="activeAlert = null">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Résoudre l'alerte</h3>
                <p class="mt-1 text-sm text-gray-600">{{ activeAlert.title }}</p>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Note de résolution (optionnelle)</label>
                    <textarea v-model="resolveForm.note" rows="3" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Cause identifiée, action corrective…" />
                    <p v-if="resolveForm.errors.note" class="mt-1 text-xs text-red-600">{{ resolveForm.errors.note }}</p>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100" @click="activeAlert = null">Annuler</button>
                    <button type="button" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700" :disabled="resolveForm.processing" @click="submitResolve">
                        Confirmer
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
