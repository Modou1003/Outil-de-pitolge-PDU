<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    project: { type: Object, required: true },
});

const emit = defineEmits(['close']);
const page = usePage();

const depot = useForm({ fichier: null });
const validation = useForm({ fichier: '' });

// Compte rendu de lecture renvoyé par le serveur : rien n'est encore écrit.
const apercu = ref(null);
const erreur = computed(() => depot.errors.fichier || validation.errors.fichier);

watch(() => props.show, (v) => {
    if (v) {
        apercu.value = null;
        depot.reset();
        depot.clearErrors();
        validation.clearErrors();
    }
});

const lire = () => {
    if (!depot.fichier) return;
    depot.post(route('projects.import.preview', props.project.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            apercu.value = page.props.flash?.import_apercu ?? null;
        },
    });
};

const confirmer = () => {
    validation.fichier = apercu.value.fichier;
    validation.post(route('projects.import.store', props.project.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            router.reload({ only: ['building_works', 'progresses', 'kpis', 'alerts', 'payments'] });
        },
    });
};

const nombre = (v, d = 2) => v === null || v === undefined
    ? '—'
    : Number(v).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });

const formatMois = (p) => {
    if (!p) return '—';
    const [a, m] = p.split('-');
    return new Date(Number(a), Number(m) - 1, 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
};

const franc = (v) => v === null || v === undefined
    ? '—'
    : Number(v).toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' F';

const rienANouveau = computed(() => apercu.value
    && apercu.value.periodes_nouvelles.length === 0
    && apercu.value.ouvrages_nouveaux === 0
    && apercu.value.decomptes_nouveaux.length === 0
    && apercu.value.decomptes_regles.length === 0);
</script>

<template>
    <Modal :show="show" max-width="4xl" @close="emit('close')">
        <div class="rounded-lg bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Importer la base de calcul d'avancement</h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Classeur mensuel établi par la mission de contrôle. Seules les informations que le projet
                        ne connaît pas encore seront ajoutées.
                    </p>
                </div>
                <button class="text-gray-400 hover:text-gray-600" @click="emit('close')">✕</button>
            </div>

            <!-- Étape 1 : dépôt du fichier -->
            <div v-if="!apercu" class="mt-6">
                <label class="block text-sm font-medium text-gray-700">Fichier Excel (.xlsx)</label>
                <input
                    type="file"
                    accept=".xlsx,.xlsm,.xls"
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-indigo-700"
                    @change="depot.fichier = $event.target.files[0]"
                />
                <p v-if="erreur" class="mt-2 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">{{ erreur }}</p>
                <p class="mt-2 text-xs text-gray-500">
                    Les feuilles lues sont « progress », « récap mois » et « facturation PFO ». Rien n'est enregistré
                    à cette étape : le contenu reconnu vous sera présenté avant validation.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="emit('close')">
                        Annuler
                    </button>
                    <button
                        type="button"
                        :disabled="!depot.fichier || depot.processing"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        @click="lire"
                    >
                        {{ depot.processing ? 'Lecture…' : 'Lire le fichier' }}
                    </button>
                </div>
            </div>

            <!-- Étape 2 : contrôle avant écriture -->
            <div v-else class="mt-5 space-y-4">
                <p class="text-xs text-gray-500">
                    <span class="font-medium text-gray-700">{{ apercu.nom_fichier }}</span>
                    — situation arrêtée au {{ formatMois(apercu.arrete_au) }}.
                </p>

                <div v-if="apercu.anomalies.length" class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs font-semibold text-amber-800">Points à vérifier</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-4 text-xs text-amber-800">
                        <li v-for="(a, i) in apercu.anomalies" :key="i">{{ a }}</li>
                    </ul>
                </div>

                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-gray-100 sm:grid-cols-4">
                    <div class="bg-white px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wide text-gray-500">Mois à ajouter</dt>
                        <dd class="text-lg font-bold text-gray-900">{{ apercu.periodes_nouvelles.length }}</dd>
                    </div>
                    <div class="bg-white px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wide text-gray-500">Ouvrages nouveaux</dt>
                        <dd class="text-lg font-bold text-gray-900">{{ apercu.ouvrages_nouveaux }}</dd>
                    </div>
                    <div class="bg-white px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wide text-gray-500">Décomptes nouveaux</dt>
                        <dd class="text-lg font-bold text-gray-900">{{ apercu.decomptes_nouveaux.length }}</dd>
                    </div>
                    <div class="bg-white px-3 py-2">
                        <dt class="text-[10px] uppercase tracking-wide text-gray-500">Pondération lue</dt>
                        <dd class="text-lg font-bold" :class="Math.abs(apercu.ponderation_totale - 100) > 0.5 ? 'text-red-600' : 'text-emerald-600'">
                            {{ nombre(apercu.ponderation_totale) }} %
                        </dd>
                    </div>
                </dl>

                <div class="rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-700">
                    Avancement consolidé — lu au classeur :
                    <strong>{{ nombre(apercu.avancement_lu) }} %</strong>,
                    actuellement dans l'application :
                    <strong>{{ nombre(apercu.avancement_actuel) }} %</strong>.
                </div>

                <!-- Ce même classeur alimente la section financière et les indicateurs -->
                <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-3">
                    <p class="text-xs font-semibold text-indigo-900">Sections également alimentées</p>
                    <ul class="mt-1 space-y-0.5 text-xs text-indigo-900/90">
                        <li>
                            <span class="font-medium">Avancement financier</span> —
                            {{ apercu.financier.lignes_nouvelles }} relevé(s) de valeur acquise à créer, un par
                            ouvrage et par mois. Valeur planifiée {{ franc(apercu.financier.valeur_planifiee) }},
                            valeur acquise {{ franc(apercu.financier.valeur_acquise) }},
                            coût réel {{ franc(apercu.financier.cout_reel) }}.
                        </li>
                        <li>
                            <span class="font-medium">Indicateurs</span> — avancement pondéré, indices de performance
                            des délais et des coûts, valeur acquise temporelle, fin projetée, écart physico-financier
                            et fraîcheur sont recalculés après l'import.
                        </li>
                        <li>
                            <span class="font-medium">Alertes</span> — les onze règles de détection sont réexaminées,
                            dont les retards au démarrage lus au planning.
                        </li>
                    </ul>
                    <p v-if="!apercu.financier.cout_reel" class="mt-1 text-[11px] text-amber-800">
                        Aucun décompte lu : l'indice de performance des coûts restera incalculable.
                    </p>
                </div>

                <p v-if="rienANouveau" class="rounded-lg bg-gray-50 px-3 py-3 text-xs text-gray-600">
                    Ce classeur n'apporte aucune information nouvelle : les mois qu'il contient sont déjà saisis.
                    Les pondérations et les calendriers des ouvrages seront néanmoins rafraîchis.
                </p>

                <div v-if="apercu.periodes_nouvelles.length" class="text-xs text-gray-600">
                    Périodes qui seront créées :
                    <span class="font-medium text-gray-900">{{ apercu.periodes_nouvelles.map(formatMois).join(', ') }}</span>
                </div>

                <!-- Détail par ouvrage -->
                <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-100 text-xs">
                        <thead class="sticky top-0 bg-gray-50 text-left uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2">Ouvrage</th>
                                <th class="px-3 py-2 text-right">Poids</th>
                                <th class="px-3 py-2 text-right">Avancement lu</th>
                                <th class="px-3 py-2">Début prévu</th>
                                <th class="px-3 py-2">Début réel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="(o, i) in apercu.ouvrages" :key="i" class="hover:bg-gray-50">
                                <td class="px-3 py-1.5">
                                    {{ o.nom }}
                                    <span v-if="o.etat === 'nouveau'" class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">nouveau</span>
                                </td>
                                <td class="px-3 py-1.5 text-right">
                                    {{ nombre(o.poids) }} %
                                    <span v-if="o.poids_actuel !== null && Math.abs(o.poids_actuel - o.poids) > 0.01" class="text-amber-600">
                                        (était {{ nombre(o.poids_actuel) }})
                                    </span>
                                </td>
                                <td class="px-3 py-1.5 text-right font-medium">{{ nombre(o.avancement, 1) }} %</td>
                                <td class="px-3 py-1.5">{{ o.debut_prevu ?? '—' }}</td>
                                <td class="px-3 py-1.5" :class="!o.debut_reel ? 'text-red-600' : ''">
                                    {{ o.debut_reel ?? 'non démarré' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Décomptes -->
                <div v-if="apercu.decomptes_nouveaux.length" class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs font-semibold text-gray-700">Décomptes qui seront enregistrés</p>
                    <ul class="mt-1 space-y-0.5 text-xs text-gray-600">
                        <li v-for="d in apercu.decomptes_nouveaux" :key="d.numero">
                            N° {{ d.numero }} du {{ d.date }} — {{ franc(d.brut) }}
                            <span :class="d.regle ? 'text-emerald-600' : 'text-amber-600'">
                                ({{ d.regle ? 'réglé' : 'déposé, non réglé' }})
                            </span>
                        </li>
                    </ul>
                </div>

                <p v-if="apercu.decomptes_regles.length" class="text-xs text-emerald-700">
                    Décomptes désormais réglés : n° {{ apercu.decomptes_regles.join(', n° ') }}.
                </p>

                <p v-if="erreur" class="rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">{{ erreur }}</p>

                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                    <button type="button" class="text-xs font-medium text-gray-500 hover:text-gray-700" @click="apercu = null">
                        ← Choisir un autre fichier
                    </button>
                    <div class="flex gap-3">
                        <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="emit('close')">
                            Annuler
                        </button>
                        <button
                            type="button"
                            :disabled="validation.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            @click="confirmer"
                        >
                            {{ validation.processing ? 'Enregistrement…' : 'Confirmer l’import' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
