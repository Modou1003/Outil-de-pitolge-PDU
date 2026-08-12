<script setup>
import { Link } from '@inertiajs/vue3';

// Onglets de la section Administration. Les deux écrans partagent cette barre
// pour former un ensemble cohérent plutôt que deux entrées de menu séparées.
const tabs = [
    { label: 'Utilisateurs', route: 'admin.users.index', icon: 'M17 20h5v-2a3 3 0 00-5.36-1.9M17 20H7m10 0v-2c0-.66-.13-1.3-.36-1.9M7 20H2v-2a3 3 0 015.36-1.9M7 20v-2c0-.66.13-1.3.36-1.9m0 0a5 5 0 019.28 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    { label: "Journal d'activité", route: 'admin.activites.index', icon: 'M12 8v4l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
];

const isActive = (name) => {
    try {
        return route().current(name);
    } catch {
        return false;
    }
};
</script>

<template>
    <div class="border-b border-gray-200">
        <nav class="flex gap-1" aria-label="Sections de l'administration">
            <Link
                v-for="t in tabs"
                :key="t.route"
                :href="route(t.route)"
                class="inline-flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition"
                :class="isActive(t.route)
                    ? 'border-indigo-600 text-indigo-700'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="t.icon" />
                </svg>
                {{ t.label }}
            </Link>
        </nav>
    </div>
</template>
