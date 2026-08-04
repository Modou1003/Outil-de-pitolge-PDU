<script setup>
import Modal from '@/Components/UI/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: [Number, String], required: true },
    amendment: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = useForm({
    number: '',
    object: '',
    signed_date: '',
    amount: '',
    duration_days: '',
    observations: '',
});

// Un effet non renseigné vaut 0 en base : on l'affiche vide pour que la
// distinction « montant seul / délai seul » reste lisible à la réouverture.
const blankIfZero = (v) => (Number(v) === 0 ? '' : v);

watch(() => props.show, (v) => {
    if (v) {
        if (props.amendment) {
            Object.keys(form.data()).forEach((k) => {
                if (props.amendment[k] !== undefined && props.amendment[k] !== null) form[k] = props.amendment[k];
            });
            form.amount = blankIfZero(props.amendment.amount);
            form.duration_days = blankIfZero(props.amendment.duration_days);
        } else {
            form.reset();
        }
        form.clearErrors();
    }
});

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => emit('close') };
    if (props.amendment) {
        form.put(route('projects.amendments.update', [props.projectId, props.amendment.id]), opts);
    } else {
        form.post(route('projects.amendments.store', props.projectId), opts);
    }
};
</script>

<template>
    <Modal :show="show" :title="amendment ? `Modifier l'avenant ${amendment.number}` : 'Nouvel avenant'" size="xl" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">N° d'avenant</label>
                    <input v-model="form.number" type="text" maxlength="40" class="w-full rounded-md border-gray-300 text-sm" required placeholder="01" />
                    <p v-if="form.errors.number" class="mt-1 text-xs text-red-600">{{ form.errors.number }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Date de signature</label>
                    <input v-model="form.signed_date" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                    <p v-if="form.errors.signed_date" class="mt-1 text-xs text-red-600">{{ form.errors.signed_date }}</p>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Objet de l'avenant</label>
                <input v-model="form.object" type="text" maxlength="255" class="w-full rounded-md border-gray-300 text-sm" placeholder="Travaux supplémentaires — extension du bloc B" />
                <p v-if="form.errors.object" class="mt-1 text-xs text-red-600">{{ form.errors.object }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">
                        Montant — FCFA <span class="font-normal text-gray-400">(optionnel)</span>
                    </label>
                    <input v-model="form.amount" type="number" step="any" placeholder="0" class="w-full rounded-md border-gray-300 text-sm" />
                    <p class="mt-1 text-xs text-gray-500">
                        Positif = plus-value, négatif = moins-value. Le marché initial reste inchangé.
                    </p>
                    <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">
                        Prolongation de délai — jours <span class="font-normal text-gray-400">(optionnel)</span>
                    </label>
                    <input v-model="form.duration_days" type="number" step="1" placeholder="0" class="w-full rounded-md border-gray-300 text-sm" />
                    <p class="mt-1 text-xs text-gray-500">
                        Positif = prolongation, négatif = réduction. La date de fin initiale reste inchangée.
                    </p>
                    <p v-if="form.errors.duration_days" class="mt-1 text-xs text-red-600">{{ form.errors.duration_days }}</p>
                </div>
            </div>

            <p class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
                Un avenant de délai seul, de montant seul, ou les deux — laisse vide ce qui ne s'applique pas.
            </p>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">Observations (optionnel)</label>
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
                {{ amendment ? 'Mettre à jour' : 'Enregistrer' }}
            </button>
        </template>
    </Modal>
</template>
