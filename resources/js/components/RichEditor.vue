<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Link from '@tiptap/extension-link';
import {
    Bold,
    Italic,
    Strikethrough,
    Code,
    List,
    ListOrdered,
    Quote,
    Heading2,
    Heading3,
    Minus,
    Link2,
    Undo2,
    Redo2,
} from 'lucide-vue-next';
import { watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        placeholder?: string;
        editable?: boolean;
        minHeight?: string;
    }>(),
    {
        placeholder: 'Write…',
        editable: true,
        minHeight: '8rem',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editor = useEditor({
    content: props.modelValue || '',
    editable: props.editable,
    extensions: [
        StarterKit.configure({ heading: { levels: [2, 3] } }),
        Placeholder.configure({ placeholder: props.placeholder }),
        Link.configure({ openOnClick: false, autolink: true, linkOnPaste: true }),
    ],
    onUpdate: ({ editor }) => {
        const html = editor.getHTML();
        emit('update:modelValue', html === '<p></p>' ? '' : html);
    },
    editorProps: {
        attributes: {
            class: 'tiptap focus:outline-none',
        },
    },
});

watch(
    () => props.modelValue,
    (val) => {
        if (!editor.value) return;
        if (val !== editor.value.getHTML()) {
            editor.value.commands.setContent(val || '', { emitUpdate: false });
        }
    },
);

function promptLink() {
    if (!editor.value) return;
    const previous = editor.value.getAttributes('link').href;
    const url = window.prompt('Link URL', previous ?? 'https://');
    if (url === null) return;
    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}
</script>

<template>
    <div
        class="rounded-md border border-input bg-background text-sm transition focus-within:border-ring focus-within:ring-1 focus-within:ring-ring"
    >
        <div
            v-if="editor && editable"
            class="flex flex-wrap items-center gap-1 border-b px-1.5 py-1"
        >
            <button
                type="button"
                title="Bold"
                class="rounded p-1 hover:bg-muted"
                :class="{ 'bg-muted text-foreground': editor.isActive('bold') }"
                @click="editor.chain().focus().toggleBold().run()"
            >
                <Bold class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Italic"
                class="rounded p-1 hover:bg-muted"
                :class="{ 'bg-muted': editor.isActive('italic') }"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <Italic class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Strike"
                class="rounded p-1 hover:bg-muted"
                :class="{ 'bg-muted': editor.isActive('strike') }"
                @click="editor.chain().focus().toggleStrike().run()"
            >
                <Strikethrough class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Inline code"
                class="rounded p-1 hover:bg-muted"
                :class="{ 'bg-muted': editor.isActive('code') }"
                @click="editor.chain().focus().toggleCode().run()"
            >
                <Code class="h-4 w-4" />
            </button>

            <span class="mx-1 h-4 w-px bg-border" />

            <button
                type="button"
                title="Heading 2"
                class="rounded p-1 hover:bg-muted"
                :class="{ 'bg-muted': editor.isActive('heading', { level: 2 }) }"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                <Heading2 class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Heading 3"
                class="rounded p-1 hover:bg-muted"
                :class="{ 'bg-muted': editor.isActive('heading', { level: 3 }) }"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
            >
                <Heading3 class="h-4 w-4" />
            </button>

            <span class="mx-1 h-4 w-px bg-border" />

            <button
                type="button"
                title="Bulleted list"
                class="rounded p-1 hover:bg-muted"
                :class="{ 'bg-muted': editor.isActive('bulletList') }"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                <List class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Numbered list"
                class="rounded p-1 hover:bg-muted"
                :class="{ 'bg-muted': editor.isActive('orderedList') }"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Quote"
                class="rounded p-1 hover:bg-muted"
                :class="{ 'bg-muted': editor.isActive('blockquote') }"
                @click="editor.chain().focus().toggleBlockquote().run()"
            >
                <Quote class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Divider"
                class="rounded p-1 hover:bg-muted"
                @click="editor.chain().focus().setHorizontalRule().run()"
            >
                <Minus class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Link"
                class="rounded p-1 hover:bg-muted"
                :class="{ 'bg-muted': editor.isActive('link') }"
                @click="promptLink"
            >
                <Link2 class="h-4 w-4" />
            </button>

            <span class="mx-1 h-4 w-px bg-border" />

            <button
                type="button"
                title="Undo"
                class="rounded p-1 hover:bg-muted disabled:opacity-40"
                :disabled="!editor.can().undo()"
                @click="editor.chain().focus().undo().run()"
            >
                <Undo2 class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Redo"
                class="rounded p-1 hover:bg-muted disabled:opacity-40"
                :disabled="!editor.can().redo()"
                @click="editor.chain().focus().redo().run()"
            >
                <Redo2 class="h-4 w-4" />
            </button>
        </div>

        <EditorContent
            :editor="editor"
            class="prose prose-sm max-w-none px-3 py-2 dark:prose-invert"
            :style="{ minHeight }"
        />
    </div>
</template>

<style>
.tiptap p.is-editor-empty:first-child::before {
    color: hsl(var(--muted-foreground));
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}
.tiptap a {
    color: hsl(var(--primary));
    text-decoration: underline;
}
.tiptap blockquote {
    border-left: 3px solid hsl(var(--border));
    padding-left: 0.75rem;
    color: hsl(var(--muted-foreground));
}
.tiptap code {
    background: hsl(var(--muted));
    padding: 0 0.25rem;
    border-radius: 3px;
    font-size: 0.85em;
}
.tiptap hr {
    border: 0;
    border-top: 1px solid hsl(var(--border));
    margin: 1em 0;
}
</style>
