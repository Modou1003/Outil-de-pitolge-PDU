<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    project: { type: Object, required: true },
    work: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    description: '',
    duration_days: '',
    planned_start_date: '',
    planned_end_date: '',
    actual_start_date: '',
    actual_end_date: '',
});

const isOpen = computed(() => props.show);
const isEdit = computed(() => !!props.work);

const champsCalendrier = ['duration_days', 'planned_start_date', 'planned_end_date', 'actual_start_date', 'actual_end_date'];

watch(() => props.show, (v) => {
    if (v) {
        if (props.work) {
            form.name = props.work.name ?? '';
            form.description = props.work.description ?? '';
            champsCalendrier.forEach((c) => { form[c] = props.work[c] ?? ''; });
        } else {
            form.reset();
        }
        form.clearErrors();
    }
});

// Retard au démarrage recalculé à la saisie, pour que l'utilisateur le voie
// avant même d'enregistrer.
const retardDemarrage = computed(() => {
    if (!form.planned_start_date) return null;
    const prevu = new Date(form.planned_start_date);
    const reference = form.actual_start_date ? new Date(form.actual_start_date) : new Date();
    const jours = Math.round((reference - prevu) / 86400000);

    return jours > 0 ? jours : 0;
});

const close = () => {
    form.reset();
    emit('close');
};

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: close };
    if (props.work) {
        form.put(route('projects.building-works.update', [props.project.id, props.work.id]), opts);
    } else {
        form.post(route('projects.building-works.store', props.project.id), opts);
    }
};
</script>

<template>
    <Modal :show="isOpen" @close="close">
        <div class="rounded-lg bg-white p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">{{ isEdit ? "Modifier l'ouvrage" : "Ajouter un ouvrage" }}</h3>
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
                        autofocus
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Description (optionnel)</label>
                    <textarea
                        v-model="form.description"
                        placeholder="Détails de cet ouvrage..."
                        rows="3"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none"
                    />
                    <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-4">
                    <h4 class="text-sm font-semibold text-gray-900">Calendrier contractuel</h4>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Dates du planning du marché. Elles permettent de mesurer le retard au démarrage.
                    </p>

                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-xs font-medium text-gray-700">Durée contractuelle (jours)</label>
                            <input
                                v-model="form.duration_days"
                                type="number"
                                min="1"
                                placeholder="ex: 348"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none"
                            />
                            <p v-if="form.errors.duration_days" class="mt-1 text-xs text-red-600">{{ form.errors.duration_days }}</p>
                        </div>

                        <div class="col-span-2 sm:col-span-1"></div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700">Début prévu</label>
                            <input
                                v-model="form.planned_start_date"
                                type="date"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none"
                            />
                            <p v-if="form.errors.planned_start_date" class="mt-1 text-xs text-red-600">{{ form.errors.planned_start_date }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700">Fin prévue</label>
                            <input
                                v-model="form.planned_end_date"
                                type="date"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none"
                            />
                            <p v-if="form.errors.planned_end_date" class="mt-1 text-xs text-red-600">{{ form.errors.planned_end_date }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700">Début réel</label>
                            <input
                                v-model="form.actual_start_date"
                                type="date"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none"
                            />
                            <p v-if="form.errors.actual_start_date" class="mt-1 text-xs text-red-600">{{ form.errors.actual_start_date }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700">Fin réelle</label>
                            <input
                                v-model="form.actual_end_date"
                                type="date"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none"
                            />
                            <p v-if="form.errors.actual_end_date" class="mt-1 text-xs text-red-600">{{ form.errors.actual_end_date }}</p>
                        </div>
                    </div>

                    <p
                        v-if="retardDemarrage !== null"
                        class="mt-3 rounded-md px-3 py-2 text-xs font-medium"
                        :class="retardDemarrage > 90 ? 'bg-red-50 text-red-700' : (retardDemarrage > 15 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700')"
                    >
                        <template v-if="retardDemarrage === 0">Démarrage à l'heure ou anticipé.</template>
                        <template v-else>
                            Retard au démarrage : {{ retardDemarrage }} jour{{ retardDemarrage > 1 ? 's' : '' }}
                            <span v-if="!form.actual_start_date"> (ouvrage non démarré à ce jour)</span>
                        </template>
                    </p>
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
                        {{ form.processing ? 'Enregistrement...' : (isEdit ? 'Enregistrer' : 'Créer') }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
