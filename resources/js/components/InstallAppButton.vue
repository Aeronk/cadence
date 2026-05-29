<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { Download } from 'lucide-vue-next';

const canInstall = ref(false);

function check() {
    canInstall.value = !!window.deferredInstallPrompt;
}

async function install() {
    const prompt = window.deferredInstallPrompt;
    if (!prompt) return;
    await prompt.prompt();
    await prompt.userChoice;
    window.deferredInstallPrompt = null;
    canInstall.value = false;
}

onMounted(() => {
    check();
    window.addEventListener('cadence:installable', check);
});

onBeforeUnmount(() => {
    window.removeEventListener('cadence:installable', check);
});
</script>

<template>
    <button
        v-if="canInstall"
        type="button"
        @click="install"
        class="flex w-full items-center gap-2 rounded-md border border-border px-3 py-2 text-sm hover:bg-muted"
    >
        <Download class="h-4 w-4" />
        Install app
    </button>
</template>
