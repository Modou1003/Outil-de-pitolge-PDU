<script setup>
import Modal from '@/Components/UI/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { watch, computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: [Number, String], required: true },
    nextLotNumber: { type: Number, default: 1 },
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    code: '',
});

const generatedCode = computed(() => {
    if (!props.nextLotNumber) return '';
    return `L${String(props.nextLotNumber).padStart(2, '0')}`;
});

watch(() => props.show, (v) => {
    if (v) {
        form.reset();
        form.code = generatedCode.value;
        form.clearErrors();
    }
});

const submit = () => {
    form.post(route('projects.lots.store', props.projectId), {
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
                    placeholder="ex: Fondations, Toiture, etc."
                    required
                    autofocus
                />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Code (auto-généré)</label>
                <input
                    v-model="form.code"
                    type="text"
                    maxlength="32"
                    class="w-full rounded-md border-gray-300 bg-gray-50 text-sm"
                    readonly
                />
                <p class="mt-1 text-[11px] text-gray-500">Code généré automatiquement</p>
                <p v-if="form.errors.code" class="mt-1 text-xs text-red-600">{{ form.errors.code }}</p>
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
