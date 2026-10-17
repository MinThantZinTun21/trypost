<script setup lang="ts">
import { IconCalendar, IconCircleCheck } from '@tabler/icons-vue';
import { computed } from 'vue';

import PostEditorActions from '@/components/posts/editor/PostEditorActions.vue';
import { PostStatus } from '@/types/post';

interface Props {
    post: { status: string };
    isNew: boolean;
    canEdit?: boolean;
    isSubmitting: boolean;
    isPostActionDisabled: boolean;
    postActionTooltip: string;
    pickTimeLabel: string;
}

const props = withDefaults(defineProps<Props>(), {
    canEdit: true,
});

const hasPickedTime = defineModel<boolean>('hasPickedTime', { required: true });
const scheduledDateTime = defineModel<string>('scheduledDateTime', {
    required: true,
});

const emit = defineEmits<{
    (e: 'delete'): void;
    (e: 'submit', status: string): void;
}>();

const READONLY_STATUSES: readonly string[] = [
    PostStatus.Publishing,
    PostStatus.Published,
    PostStatus.PartiallyPublished,
    PostStatus.Failed,
];
const PUBLISHED_STATUSES: readonly string[] = [
    PostStatus.Published,
    PostStatus.PartiallyPublished,
];

const isReadOnly = computed(() =>
    READONLY_STATUSES.includes(props.post.status),
);
const isScheduled = computed(
    () => !props.isNew && props.post.status === PostStatus.Scheduled,
);
const isPublished = computed(() =>
    PUBLISHED_STATUSES.includes(props.post.status),
);
</script>

<template>
    <header
        :class="[
            'flex shrink-0 items-center gap-3 border-b border-border px-4 py-3 md:px-6',
            isScheduled ? 'bg-primary/10' : 'bg-card',
        ]"
    >
        <!-- Left: the scheduled banner, or the editable-state status.
             Both clear the floating hamburger on mobile (pl-12 md:pl-0). -->
        <template v-if="isScheduled">
            <div
                class="flex min-w-0 flex-1 items-start gap-3 pl-12 md:items-center md:pl-0"
            >
                <div
                    class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground md:size-9"
                >
                    <IconCalendar class="size-4" stroke-width="2" />
                </div>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="text-sm font-semibold text-foreground">
                        {{ $t('posts.edit.scheduled_overlay_title') }}
                    </p>
                    <p class="text-xs text-foreground/70">
                        {{
                            $t('posts.edit.scheduled_overlay_subtitle', {
                                date: pickTimeLabel,
                            })
                        }}
                    </p>
                </div>
            </div>
        </template>

        <div v-else class="flex items-center gap-3 pl-12 md:pl-0">
            <span
                v-if="isPublished"
                class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700"
            >
                <IconCircleCheck class="size-3.5" stroke-width="2.5" />
                {{ $t('posts.edit.status.published') }}
            </span>
            <span
                v-else-if="isNew"
                class="flex items-center gap-1.5 text-xs font-semibold text-foreground/60"
            >
                <span class="size-2 rounded-full bg-foreground/40" />
                {{ $t('posts.edit.new_post') }}
            </span>
        </div>

        <!-- Actions: one instance for both states, pushed right on desktop.
             On mobile they live in the sticky bottom bar, so this stays hidden. -->
        <div class="ml-auto hidden lg:flex">
            <PostEditorActions
                :is-read-only="isReadOnly"
                :is-scheduled="isScheduled"
                :is-new="isNew"
                :can-edit="canEdit"
                :is-submitting="isSubmitting"
                :is-post-action-disabled="isPostActionDisabled"
                :post-action-tooltip="postActionTooltip"
                :pick-time-label="pickTimeLabel"
                v-model:has-picked-time="hasPickedTime"
                v-model:scheduled-date-time="scheduledDateTime"
                @delete="emit('delete')"
                @submit="emit('submit', $event)"
            />
        </div>
    </header>
</template>
