<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useChannel } from '@/composables/useChannel';
import comments from '@/routes/comments';

type Comment = {
    id: number;
    body: string;
    user: { id: number; name: string };
    created_at: string;
};

const props = defineProps<{
    commentableType: 'task' | 'project';
    commentableId: number;
    comments: Comment[];
    currentUserId: number;
}>();

const items = ref<Comment[]>([...props.comments]);

watch(
    () => props.comments,
    (v) => (items.value = [...v]),
);

const form = useForm({
    commentable_type: props.commentableType,
    commentable_id: props.commentableId,
    body: '',
});

function submit() {
    form.post(comments.store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
}

function remove(comment: Comment) {
    router.delete(comments.destroy(comment.id).url, { preserveScroll: true });
}

// Live updates: when someone else posts a comment on this task/project,
// echo it into the list. Own posts are appended by Inertia's re-fetch.
useChannel<{ comment: Comment & { commentable_type: string; commentable_id: number } }>(
    `${props.commentableType}.${props.commentableId}`,
    'comment.posted',
    ({ comment }) => {
        if (comment.user.id === props.currentUserId) return;
        if (items.value.some((c) => c.id === comment.id)) return;
        items.value = [comment, ...items.value];
    },
);
</script>

<template>
    <section class="rounded-lg border">
        <header class="border-b px-4 py-3 font-semibold">Comments</header>

        <div class="divide-y">
            <div v-if="items.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                No comments yet.
            </div>
            <div v-for="c in items" :key="c.id" class="px-4 py-3">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span class="font-medium text-foreground">{{ c.user.name }}</span>
                    <div class="flex items-center gap-2">
                        <span>{{ new Date(c.created_at).toLocaleString() }}</span>
                        <button
                            v-if="c.user.id === currentUserId"
                            @click="remove(c)"
                            class="hover:text-foreground"
                        >
                            <Trash2 class="h-3 w-3" />
                        </button>
                    </div>
                </div>
                <p class="mt-1 whitespace-pre-wrap text-sm">{{ c.body }}</p>
            </div>
        </div>

        <form class="border-t p-4" @submit.prevent="submit">
            <textarea
                v-model="form.body"
                rows="3"
                placeholder="Write a comment…"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                required
            />
            <div class="mt-2 flex justify-end">
                <Button type="submit" :disabled="form.processing">Post</Button>
            </div>
        </form>
    </section>
</template>
