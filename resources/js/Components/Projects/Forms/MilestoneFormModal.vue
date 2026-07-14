<script setup>
import Modal from '@/Components/UI/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: [Number, String], required: true },
    milestone: { type: Object, default: null },
    lots: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const form = useForm({
    building_work_id: null,
    project_lot_id: null,
    name: '',
    description: '',
    planned_date: '',
    actual_date: '',
    status: 'pending',
    is_critical: false,
    observations: '',
    sort_order: 0,
});

watch(() => props.show, (v) => {
    if (v) {
        if (props.milestone) {
            Object.keys(form.data()).forEach((k) => {
                if (props.milestone[k] !== undefined && props.milestone[k] !== null) form[k] = props.milestone[k];
            });
            form.is_critical = !!props.milestone.is_critical;
        } else {
            form.reset();
            form.building_work_id = null;
        }
        form.clearErrors();
    }
});

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => emit('close') };
    if (props.milestone) {
        form.put(route('projects.milestones.update', [props.projectId, props.milestone.id]), opts);
    } else {
        form.post(route('projects.milestones.store', props.projectId), opts);
    }
};
</script>

<template>
    <Modal :show="show" :title="milestone ? 'Modifier le jalon' : 'Nouveau jalon'" size="lg" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Lot associé (optionnel)</label>
                <select v-model="form.project_lot_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option :value="null">Aucun lot spécifique</option>
                    <option v-for="lot in lots" :key="lot.id" :value="lot.id">{{ lot.code }} — {{ lot.name }}</option>
                </select>
                <p v-if="form.errors.project_lot_id" class="mt-1 text-xs text-red-600">{{ form.errors.project_lot_id }}</p>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Nom du jalon</label>
                <input v-model="form.name" type="text" class="w-full rounded-md border-gray-300 text-sm" required placeholder="Ex. Réception provisoire" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Description</label>
                <textarea v-model="form.description" rows="2" class="w-full rounded-md border-gray-300 text-sm" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Date prévue</label>
                    <input v-model="form.planned_date" type="date" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.planned_date" class="mt-1 text-xs text-red-600">{{ form.errors.planned_date }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Date réelle</label>
                    <input v-model="form.actual_date" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                    <p v-if="form.errors.actual_date" class="mt-1 text-xs text-red-600">{{ form.errors.actual_date }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Statut</label>
                    <select v-model="form.status" class="w-full rounded-md border-gray-300 text-sm" required>
                        <option value="pending">En attente</option>
                        <option value="reached">Atteint</option>
                        <option value="missed">Manqué</option>
                        <option value="cancelled">Annulé</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.is_critical" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        Jalon critique (chemin critique)
                    </label>
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
                {{ milestone ? 'Mettre à jour' : 'Créer le jalon' }}
            </button>
        </template>
    </Modal>
</template>
