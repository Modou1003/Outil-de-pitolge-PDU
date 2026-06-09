<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useAuth } from '@/Composables/useAuth';
import Modal from '@/Components/UI/Modal.vue';

const props = defineProps({
    project: { type: Object, required: true },
    documents: { type: Array, default: () => [] },
    categories: { type: Object, default: () => ({}) },
});

const { hasRole, user } = useAuth();
const canUpload = computed(() => hasRole(['admin', 'directeur', 'chef_projet', 'agent_financier']));
const canDelete = (doc) => hasRole(['admin', 'directeur']) || doc.uploader?.id === user.value?.id;

const activeCategory = ref('all');
const showModal = ref(false);
const dragOver = ref(false);

const form = useForm({
    file: null,
    title: '',
    description: '',
    category: 'etude',
    visibility: 'internal',
});

const filtered = computed(() => {
    if (activeCategory.value === 'all') return props.documents;
    return props.documents.filter((d) => d.category === activeCategory.value);
});

const countByCategory = (key) =>
    key === 'all' ? props.documents.length : props.documents.filter((d) => d.category === key).length;

const categoryOptions = computed(() => [
    { key: 'all', label: 'Tous' },
    ...Object.entries(props.categories).map(([key, label]) => ({ key, label })),
]);

const categoryBadge = {
    etude: 'bg-indigo-100 text-indigo-700',
    contrat: 'bg-purple-100 text-purple-700',
    photo: 'bg-pink-100 text-pink-700',
    pv: 'bg-emerald-100 text-emerald-700',
    rapport: 'bg-amber-100 text-amber-700',
    autre: 'bg-gray-100 text-gray-700',
};

const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const openUpload = () => {
    form.reset();
    form.category = 'etude';
    form.visibility = 'internal';
    form.clearErrors();
    showModal.value = true;
};

const onFilePick = (e) => {
    const f = e.target.files?.[0];
    if (f) {
        form.file = f;
        if (!form.title) form.title = f.name.replace(/\.[^.]+$/, '');
    }
};

const onDrop = (e) => {
    e.preventDefault();
    dragOver.value = false;
    const f = e.dataTransfer.files?.[0];
    if (f) {
        form.file = f;
        if (!form.title) form.title = f.name.replace(/\.[^.]+$/, '');
        showModal.value = true;
    }
};

const submit = () => {
    form.post(route('projects.documents.store', props.project.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; form.reset(); },
    });
};

const download = (doc) => {
    window.open(route('projects.documents.download', [props.project.id, doc.id]), '_blank');
};

const remove = (doc) => {
    if (!confirm(`Supprimer « ${doc.title} » ?`)) return;
    router.delete(route('projects.documents.destroy', [props.project.id, doc.id]), { preserveScroll: true });
};

const iconPath = (doc) => {
    if (doc.is_image) return 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z';
    if (doc.is_pdf) return 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
    return 'M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9l-7-7zM13 2v7h7';
};
</script>

<template>
    <div class="space-y-4">
        <!-- Zone drag & drop -->
        <div
            class="rounded-xl border-2 border-dashed bg-white p-6 text-center transition"
            :class="dragOver ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300'"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop="canUpload && onDrop($event)"
        >
            <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <p class="mt-2 text-sm text-gray-700">
                <span v-if="canUpload">Glissez un fichier ici ou</span>
                <span v-else>Vous n'êtes pas autorisé à téléverser des documents.</span>
                <button v-if="canUpload" type="button" class="ml-1 font-medium text-indigo-600 hover:text-indigo-700" @click="openUpload">parcourez votre ordinateur</button>
            </p>
            <p class="mt-1 text-[11px] text-gray-500">PDF, Office, images, ZIP — max 20 Mo</p>
        </div>

        <!-- Filtres catégorie -->
        <div class="flex flex-wrap gap-2">
            <button
                v-for="c in categoryOptions"
                :key="c.key"
                type="button"
                class="rounded-full px-3 py-1 text-xs font-medium transition"
                :class="activeCategory === c.key
                    ? 'bg-indigo-600 text-white shadow-sm'
                    : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'"
                @click="activeCategory = c.key"
            >
                {{ c.label }}
                <span class="ml-1 text-[10px] opacity-70">{{ countByCategory(c.key) }}</span>
            </button>
        </div>

        <!-- Liste -->
        <div v-if="!filtered.length" class="rounded-xl bg-white p-10 text-center text-sm text-gray-500 ring-1 ring-gray-200">
            Aucun document dans cette catégorie.
        </div>
        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="doc in filtered" :key="doc.id" class="group flex flex-col rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 transition hover:shadow-md">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 rounded-lg bg-indigo-50 p-2 text-indigo-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(doc)" /></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ doc.title }}</p>
                        <p class="truncate text-[11px] text-gray-500">{{ doc.file_name }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="categoryBadge[doc.category] || 'bg-gray-100 text-gray-700'">
                                {{ categories[doc.category] || doc.category }}
                            </span>
                            <span class="text-[10px] text-gray-500">{{ doc.file_size_human }}</span>
                        </div>
                    </div>
                </div>
                <p v-if="doc.description" class="mt-2 line-clamp-2 text-xs text-gray-600">{{ doc.description }}</p>
                <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-2 text-[11px] text-gray-500">
                    <span class="truncate">{{ doc.uploader?.name || '—' }} · {{ formatDate(doc.uploaded_at) }}</span>
                    <div class="flex gap-1">
                        <button class="rounded p-1 text-gray-500 hover:bg-indigo-50 hover:text-indigo-700" title="Télécharger" @click="download(doc)">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" /></svg>
                        </button>
                        <button v-if="canDelete(doc)" class="rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-700" title="Supprimer" @click="remove(doc)">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Upload -->
        <Modal :show="showModal" title="Téléverser un document" size="lg" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Fichier</label>
                    <input type="file" class="w-full rounded-md border-gray-300 text-sm" @change="onFilePick" required />
                    <p v-if="form.file" class="mt-1 text-[11px] text-gray-500">{{ form.file.name }} · {{ (form.file.size / 1024 / 1024).toFixed(2) }} Mo</p>
                    <p v-if="form.errors.file" class="mt-1 text-xs text-red-600">{{ form.errors.file }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Titre</label>
                    <input v-model="form.title" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Ex. Plan de masse — version finale" />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Catégorie</label>
                        <select v-model="form.category" class="w-full rounded-md border-gray-300 text-sm" required>
                            <option v-for="(lbl, key) in categories" :key="key" :value="key">{{ lbl }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Visibilité</label>
                        <select v-model="form.visibility" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="internal">Interne</option>
                            <option value="public">Public</option>
                            <option value="confidential">Confidentiel</option>
                            <option value="restricted">Restreint</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Description</label>
                    <textarea v-model="form.description" rows="2" class="w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div v-if="form.progress" class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full bg-indigo-600 transition-all" :style="{ width: `${form.progress.percentage}%` }" />
                </div>
            </form>
            <template #footer>
                <button type="button" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100" @click="showModal = false">Annuler</button>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="form.processing || !form.file"
                    @click="submit"
                >
                    Téléverser
                </button>
            </template>
        </Modal>
    </div>
</template>
