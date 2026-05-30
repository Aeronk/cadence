<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    File as FileIcon,
    FileText,
    FileImage,
    FileSpreadsheet,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

type ProjectFile = {
    id: number;
    original_name: string;
    mime_type: string | null;
    size_bytes: number;
    created_at: string;
    uploaded_by: number;
    uploader: { id: number; name: string } | null;
};

const props = defineProps<{
    projectId: number;
    files: ProjectFile[];
    currentUserId: number;
    canManage: boolean;
}>();

const dragOver = ref(false);
const uploadForm = useForm<{ file: File | null }>({ file: null });

function iconFor(mime: string | null) {
    if (!mime) return FileIcon;
    if (mime.startsWith('image/')) return FileImage;
    if (mime.includes('spreadsheet') || mime === 'text/csv') return FileSpreadsheet;
    if (mime === 'application/pdf' || mime.startsWith('text/')) return FileText;
    return FileIcon;
}

function formatSize(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function pick(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) submitFile(file);
    input.value = '';
}

function onDrop(e: DragEvent) {
    dragOver.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) submitFile(file);
}

function submitFile(file: File) {
    uploadForm.file = file;
    uploadForm.post(`/projects/${props.projectId}/files`, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => uploadForm.reset(),
    });
}

function remove(file: ProjectFile) {
    if (!confirm(`Delete "${file.original_name}"?`)) return;
    router.delete(`/files/${file.id}`, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-4">
        <label
            v-if="canManage"
            class="block cursor-pointer rounded-lg border-2 border-dashed border-border p-8 text-center transition"
            :class="dragOver ? 'border-primary bg-primary/5' : 'hover:border-primary/60'"
            @dragenter.prevent="dragOver = true"
            @dragover.prevent="dragOver = true"
            @dragleave="dragOver = false"
            @drop.prevent="onDrop"
        >
            <Upload class="mx-auto mb-2 h-6 w-6 text-muted-foreground" />
            <p class="text-sm font-medium">
                {{ uploadForm.processing ? 'Uploading…' : 'Drop a file here or click to browse' }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                PDF, Office docs, images, zip — up to 25 MB
            </p>
            <input
                type="file"
                class="sr-only"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.md,.csv,.zip,image/*"
                @change="pick"
            />
            <p
                v-if="uploadForm.errors.file"
                class="mt-2 text-xs text-red-500"
            >
                {{ uploadForm.errors.file }}
            </p>
        </label>

        <div
            v-if="files.length === 0"
            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            No files attached yet.
        </div>

        <div v-else class="divide-y rounded-lg border">
            <div
                v-for="file in files"
                :key="file.id"
                class="group flex items-center gap-3 px-3 py-2.5"
            >
                <component
                    :is="iconFor(file.mime_type)"
                    class="h-5 w-5 shrink-0 text-muted-foreground"
                />
                <a
                    :href="`/files/${file.id}/download`"
                    class="min-w-0 flex-1 truncate text-sm hover:underline"
                >
                    {{ file.original_name }}
                </a>
                <div class="hidden text-xs text-muted-foreground md:block">
                    {{ formatSize(file.size_bytes) }}
                </div>
                <div class="hidden text-xs text-muted-foreground md:block">
                    {{ file.uploader?.name ?? '—' }}
                </div>
                <div class="hidden text-xs text-muted-foreground md:block">
                    {{ new Date(file.created_at).toLocaleDateString() }}
                </div>
                <button
                    v-if="canManage || file.uploaded_by === currentUserId"
                    title="Delete"
                    class="opacity-0 transition group-hover:opacity-100"
                    @click="remove(file)"
                >
                    <Trash2 class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                </button>
            </div>
        </div>
    </div>
</template>
