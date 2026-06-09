<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAuth } from '@/Composables/useAuth';

defineProps({
    title: { type: String, default: '' },
    breadcrumbs: { type: Array, default: () => [] },
});

defineEmits(['toggle-sidebar']);

const page = usePage();
const { user, initials, roleLabel } = useAuth();
const dropdownOpen = ref(false);

const alertsCount = computed(() => page.props.counters?.active_alerts ?? 0);

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-white/20 bg-white/85 px-6 shadow-sm backdrop-blur-xl">
        <div class="flex min-w-0 items-center gap-4">
            <button
                type="button"
                class="rounded-xl p-2 text-slate-600 transition hover:bg-green-50 hover:text-green-700"
                aria-label="Basculer le menu"
                @click="$emit('toggle-sidebar')"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div class="min-w-0">
                <nav v-if="breadcrumbs.length" class="flex items-center gap-1 text-xs text-slate-500">
                    <template v-for="(b, i) in breadcrumbs" :key="i">
                        <Link v-if="b.href" :href="b.href" class="hover:text-green-700 hover:underline">{{ b.label }}</Link>
                        <span v-else>{{ b.label }}</span>
                        <span v-if="i < breadcrumbs.length - 1" class="text-orange-300">/</span>
                    </template>
                </nav>
                <h1 class="truncate bg-gradient-to-r from-green-900 to-orange-500 bg-clip-text text-lg font-bold text-transparent">{{ title }}</h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Link
                :href="route('alertes.index')"
                class="relative rounded-full p-2 text-slate-600 transition hover:bg-green-50 hover:text-green-700"
                aria-label="Alertes"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1" />
                </svg>
                <span
                    v-if="alertsCount > 0"
                    class="absolute -right-0.5 -top-0.5 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white"
                >
                    {{ alertsCount > 99 ? '99+' : alertsCount }}
                </span>
            </Link>

            <div class="relative">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-lg px-2 py-1 transition hover:bg-gray-50"
                    @click="dropdownOpen = !dropdownOpen"
                    @blur="setTimeout(() => (dropdownOpen = false), 150)"
                >
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-green-600 to-orange-500 text-sm font-bold text-white shadow-lg shadow-orange-500/25">
                        {{ initials }}
                    </div>
                    <div class="hidden text-left md:block">
                        <p class="text-sm font-semibold leading-tight text-slate-900">{{ user?.name }}</p>
                        <p class="text-xs text-green-700">{{ roleLabel }}</p>
                    </div>
                    <svg class="hidden h-4 w-4 text-gray-400 md:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <transition
                    enter-active-class="transition duration-100 ease-out"
                    enter-from-class="scale-95 opacity-0"
                    enter-to-class="scale-100 opacity-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="scale-100 opacity-100"
                    leave-to-class="scale-95 opacity-0"
                >
                    <div
                        v-if="dropdownOpen"
                        class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl border border-green-100 bg-white py-1 shadow-xl shadow-green-900/10"
                    >
                        <div class="border-b border-gray-100 px-4 py-2">
                            <p class="text-sm font-medium text-gray-900">{{ user?.name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ user?.email }}</p>
                        </div>
                        <Link
                            :href="route('profile.edit')"
                            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Mon profil
                        </Link>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 border-t border-gray-100 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            @click="logout"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Déconnexion
                        </button>
                    </div>
                </transition>
            </div>
        </div>
    </header>
</template>
