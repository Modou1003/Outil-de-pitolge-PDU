<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAuth } from '@/Composables/useAuth';
import AppLogoImage from '@/Components/AppLogoImage.vue';

defineProps({
    collapsed: { type: Boolean, default: false },
});

const page = usePage();
const { hasPermission } = useAuth();

const counters = computed(() => page.props.counters ?? { active_projects: 0, active_alerts: 0 });

const items = computed(() => [
    {
        label: 'Tableau de bord',
        route: 'dashboard',
        href: route('dashboard'),
        icon: 'home',
        show: true,
    },
    {
        label: 'Projets',
        route: 'dashboard',
        href: route('dashboard'),
        icon: 'folder',
        badge: counters.value.active_projects,
        show: true,
    },
    {
        label: 'Alertes',
        route: 'alertes.index',
        href: route('alertes.index'),
        icon: 'bell',
        badge: counters.value.active_alerts,
        badgeColor: 'red',
        show: true,
    },
    {
        label: 'Rapports',
        route: 'rapports.index',
        href: route('rapports.index'),
        icon: 'file',
        show: true,
    },
    {
        label: 'Administration',
        route: 'admin.users.index',
        href: route('admin.users.index'),
        icon: 'settings',
        show: hasPermission('manage_users'),
        disabled: false,
    },
]);

const isActive = (item) => {
    try {
        return route().current(item.route);
    } catch {
        return false;
    }
};

const icons = {
    home: 'M3 12l9-9 9 9M4 10v10h16V10',
    folder: 'M3 7h5l2 2h11v10H3V7z',
    edit: 'M15.232 5.232l3.536 3.536M9 13l6-6 3 3-6 6H9v-3z M4 20h16',
    bell: 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1',
    file: 'M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9l-7-7z M13 2v7h7',
    settings: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
};
</script>

<template>
    <aside
        class="flex h-screen flex-col border-r border-white/10 bg-gradient-to-b from-green-900 via-emerald-900 to-green-950 text-white shadow-2xl transition-all duration-200"
        :class="collapsed ? 'w-16' : 'w-64'"
    >
        <div class="flex h-24 items-center gap-3 border-b border-white/10 px-4">
            <div class="rounded-2xl bg-white/95 p-1 shadow-lg shadow-orange-500/25">
                <AppLogoImage className="h-16 w-16 rounded-xl object-contain" />
            </div>
            <div v-if="!collapsed" class="min-w-0">
                <p class="text-sm font-bold leading-tight text-white">PDU-CI</p>
                <p class="truncate text-xs text-orange-100">Suivi projets</p>
            </div>
        </div>

        <nav class="flex-1 space-y-0.5 overflow-y-auto px-2 py-4">
            <template v-for="(item, idx) in items" :key="idx">
                <Link
                    v-if="item.show"
                    :href="item.href"
                    :class="[
                        'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                        isActive(item)
                            ? 'bg-white text-green-900 shadow-lg shadow-orange-500/20'
                            : 'text-green-50 hover:bg-white/10 hover:text-white',
                        item.disabled ? 'cursor-not-allowed opacity-50' : '',
                    ]"
                    :title="collapsed ? item.label : ''"
                    @click="item.disabled && $event.preventDefault()"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" />
                    </svg>
                    <span v-if="!collapsed" class="flex-1">{{ item.label }}</span>
                    <span
                        v-if="!collapsed && item.badge"
                        class="inline-flex min-w-[1.5rem] justify-center rounded-full px-1.5 py-0.5 text-xs font-semibold"
                        :class="item.badgeColor === 'red' ? 'bg-red-500 text-white' : 'bg-orange-400 text-green-950'"
                    >
                        {{ item.badge }}
                    </span>
                </Link>
            </template>
        </nav>
    </aside>
</template>
