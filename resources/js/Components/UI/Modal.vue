<script setup>
import { onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg | xl
    closable: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const sizeClass = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-2xl',
};

const onKeydown = (e) => {
    if (e.key === 'Escape' && props.show && props.closable) emit('close');
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

watch(() => props.show, (v) => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = v ? 'hidden' : '';
    }
});
</script>

<template>
    <transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4"
            @click.self="closable && emit('close')"
        >
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="scale-95 opacity-0"
                enter-to-class="scale-100 opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="scale-100 opacity-100"
                leave-to-class="scale-95 opacity-0"
            >
                <div v-if="show" class="relative w-full rounded-xl bg-white shadow-xl" :class="sizeClass[size]">
                    <div v-if="title || closable" class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <h3 class="text-base font-semibold text-gray-900">{{ title }}</h3>
                        <button
                            v-if="closable"
                            type="button"
                            class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
                            @click="emit('close')"
                            aria-label="Fermer"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="px-5 py-4">
                        <slot />
                    </div>
                    <div v-if="$slots.footer" class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-3">
                        <slot name="footer" />
                    </div>
                </div>
            </transition>
        </div>
    </transition>
</template>
