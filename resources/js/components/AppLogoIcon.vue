<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img
        src="/images/logo.png"
        alt="App Logo"
        class="h-12 w-12 rounded-md border border-gray-200 bg-white p-1 shadow-sm"
        :class="className">
</template>
