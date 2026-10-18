<script setup lang="ts">
import { IconCalendar, IconTrash } from '@tabler/icons-vue';
import { computed } from 'vue';

import PickTimePopover from '@/components/posts/PickTimePopover.vue';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePageErrors } from '@/composables/usePageErrors';
import { PostStatus } from '@/types/post';

const props = withDefaults(
    defineProps<{
        isReadOnly: boolean;
        isScheduled: boolean;
        isNew: boolean;
        canEdit?: boolean;
        isSubmitting: boolean;
        isPostActionDisabled: boolean;
        postActionTooltip: string;
        pickTimeLabel: string;
    }>(),
    {
        canEdit: true,
    },
);

const hasPickedTime = defineModel<boolean>('hasPickedTime', { required: true });
const scheduledDateTime = defineModel<string>('scheduledDateTime', {
    required: true,
});

const emit = defineEmits<{
    (e: 'delete'): void;
    (e: 'submit', status: string): void;
}>();

const errors = usePageErrors();
const scheduledAtError = computed(() => errors.value.scheduled_at);

// A Scheduled post that keeps a time is saved in place; a new one is scheduled.
const scheduleLabelKey = computed(() =>
    props.isScheduled && !props.isNew
        ? 'posts.edit.save'
        : 'posts.edit.schedule',
);
</script>

<template>
    <div
        v-if="!isReadOnly && canEdit"
        class="flex w-full flex-col gap-1 lg:w-auto lg:items-end"
    >
        <div class="flex w-full items-center gap-2 lg:w-auto">
            <TooltipProvider>
                <Tooltip v-if="!isNew">
                    <TooltipTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="bg-destructive/10 hover:bg-destructive/20"
                            data-testid="post-delete"
                            :disabled="isSubmitting"
                            @click="emit('delete')"
                        >
                            <IconTrash class="size-4 text-destructive" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{
                        $t('posts.edit.delete')
                    }}</TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <span tabindex="0" class="min-w-0">
                            <PickTimePopover
                                v-model="scheduledDateTime"
                                :disabled="isPostActionDisabled"
                                @confirm="hasPickedTime = true"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="max-w-full"
                                    data-testid="post-pick-time"
                                    :disabled="isPostActionDisabled"
                                >
                                    <IconCalendar class="size-4" />
                                    <span class="truncate">{{
                                        pickTimeLabel
                                    }}</span>
                                </Button>
                            </PickTimePopover>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent
                        v-if="postActionTooltip"
                        class="max-w-xs whitespace-pre-line"
                    >
                        {{ postActionTooltip }}
                    </TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <span tabindex="0" class="flex-1 lg:flex-none">
                            <Button
                                type="button"
                                class="w-full lg:w-auto"
                                data-testid="post-submit"
                                :disabled="isPostActionDisabled"
                                @click="
                                    emit(
                                        'submit',
                                        hasPickedTime
                                            ? PostStatus.Scheduled
                                            : PostStatus.Publishing,
                                    )
                                "
                            >
                                {{
                                    hasPickedTime
                                        ? $t(scheduleLabelKey)
                                        : $t('posts.edit.post_now')
                                }}
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent
                        v-if="postActionTooltip"
                        class="max-w-xs whitespace-pre-line"
                    >
                        {{ postActionTooltip }}
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>
        <FieldError :errors="[scheduledAtError]" />
    </div>
</template>
