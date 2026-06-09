<script setup>
const props = defineProps({
    src: { type: String, default: '/images/logo.jpg' },
    alt: { type: String, default: 'PDU-CI' },
    className: { type: String, default: 'h-full w-full object-contain' },
});
</script>

<template>
    <img :src="src" :alt="alt" :class="className" />
</template>
