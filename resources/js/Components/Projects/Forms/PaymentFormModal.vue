<script setup>
import Modal from '@/Components/UI/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectId: { type: [Number, String], required: true },
    payment: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = useForm({
    number: '',
    period: '',
    payment_date: '',
    gross_amount: 0,
    startup_advance_recovery: 0,
    supply_advance_recovery: 0,
    net_paid: 0,
    is_paid: false,
    observations: '',
});

watch(() => props.show, (v) => {
    if (v) {
        if (props.payment) {
            Object.keys(form.data()).forEach((k) => {
                if (props.payment[k] !== undefined && props.payment[k] !== null) form[k] = props.payment[k];
            });
            form.is_paid = !!props.payment.is_paid;
        } else {
            form.reset();
        }
        form.clearErrors();
    }
});

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => emit('close') };
    if (props.payment) {
        form.put(route('projects.payments.update', [props.projectId, props.payment.id]), opts);
    } else {
        form.post(route('projects.payments.store', props.projectId), opts);
    }
};
</script>

<template>
    <Modal :show="show" :title="payment ? `Modifier le décompte ${payment.number}` : 'Nouveau décompte'" size="xl" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">N° de décompte</label>
                    <input v-model="form.number" type="text" maxlength="40" class="w-full rounded-md border-gray-300 text-sm" required placeholder="001" />
                    <p v-if="form.errors.number" class="mt-1 text-xs text-red-600">{{ form.errors.number }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Période (YYYY-MM)</label>
                    <input v-model="form.period" type="month" class="w-full rounded-md border-gray-300 text-sm" />
                    <p v-if="form.errors.period" class="mt-1 text-xs text-red-600">{{ form.errors.period }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Date de paiement</label>
                    <input v-model="form.payment_date" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                    <p v-if="form.errors.payment_date" class="mt-1 text-xs text-red-600">{{ form.errors.payment_date }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Montant brut HT — FCFA</label>
                    <input v-model.number="form.gross_amount" type="number" min="0" step="any" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.gross_amount" class="mt-1 text-xs text-red-600">{{ form.errors.gross_amount }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Net payé — FCFA</label>
                    <input v-model.number="form.net_paid" type="number" min="0" step="any" class="w-full rounded-md border-gray-300 text-sm" required />
                    <p v-if="form.errors.net_paid" class="mt-1 text-xs text-red-600">{{ form.errors.net_paid }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Remb. avance de démarrage — FCFA</label>
                    <input v-model.number="form.startup_advance_recovery" type="number" min="0" step="any" class="w-full rounded-md border-gray-300 text-sm" />
                    <p v-if="form.errors.startup_advance_recovery" class="mt-1 text-xs text-red-600">{{ form.errors.startup_advance_recovery }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Remb. avance d'approvisionnement — FCFA</label>
                    <input v-model.number="form.supply_advance_recovery" type="number" min="0" step="any" class="w-full rounded-md border-gray-300 text-sm" />
                    <p v-if="form.errors.supply_advance_recovery" class="mt-1 text-xs text-red-600">{{ form.errors.supply_advance_recovery }}</p>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="form.is_paid" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                Décompte payé
            </label>

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
                {{ payment ? 'Mettre à jour' : 'Enregistrer' }}
            </button>
        </template>
    </Modal>
</template>
