<script setup>
import Modal from '@/Components/UI/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: [Number, String], required: true },
    lot: { type: Object, default: null },
    buildingWorkId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close']);

const form = useForm({
    building_work_id: null,
    code: '',
    name: '',
    description: '',
    weight_percentage: 0,
    planned_start_date: '',
    planned_end_date: '',
    actual_start_date: '',
    actual_end_date: '',
    progress_percentage: 0,
    status: 'not_started',
    observations: '',
    sort_order: 0,
});

watch(() => props.show, (v) => {
    if (v) {
        if (props.lot) {
            Object.keys(form.data()).forEach((k) => {
                if (props.lot[k] !== undefined && props.lot[k] !== null) form[k] = props.lot[k];
            });
        } else {
            form.reset();
        }
        // Rattache le lot à l'ouvrage courant.
        form.building_work_id = props.lot?.building_work_id ?? props.buildingWorkId ?? null;
        form.clearErrors();
    }
});

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => emit('close') };
    if (props.lot) {
        form.put(route('projects.lots.update', [props.projectId, props.lot.id]), opts);
    } else {
        form.post(route('projects.lots.store', props.projectId), opts);
    }
};
</script>

<template>
    <Modal :show="show" :title="lot ? `Modifier le lot ${lot.code}` : 'Nouveau lot'" size="xl" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Code</label>
                    <input v-model="form.code" type="text" maxlength="32" class="w-full rounded-md border-gray-300 text-sm" required placeholder="L01" />
                    <p v-if="form.errors.code" class="mt-1 text-xs text-red-600">{{ form.errors.code }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-700">Nom du lot</label>
                    <input v-model="form.name" type="text" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Description</label>
                <textarea v-model="form.description" rows="2" class="w-full rounded-md border-gray-300 text-sm" />
                <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Pondération (%)</label>
                    <input v-model.number="form.weight_percentage" type="number" min="0" max="100" step="0.1" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.weight_percentage" class="mt-1 text-xs text-red-600">{{ form.errors.weight_percentage }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Avancement (%)</label>
                    <input v-model.number="form.progress_percentage" type="number" min="0" max="100" step="0.1" class="w-full rounded-md border-gray-300 text-sm" />
                    <p v-if="form.errors.progress_percentage" class="mt-1 text-xs text-red-600">{{ form.errors.progress_percentage }}</p>
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
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Début prévu</label>
                    <input v-model="form.planned_start_date" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Fin prévue</label>
                    <input v-model="form.planned_end_date" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                    <p v-if="form.errors.planned_end_date" class="mt-1 text-xs text-red-600">{{ form.errors.planned_end_date }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Début réel</label>
                    <input v-model="form.actual_start_date" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Fin réelle</label>
                    <input v-model="form.actual_end_date" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                    <p v-if="form.errors.actual_end_date" class="mt-1 text-xs text-red-600">{{ form.errors.actual_end_date }}</p>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Observations</label>
                <textarea v-model="form.observations" rows="2" class="w-full rounded-md border-gray-300 text-sm" />
            </div>
        </form>

        <template #footer>
            <button type="button" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100" @click="emit('close')">Annuler</button>
            <button
                type="button"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                :disabled="form.processing"
                @click="submit"
            >
                {{ lot ? 'Mettre à jour' : 'Créer le lot' }}
            </button>
        </template>
    </Modal>
</template>
