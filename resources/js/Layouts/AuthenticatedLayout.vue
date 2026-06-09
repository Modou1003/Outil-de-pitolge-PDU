<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Sidebar from '@/Components/Layout/Sidebar.vue';
import Topbar from '@/Components/Layout/Topbar.vue';

defineProps({
    title: { type: String, default: '' },
    breadcrumbs: { type: Array, default: () => [] },
});

const page = usePage();
const sidebarCollapsed = ref(false);
const mobileOpen = ref(false);
const isMobile = ref(false);

const handleResize = () => {
    isMobile.value = window.innerWidth < 768;
    if (!isMobile.value) mobileOpen.value = false;
};

const toggleSidebar = () => {
    if (isMobile.value) {
        mobileOpen.value = !mobileOpen.value;
    } else {
        sidebarCollapsed.value = !sidebarCollapsed.value;
    }
};

onMounted(() => {
    handleResize();
    window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
});

const flash = () => page.props.flash ?? {};
</script>

<template>
    <div class="flex min-h-screen bg-gradient-to-br from-emerald-950 via-green-900 to-orange-700">
        <!-- Sidebar desktop -->
        <div class="hidden md:block">
            <Sidebar :collapsed="sidebarCollapsed" />
        </div>

        <!-- Sidebar mobile (overlay) -->
        <transition
            enter-active-class="transition duration-200"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition duration-150"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
        >
            <div v-if="isMobile && mobileOpen" class="fixed inset-y-0 left-0 z-40 md:hidden">
                <Sidebar :collapsed="false" />
            </div>
        </transition>
        <div
            v-if="isMobile && mobileOpen"
            class="fixed inset-0 z-30 bg-black/40 md:hidden"
            @click="mobileOpen = false"
        />

        <div class="flex min-w-0 flex-1 flex-col">
            <Topbar
                :title="title"
                :breadcrumbs="breadcrumbs"
                @toggle-sidebar="toggleSidebar"
            />

            <!-- Flash messages -->
            <div v-if="flash().success || flash().error || flash().info" class="px-6 pt-4">
                <div
                    v-if="flash().success"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ flash().success }}
                </div>
                <div
                    v-if="flash().error"
                    class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {{ flash().error }}
                </div>
                <div
                    v-if="flash().info"
                    class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"
                >
                    {{ flash().info }}
                </div>
            </div>

            <header v-if="$slots.header" class="border-b border-white/10 bg-white/90 px-6 py-4 shadow-sm backdrop-blur">
                <slot name="header" />
            </header>

            <main class="flex-1 bg-gradient-to-br from-green-50 via-white to-orange-50 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
