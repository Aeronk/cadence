<script setup lang="ts">
import AuthSimpleLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';

const { title = '', description = '', variant = 'simple' } = defineProps<{
    title?: string;
    description?: string;
    variant?: 'simple' | 'split';
}>();
</script>

<template>
    <AuthSplitLayout v-if="variant === 'split'" :title="title" :description="description">
        <slot />
    </AuthSplitLayout>
    <AuthSimpleLayout v-else :title="title" :description="description">
        <slot />
    </AuthSimpleLayout>
</template>
