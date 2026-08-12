<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminTabs from '@/Components/Admin/AdminTabs.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    groups: { type: Array, required: true },
    modified_count: { type: Number, default: 0 },
});

// Un formulaire unique portant toutes les valeurs, indexées par clé de seuil.
const initial = {};
props.groups.forEach((g) => g.fields.forEach((f) => { initial[f.key] = f.value; }));
const form = useForm(initial);

const submit = () => form.put(route('admin.seuils.update'), { preserveScroll: true });

const reset = () => {
    if (!confirm('Rétablir tous les seuils à leurs valeurs par défaut ?')) return;
    router.delete(route('admin.seuils.reset'), { preserveScroll: true });
};

// Un seuil s'écarte-t-il de la valeur d'origine du catalogue ?
const isChanged = (f) => Number(form[f.key]) !== Number(f.default);

const changedCount = computed(() =>
    props.groups.reduce((n, g) => n + g.fields.filter(isChanged).length, 0)
);

const breadcrumbs = [
    { label: 'Accueil', href: route('dashboard') },
    { label: 'Administration', href: route('admin.users.index') },
    { label: 'Seuils' },
];
</script>

<template>
    <Head title="Seuils des indicateurs" />

    <AuthenticatedLayout :breadcrumbs="breadcrumbs">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Seuils des alertes et indicateurs</h2>
        </template>

        <div class="space-y-4">
            <AdminTabs />

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm text-gray-600">
                    Ces valeurs déterminent à partir de quand une alerte s'ouvre et à partir de quand
                    un indicateur change de couleur. Elles s'appliquent à l'ensemble du portefeuille et
                    prennent effet dès la prochaine détection.
                </p>
                <p v-if="changedCount" class="mt-2 text-sm font-medium text-amber-700">
                    {{ changedCount }} seuil(s) s'écarte(nt) de la valeur d'origine.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div v-for="g in groups" :key="g.name" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="border-b border-gray-100 bg-gray-50 px-5 py-2.5">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">{{ g.name }}</h3>
                    </div>

                    <div class="divide-y divide-gray-50">
                        <div v-for="f in g.fields" :key="f.key" class="flex flex-col gap-3 px-5 py-3 sm:flex-row sm:items-center">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ f.label }}
                                    <span v-if="isChanged(f)" class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700">modifié</span>
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ f.help }}</p>
                                <p v-if="f.updated_by" class="mt-0.5 text-[11px] text-gray-400">
                                    Dernière modification par {{ f.updated_by }}
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <input
                                    v-model.number="form[f.key]"
                                    type="number"
                                    :step="f.step"
                                    :min="f.min"
                                    :max="f.max"
                                    class="w-28 rounded-md border-gray-300 text-right text-sm"
                                />
                                <span class="w-12 text-xs text-gray-500">{{ f.unit }}</span>
                                <span class="hidden w-24 text-right text-[11px] text-gray-400 sm:inline">
                                    défaut {{ f.default }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="Object.keys(form.errors).length" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
                    <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-5 py-3 shadow-sm ring-1 ring-gray-200">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                        :disabled="!modified_count"
                        :class="{ 'opacity-40': !modified_count }"
                        @click="reset"
                    >
                        Rétablir les valeurs par défaut
                    </button>

                    <button
                        type="submit"
                        class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Enregistrement…' : 'Enregistrer les seuils' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
