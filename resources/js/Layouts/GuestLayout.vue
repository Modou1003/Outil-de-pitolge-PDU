<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gradient-to-br from-green-950 via-emerald-900 to-orange-700 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-white drop-shadow-lg" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden border border-white/20 bg-white/95 px-6 py-4 shadow-2xl shadow-green-950/40 backdrop-blur sm:max-w-md sm:rounded-2xl"
        >
            <slot />
        </div>
    </div>
</template>
