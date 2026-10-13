<script setup lang="ts">
import { IconChevronDown, IconChevronUp } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import { Avatar } from '@/components/ui/avatar';
import { Card, CardContent } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { usePageErrors } from '@/composables/usePageErrors';
import { getPlatformLogo } from '@/composables/usePlatformLogo';
import { toNullableText } from '@/lib/utils';
import {
    getYouTubeDescriptionIssue,
    YOUTUBE_DESCRIPTION_MAX_BYTES,
    youtubeDescriptionBytes,
} from '@/lib/youtubeDescription';
import {
    parseYouTubeTags,
    toYouTubeTags,
    YOUTUBE_TAGS_MAX_CHARACTERS,
    youtubeTagsCharacters,
} from '@/lib/youtubeTags';
import {
    YOUTUBE_TITLE_MAX_CHARACTERS,
    youtubeTitleCharacters,
} from '@/lib/youtubeTitle';
import type { ChannelAccount } from '@/types/channel';
import { Platform } from '@/types/platform';

interface Props {
    socialAccount: ChannelAccount | null;
    platformIndex: number;
    meta: Record<string, unknown>;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});
const emit = defineEmits<{
    'update:meta': [value: Record<string, unknown>];
}>();

const open = ref(false);
const errors = usePageErrors();
const descriptionId = computed(
    () => `youtube-description-${props.platformIndex}`,
);
const titleId = computed(() => `youtube-title-${props.platformIndex}`);
const tagsId = computed(() => `youtube-tags-${props.platformIndex}`);

const title = computed({
    get: () => toNullableText(props.meta.title) ?? '',
    set: (value: string) =>
        emit('update:meta', {
            ...props.meta,
            title: toNullableText(value),
        }),
});
const titleCharacters = computed(() => youtubeTitleCharacters(title.value));
const titleError = computed(
    () => errors.value[`platforms.${props.platformIndex}.meta.title`],
);

const description = computed({
    get: () => toNullableText(props.meta.description) ?? '',
    set: (value: string) =>
        emit('update:meta', {
            ...props.meta,
            description: toNullableText(value),
        }),
});
/**
 * The tags as typed, so a trailing comma or space survives until the next
 * tag; the meta gets the parsed list.
 */
const tagsText = ref(toYouTubeTags(props.meta.tags).join(', '));
watch(
    () => props.meta.tags,
    (tags) => {
        const parsed = toYouTubeTags(tags);

        if (parseYouTubeTags(tagsText.value).join(',') !== parsed.join(',')) {
            tagsText.value = parsed.join(', ');
        }
    },
);
const updateTags = (value: string | number) => {
    tagsText.value = String(value);
    const tags = parseYouTubeTags(tagsText.value);

    emit('update:meta', {
        ...props.meta,
        tags: tags.length > 0 ? tags : null,
    });
};
const tagsCharacters = computed(() =>
    youtubeTagsCharacters(parseYouTubeTags(tagsText.value)),
);
const tagsError = computed(
    () =>
        errors.value[`platforms.${props.platformIndex}.meta.tags`] ??
        Object.entries(errors.value).find(([key]) =>
            key.startsWith(`platforms.${props.platformIndex}.meta.tags.`),
        )?.[1],
);

const usedBytes = computed(() => youtubeDescriptionBytes(description.value));
const descriptionError = computed(() => {
    const issue = getYouTubeDescriptionIssue(props.meta.description);

    return issue
        ? trans(issue)
        : errors.value[`platforms.${props.platformIndex}.meta.description`];
});
</script>

<template>
    <Card class="gap-0 py-0 shadow-2xs">
        <Collapsible v-model:open="open">
            <CollapsibleTrigger as-child>
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between gap-3 p-4 text-sm"
                    :data-testid="`youtube-settings-toggle-${platformIndex}`"
                    :aria-expanded="open"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <span
                            class="inline-flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border bg-card shadow-2xs"
                        >
                            <img
                                :src="getPlatformLogo(Platform.YouTube)"
                                alt="YouTube"
                                class="size-full object-cover"
                            />
                        </span>
                        <span class="truncate font-bold text-foreground">{{
                            $t('posts.form.youtube.settings')
                        }}</span>
                        <span
                            v-if="socialAccount?.username"
                            class="truncate font-medium text-foreground/60"
                            >·&nbsp;@{{ socialAccount.username }}</span
                        >
                    </span>
                    <IconChevronUp
                        v-if="open"
                        class="size-4 shrink-0 text-foreground/60"
                    />
                    <IconChevronDown
                        v-else
                        class="size-4 shrink-0 text-foreground/60"
                    />
                </button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <CardContent
                    class="space-y-5 border-t-2 border-foreground/10 px-4 pt-4 pb-4"
                >
                    <div
                        v-if="socialAccount"
                        class="flex items-center gap-3 rounded-lg bg-foreground/5 p-3"
                    >
                        <Avatar
                            :src="socialAccount.avatar_url"
                            :name="socialAccount.display_label"
                            class="size-9 shrink-0 rounded-full border border-border shadow-2xs"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                            >
                                {{ $t('posts.form.youtube.posting_to') }}
                            </p>
                            <p class="truncate text-sm">
                                <span class="font-bold text-foreground">{{
                                    socialAccount.display_label
                                }}</span>
                                <span
                                    v-if="socialAccount.username"
                                    class="font-medium text-foreground/60"
                                    >&nbsp;@{{ socialAccount.username }}</span
                                >
                            </p>
                        </div>
                    </div>
                    <Field class="gap-2">
                        <FieldLabel
                            :for="titleId"
                            class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                        >
                            {{ $t('posts.form.youtube.title') }}
                        </FieldLabel>
                        <Input
                            :id="titleId"
                            v-model="title"
                            :data-testid="titleId"
                            :disabled="disabled"
                            :aria-invalid="titleError ? true : undefined"
                            :placeholder="
                                $t('posts.form.youtube.title_placeholder')
                            "
                        />
                        <p
                            class="text-xs tabular-nums"
                            :class="
                                titleError ||
                                titleCharacters > YOUTUBE_TITLE_MAX_CHARACTERS
                                    ? 'text-rose-600'
                                    : 'text-foreground/60'
                            "
                        >
                            {{
                                $t('posts.form.youtube.title_characters', {
                                    used: titleCharacters.toString(),
                                    limit: YOUTUBE_TITLE_MAX_CHARACTERS.toString(),
                                })
                            }}
                        </p>
                        <FieldError :errors="[titleError]" />
                    </Field>
                    <Field class="gap-2">
                        <FieldLabel
                            :for="descriptionId"
                            class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                        >
                            {{ $t('posts.form.youtube.description') }}
                        </FieldLabel>
                        <Textarea
                            :id="descriptionId"
                            v-model="description"
                            :data-testid="descriptionId"
                            :disabled="disabled"
                            :aria-invalid="descriptionError ? true : undefined"
                            :placeholder="
                                $t('posts.form.youtube.description_placeholder')
                            "
                            class="field-sizing-fixed min-h-32 w-full resize-y"
                        />
                        <p
                            class="text-xs tabular-nums"
                            :class="
                                descriptionError
                                    ? 'text-rose-600'
                                    : 'text-foreground/60'
                            "
                        >
                            {{
                                $t('posts.form.youtube.description_bytes', {
                                    used: usedBytes.toString(),
                                    limit: YOUTUBE_DESCRIPTION_MAX_BYTES.toString(),
                                })
                            }}
                        </p>
                        <FieldError :errors="[descriptionError]" />
                    </Field>
                    <Field class="gap-2">
                        <FieldLabel
                            :for="tagsId"
                            class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                        >
                            {{ $t('posts.form.youtube.tags') }}
                        </FieldLabel>
                        <Input
                            :id="tagsId"
                            :model-value="tagsText"
                            :data-testid="tagsId"
                            :disabled="disabled"
                            :aria-invalid="tagsError ? true : undefined"
                            :placeholder="
                                $t('posts.form.youtube.tags_placeholder')
                            "
                            @update:model-value="updateTags"
                        />
                        <p
                            class="text-xs tabular-nums"
                            :class="
                                tagsError ||
                                tagsCharacters > YOUTUBE_TAGS_MAX_CHARACTERS
                                    ? 'text-rose-600'
                                    : 'text-foreground/60'
                            "
                        >
                            {{ $t('posts.form.youtube.tags_hint') }}
                            {{
                                $t('posts.form.youtube.tags_characters', {
                                    used: tagsCharacters.toString(),
                                    limit: YOUTUBE_TAGS_MAX_CHARACTERS.toString(),
                                })
                            }}
                        </p>
                        <FieldError :errors="[tagsError]" />
                    </Field>
                </CardContent>
            </CollapsibleContent>
        </Collapsible>
    </Card>
</template>
