<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/Composables/useAuth';
import AmendmentFormModal from '@/Components/Projects/Forms/AmendmentFormModal.vue';

const props = defineProps({
    project: { type: Object, required: true },
    amendments: { type: Array, default: () => [] },
});

const { hasPermission } = useAuth();
const canWrite = computed(() => hasPermission('manage_finances'));

const showModal = ref(false);
const editing = ref(null);
const openCreate = () => { editing.value = null; showModal.value = true; };
const openEdit = (a) => { editing.value = a; showModal.value = true; };
const remove = (a) => {
    if (!confirm(`Supprimer l'avenant ${a.number} ?`)) return;
    router.delete(route('projects.amendments.destroy', [props.project.id, a.id]), { preserveScroll: true });
};

// --- Volet montant ---
const initial = computed(() => Number(props.project.budget_allocated) || 0);
const totalAmount = computed(() => props.amendments.reduce((s, a) => s + (Number(a.amount) || 0), 0));
const revised = computed(() => initial.value + totalAmount.value);
const variationRate = computed(() => (initial.value > 0 ? (totalAmount.value / initial.value) * 100 : null));

// --- Volet délai ---
const totalDays = computed(() => props.amendments.reduce((s, a) => s + (Number(a.duration_days) || 0), 0));
const initialEnd = computed(() => props.project.planned_end_date ?? props.project.planned_completion_date ?? props.project.end_date ?? null);
const revisedEnd = computed(() => {
    if (props.project.planned_end_date_revised) return props.project.planned_end_date_revised;
    if (!initialEnd.value) return null;
    const d = new Date(initialEnd.value);
    d.setDate(d.getDate() + totalDays.value);
    return d.toISOString().slice(0, 10);
});

const fmt = (v) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Number(v) || 0);
const signed = (v) => `${Number(v) > 0 ? '+' : ''}${fmt(v)}`;
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—';
const days = (n) => `${Number(n) > 0 ? '+' : ''}${Number(n) || 0} j`;

// Couleur selon le sens de la variation (hausse = ambre, baisse = bleu, nul = neutre).
const toneClasses = (v) => v > 0
    ? { box: 'bg-amber-50 ring-amber-200', label: 'text-amber-600', value: 'text-amber-700' }
    : (v < 0
        ? { box: 'bg-sky-50 ring-sky-200', label: 'text-sky-600', value: 'text-sky-700' }
        : { box: 'bg-white ring-gray-200', label: 'text-gray-500', value: 'text-gray-900' });

const amountTone = computed(() => toneClasses(totalAmount.value));
const dayTone = computed(() => toneClasses(totalDays.value));
</script>

<template>
    <div class="space-y-4">
        <!-- Montant : initial → avenants → actualisé -->
        <div>
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Montant du marché</h3>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Montant initial</p>
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ fmt(initial) }}</p>
                    <p class="text-[11px] text-gray-500">montant de base, inchangé</p>
                </div>
                <div class="rounded-xl p-4 shadow-sm ring-1" :class="amountTone.box">
                    <p class="text-[11px] uppercase tracking-wide" :class="amountTone.label">Total avenants</p>
                    <p class="mt-1 text-lg font-bold" :class="amountTone.value">{{ signed(totalAmount) }}</p>
                    <p class="text-[11px]" :class="amountTone.label">
                        {{ variationRate !== null ? `${variationRate > 0 ? '+' : ''}${variationRate.toFixed(1)}% du montant initial` : '—' }}
                    </p>
                </div>
                <div class="rounded-xl bg-indigo-50 p-4 shadow-sm ring-1 ring-indigo-200">
                    <p class="text-[11px] uppercase tracking-wide text-indigo-600">Montant actualisé</p>
                    <p class="mt-1 text-lg font-bold text-indigo-900">{{ fmt(revised) }}</p>
                    <p class="text-[11px] text-indigo-600">référence des taux financiers</p>
                </div>
            </div>
        </div>

        <!-- Délai : date initiale → prolongation → date actualisée -->
        <div>
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Délai d'exécution</h3>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500">Date de fin initiale</p>
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ fmtDate(initialEnd) }}</p>
                    <p class="text-[11px] text-gray-500">date contractuelle, inchangée</p>
                </div>
                <div class="rounded-xl p-4 shadow-sm ring-1" :class="dayTone.box">
                    <p class="text-[11px] uppercase tracking-wide" :class="dayTone.label">Prolongation cumulée</p>
                    <p class="mt-1 text-lg font-bold" :class="dayTone.value">{{ days(totalDays) }}</p>
                    <p class="text-[11px]" :class="dayTone.label">
                        {{ totalDays > 0 ? 'délai rallongé par avenant' : (totalDays < 0 ? 'délai raccourci par avenant' : 'aucune prolongation') }}
                    </p>
                </div>
                <div class="rounded-xl bg-indigo-50 p-4 shadow-sm ring-1 ring-indigo-200">
                    <p class="text-[11px] uppercase tracking-wide text-indigo-600">Date de fin actualisée</p>
                    <p class="mt-1 text-lg font-bold text-indigo-900">{{ fmtDate(revisedEnd) }}</p>
                    <p class="text-[11px] text-indigo-600">référence des indicateurs de délai</p>
                </div>
            </div>
        </div>

        <!-- Liste des avenants -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Avenants ({{ amendments.length }})</h3>
                <button
                    v-if="canWrite"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-700"
                    @click="openCreate"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Ajouter un avenant
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">N°</th>
                            <th class="px-4 py-2">Date</th>
                            <th class="px-4 py-2">Objet</th>
                            <th class="px-4 py-2 text-right">Montant</th>
                            <th class="px-4 py-2 text-right">Délai</th>
                            <th v-if="canWrite" class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="a in amendments" :key="a.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ a.number }}</td>
                            <td class="px-4 py-2 text-xs text-gray-600">{{ fmtDate(a.signed_date) }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ a.object || '—' }}</td>
                            <td class="px-4 py-2 text-right font-semibold" :class="a.amount < 0 ? 'text-sky-700' : (a.amount > 0 ? 'text-amber-700' : 'text-gray-400')">
                                {{ a.amount ? signed(a.amount) : '—' }}
                            </td>
                            <td class="px-4 py-2 text-right font-semibold" :class="a.duration_days < 0 ? 'text-sky-700' : (a.duration_days > 0 ? 'text-amber-700' : 'text-gray-400')">
                                {{ a.duration_days ? days(a.duration_days) : '—' }}
                            </td>
                            <td v-if="canWrite" class="px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button class="rounded p-1 text-gray-500 hover:bg-indigo-50 hover:text-indigo-700" title="Modifier" @click="openEdit(a)">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828A2 2 0 0110 16.5L6 17l.5-4a2 2 0 01.586-1.414z" /></svg>
                                    </button>
                                    <button class="rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-700" title="Supprimer" @click="remove(a)">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!amendments.length">
                            <td :colspan="canWrite ? 6 : 5" class="px-4 py-6 text-center text-sm text-gray-500">
                                Aucun avenant. Le marché actualisé est identique au marché initial.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AmendmentFormModal
            :show="showModal"
            :project-id="project.id"
            :amendment="editing"
            @close="showModal = false"
        />
    </div>
</template>
