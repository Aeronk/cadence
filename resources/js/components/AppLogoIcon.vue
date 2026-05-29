<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <!-- Cadence "C" mark — paired curves suggesting rhythm/cadence. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        fill="none"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <path
            d="M37 14a16 16 0 1 0 0 20"
            stroke="currentColor"
            stroke-width="5"
            stroke-linecap="round"
        />
        <path
            d="M41 20a8 8 0 1 0 0 8"
            stroke="currentColor"
            stroke-width="3.5"
            stroke-linecap="round"
            opacity="0.55"
        />
    </svg>
</template>
