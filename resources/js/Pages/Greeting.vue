<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    userName: { type: String, required: true },
    userRoles: { type: Array, default: () => [] },
});

const show = ref(false);
let redirectTimer = null;

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Bonjour';
    if (h < 18) return 'Bon après-midi';
    return 'Bonsoir';
});

const todayFr = computed(() => {
    return new Date().toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const roleKey = computed(() => props.userRoles[0] ?? 'admin');

const primaryRole = computed(() => {
    if (!props.userRoles.length) return '';
    const labels = {
        admin: 'Administrateur',
        directeur: 'Directeur',
        chef_projet: 'Chef de projet',
        agent_financier: 'Agent financier',
        visiteur: 'Visiteur',
    };
    return labels[roleKey.value] ?? props.userRoles[0];
});

const avatarUrl = computed(() => {
    const map = {
        admin: '/images/avatars/admin.jpg',
        directeur: '/images/avatars/directeur.jpg',
        chef_projet: '/images/avatars/chef_projet.jpg',
        agent_financier: '/images/avatars/agent_financier.jpg',
        visiteur: '/images/avatars/visiteur.jpg',
    };
    return map[roleKey.value] ?? '/images/avatars/admin.jpg';
});

const goToDashboard = () => {
    router.post(route('greeting.acknowledge'));
};

onMounted(() => {
    requestAnimationFrame(() => {
        show.value = true;
    });
    redirectTimer = setTimeout(goToDashboard, 4500);
});

onUnmounted(() => {
    if (redirectTimer) clearTimeout(redirectTimer);
});
</script>

<template>
    <Head title="Bienvenue" />

    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-indigo-900 via-indigo-700 to-emerald-600">
        <div class="absolute inset-0 opacity-20">
            <div class="absolute -left-20 -top-20 h-80 w-80 rounded-full bg-white blur-3xl"></div>
            <div class="absolute -bottom-20 -right-20 h-96 w-96 rounded-full bg-emerald-300 blur-3xl"></div>
        </div>

        <div
            class="relative z-10 mx-4 w-full max-w-2xl transform rounded-2xl bg-white/95 p-10 text-center shadow-2xl backdrop-blur transition duration-700 ease-out"
            :class="show ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
        >
            <div class="mx-auto mb-6 h-36 w-36 overflow-hidden rounded-full border-4 border-white bg-slate-100 shadow-lg">
                <img :src="avatarUrl" alt="Photo de profil" class="h-full w-full object-cover" />
            </div>

            <p class="text-sm font-medium uppercase tracking-widest text-indigo-600">
                {{ todayFr }}
            </p>

            <h1 class="mt-2 text-4xl font-bold text-gray-900 sm:text-5xl">
                {{ greeting }},
            </h1>
            <h2 class="mt-1 bg-gradient-to-r from-indigo-600 to-emerald-600 bg-clip-text text-4xl font-extrabold text-transparent sm:text-5xl">
                {{ userName }}
            </h2>

            <p v-if="primaryRole" class="mx-auto mt-4 max-w-xs rounded-full bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700">
                {{ primaryRole }}
            </p>

            <p class="mx-auto mt-6 max-w-md text-base leading-relaxed text-gray-600">
                Bienvenue sur la plateforme de suivi-évaluation des projets du
                <span class="font-semibold text-gray-800">Programme de Développement des Universités de Côte d'Ivoire</span>.
            </p>

            <button
                type="button"
                class="mt-8 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-indigo-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2"
                @click="goToDashboard"
            >
                Accéder au tableau de bord
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </button>

            <p class="mt-6 text-xs text-gray-400">Redirection automatique dans quelques secondes…</p>
        </div>
    </div>
</template>
