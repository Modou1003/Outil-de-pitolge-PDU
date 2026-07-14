<script setup>
import Modal from '@/Components/UI/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: [Number, String], required: true },
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
});

watch(() => props.show, (v) => {
    if (v) {
        form.reset();
        form.clearErrors();
    }
});

const submit = () => {
    form.post(route('projects.building-works.store', props.projectId), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Modal :show="show" title="Ajouter un ouvrage" size="md" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Nom de l'ouvrage</label>
                <input
                    v-model="form.name"
                    type="text"
                    class="w-full rounded-md border-gray-300 text-sm"
                    placeholder="ex: Fondations, Structure, etc."
                    required
                    autofocus
                />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Description (optionnel)</label>
                <textarea
                    v-model="form.description"
                    rows="2"
                    class="w-full rounded-md border-gray-300 text-sm"
                    placeholder="Détails de cet ouvrage..."
                />
                <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Statut</label>
                <select v-model="form.status" class="w-full rounded-md border-gray-300 text-sm" required>
                    <option value="not_started">Non démarré</option>
                    <option value="in_progress">En cours</option>
                    <option value="on_hold">En pause</option>
                    <option value="completed">Terminé</option>
                    <option value="cancelled">Annulé</option>
                </select>
                <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
            </div>
        </form>

        <template #footer>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    @click="emit('close')"
                >
                    Annuler
                </button>
                <button
                    type="button"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    @click="submit"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Création...' : 'Créer' }}
                </button>
            </div>
        </template>
    </Modal>
</template>
