<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/Composables/useAuth';
import PaymentFormModal from '@/Components/Projects/Forms/PaymentFormModal.vue';

const props = defineProps({
    project: { type: Object, required: true },
    payments: { type: Array, default: () => [] },
    financial: { type: Object, default: null },
});

const { hasPermission } = useAuth();
const canWrite = computed(() => hasPermission('manage_finances'));

const showModal = ref(false);
const editing = ref(null);
const openCreate = () => { editing.value = null; showModal.value = true; };
const openEdit = (p) => { editing.value = p; showModal.value = true; };
const remove = (p) => {
    if (!confirm(`Supprimer le décompte ${p.number} ?`)) return;
    router.delete(route('projects.payments.destroy', [props.project.id, p.id]), { preserveScroll: true });
};

// Réglage des avances contractuelles
const startup = ref(Number(props.project.startup_advance_amount ?? 0));
const supply = ref(Number(props.project.supply_advance_amount ?? 0));
const savingAdvances = ref(false);
const saveAdvances = () => {
    savingAdvances.value = true;
    router.patch(route('projects.advances.update', props.project.id), {
        startup_advance_amount: Number(startup.value) || 0,
        supply_advance_amount: Number(supply.value) || 0,
    }, {
        preserveScroll: true,
        onFinish: () => { savingAdvances.value = false; },
    });
};

const fmt = (v) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Number(v) || 0);
const pct = (v) => v === null || v === undefined ? '—' : `${Number(v).toFixed(1)}%`;
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—';
</script>

<template>
    <div class="space-y-4">
        <!-- Synthèse financière MOA -->
        <div v-if="financial" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">Facturé (HT)</p>
                <p class="mt-1 text-lg font-bold text-gray-900">{{ fmt(financial.invoiced) }}</p>
                <p class="text-[11px] text-gray-500">{{ pct(financial.invoice_rate) }} du marché</p>
            </div>
            <div class="rounded-xl bg-amber-50 p-4 shadow-sm ring-1 ring-amber-200">
                <p class="text-[11px] uppercase tracking-wide text-amber-600">Reste à facturer</p>
                <p class="mt-1 text-lg font-bold text-amber-700">{{ fmt(financial.remaining_to_invoice) }}</p>
                <p class="text-[11px] text-amber-600">{{ pct(financial.remaining_to_invoice_rate) }} du marché</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-[11px] uppercase tracking-wide text-gray-500">Encaissé (travaux + avances)</p>
                <p class="mt-1 text-lg font-bold text-emerald-700">{{ fmt(financial.encashed) }}</p>
                <p class="text-[11px] text-gray-500">{{ pct(financial.encashment_rate) }} du marché</p>
            </div>
            <div
                class="rounded-xl p-4 shadow-sm ring-1"
                :class="financial.advance_remaining > 0 ? 'bg-red-50 ring-red-200' : 'bg-white ring-gray-200'"
                title="Avances versées non encore remboursées — exposition financière du maître d'ouvrage"
            >
                <p class="text-[11px] uppercase tracking-wide" :class="financial.advance_remaining > 0 ? 'text-red-600' : 'text-gray-500'">Exposition sur avances</p>
                <p class="mt-1 text-lg font-bold" :class="financial.advance_remaining > 0 ? 'text-red-700' : 'text-gray-900'">{{ fmt(financial.advance_remaining) }}</p>
                <p class="text-[11px]" :class="financial.advance_remaining > 0 ? 'text-red-600' : 'text-gray-500'">reste à rembourser · {{ pct(financial.advance_remaining_rate) }}</p>
            </div>
        </div>

        <!-- Avances contractuelles -->
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Avance de démarrage (FCFA)</label>
                    <input v-model.number="startup" type="number" min="0" step="any" :disabled="!canWrite" class="w-48 rounded-md border-gray-300 text-sm disabled:bg-gray-100" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">Avance d'approvisionnement (FCFA)</label>
                    <input v-model.number="supply" type="number" min="0" step="any" :disabled="!canWrite" class="w-48 rounded-md border-gray-300 text-sm disabled:bg-gray-100" />
                </div>
                <button
                    v-if="canWrite"
                    type="button"
                    class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="savingAdvances"
                    @click="saveAdvances"
                >
                    Enregistrer les avances
                </button>
                <p v-if="financial" class="text-xs text-gray-500">
                    Remboursé : <span class="font-semibold text-gray-700">{{ fmt(financial.advance_recovered) }}</span>
                    ({{ pct(financial.advance_recovery_rate) }})
                </p>
            </div>
        </div>

        <!-- Décomptes -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Décomptes ({{ payments.length }})</h3>
                <button
                    v-if="canWrite"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-700"
                    @click="openCreate"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Ajouter un décompte
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">N°</th>
                            <th class="px-4 py-2">Période</th>
                            <th class="px-4 py-2">Date</th>
                            <th class="px-4 py-2 text-right">Brut HT</th>
                            <th class="px-4 py-2 text-right">Remb. avances</th>
                            <th class="px-4 py-2 text-right">Net payé</th>
                            <th class="px-4 py-2">Statut</th>
                            <th v-if="canWrite" class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="p in payments" :key="p.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ p.number }}</td>
                            <td class="px-4 py-2 text-xs text-gray-600">{{ p.period || '—' }}</td>
                            <td class="px-4 py-2 text-xs text-gray-600">{{ fmtDate(p.payment_date) }}</td>
                            <td class="px-4 py-2 text-right">{{ fmt(p.gross_amount) }}</td>
                            <td class="px-4 py-2 text-right text-gray-600">{{ fmt(p.startup_advance_recovery + p.supply_advance_recovery) }}</td>
                            <td class="px-4 py-2 text-right font-medium text-emerald-700">{{ fmt(p.net_paid) }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="p.is_paid ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                    {{ p.is_paid ? 'Payé' : 'En attente' }}
                                </span>
                            </td>
                            <td v-if="canWrite" class="px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button class="rounded p-1 text-gray-500 hover:bg-indigo-50 hover:text-indigo-700" title="Modifier" @click="openEdit(p)">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828A2 2 0 0110 16.5L6 17l.5-4a2 2 0 01.586-1.414z" /></svg>
                                    </button>
                                    <button class="rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-700" title="Supprimer" @click="remove(p)">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!payments.length">
                            <td :colspan="canWrite ? 8 : 7" class="px-4 py-6 text-center text-sm text-gray-500">Aucun décompte enregistré.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <PaymentFormModal
            :show="showModal"
            :project-id="project.id"
            :payment="editing"
            @close="showModal = false"
        />
    </div>
</template>
