<script setup>
import AmendmentsSection from '@/Components/Projects/AmendmentsSection.vue';

defineProps({
    project: { type: Object, required: true },
    amendments: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-200">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Marché et avenants</h3>
            <p class="mt-1 text-xs text-gray-500">
                Le montant et la date de fin initiaux du marché restent intacts. Chaque avenant vient s'y ajouter
                pour former le marché actualisé, qui sert de référence à tous les indicateurs de coût et de délai.
            </p>
        </div>

        <AmendmentsSection :project="project" :amendments="amendments" />
    </div>
</template>
