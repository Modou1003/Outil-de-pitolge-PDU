<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    project: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    description: '',
    status: 'not_started',
});

const isOpen = computed(() => props.show);

const close = () => {
    form.reset();
    emit('close');
};

const submit = () => {
    form.post(route('projects.building-works.store', props.project.id), {
        preserveScroll: true,
        onSuccess: close,
    });
};

const statuses = [
    { value: 'not_started', label: 'Non commencé' },
    { value: 'in_progress', label: 'En cours' },
    { value: 'on_hold', label: 'En pause' },
    { value: 'completed', label: 'Terminé' },
    { value: 'cancelled', label: 'Annulé' },
];
</script>

<template>
    <Modal :show="isOpen" @close="close">
        <div class="rounded-lg bg-white p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Ajouter un ouvrage</h3>
                <button @click="close" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <form @submit.prevent="submit" class="mt-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nom de l'ouvrage</label>
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="ex: Fondations, Structure, etc."
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Description (optionnel)</label>
                    <textarea
                        v-model="form.description"
                        placeholder="Détails de cet ouvrage..."
                        rows="4"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none"
                    />
                    <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Statut</label>
                    <select
                        v-model="form.status"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none"
                    >
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="close"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Création...' : 'Créer' }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
