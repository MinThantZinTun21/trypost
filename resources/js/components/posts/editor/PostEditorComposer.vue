<script setup lang="ts">
import {
    IconAlertTriangle,
    IconAlt,
    IconExternalLink,
    IconFileTypePdf,
    IconGripVertical,
    IconLibraryPhoto,
    IconMoodSmile,
    IconTrash,
    IconVideo,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, nextTick, ref } from 'vue';

import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import AltTextDialog from '@/components/posts/editor/AltTextDialog.vue';
import EmojiPicker from '@/components/posts/EmojiPicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useMediaUpload } from '@/composables/useMediaUpload';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import date from '@/date';
import { mediaLimitsDocsUrl } from '@/lib/docs';
import {
    acceptAttribute,
    classify,
    isDocument,
    isImage,
    isVideo,
    MediaType,
} from '@/lib/mediaType';
import type { MediaItem } from '@/types/media';

interface PlatformLimit {
    platform: string;
    maxLength: number;
}

interface MediaIssue {
    platform: string;
    reason: string;
}

const props = withDefaults(
    defineProps<{
        platformLimits: PlatformLimit[];
        mediaIssues: Record<string, MediaIssue[]>;
        readOnly?: boolean;
    }>(),
    {
        readOnly: false,
    },
);

const content = defineModel<string>('content', { required: true });
const media = defineModel<MediaItem[]>('media', { required: true });

const emojiOpen = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const { uploading, uploadProgress, uploadFiles } = useMediaUpload(media);

const dragMediaIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);
const mediaThumbRefs = ref<HTMLElement[]>([]);
const lightbox = ref<InstanceType<typeof ImagePreviewDialog> | null>(null);

const openPreview = (item: MediaItem) => {
    const idx = media.value.findIndex((m) => m.id === item.id);
    if (idx < 0) return;
    lightbox.value?.openCollection(
        media.value.map((m) => ({
            url: m.url,
            type: classify(m) ?? MediaType.Image,
            altText: isImage(m) ? m.meta?.alt_text : undefined,
        })),
        idx,
    );
};

const limitsWithUsage = computed(() =>
    props.platformLimits.map((p) => {
        const used = content.value.length;
        const ratio = p.maxLength > 0 ? used / p.maxLength : 0;
        const state = ratio > 1 ? 'over' : ratio >= 0.9 ? 'warn' : 'ok';
        return { ...p, used, state };
    }),
);

const limitClass = (state: string): string => {
    if (state === 'over') return 'border-border bg-rose-100 text-rose-700';
    if (state === 'warn') return 'border-border bg-amber-100 text-amber-800';
    return 'border-border bg-card text-foreground';
};

const smallestLimit = computed(() => {
    if (props.platformLimits.length === 0) return null;

    return Math.min(...props.platformLimits.map((p) => p.maxLength));
});

const overflowParts = computed(() => {
    const limit = smallestLimit.value;
    if (limit === null || content.value.length <= limit) {
        return { fits: content.value, overflow: '' };
    }
    return {
        fits: content.value.slice(0, limit),
        overflow: content.value.slice(limit),
    };
});

const removeMedia = (mediaId: string) => {
    media.value = media.value.filter((m) => m.id !== mediaId);
};

const onFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) {
        void uploadFiles(Array.from(target.files));
        target.value = '';
    }
};

const appendEmoji = (emoji: string) => {
    content.value += emoji;
    emojiOpen.value = false;
};

const moveMediaItem = (from: number, to: number) => {
    if (from === to || to < 0 || to >= media.value.length) return;
    const next = [...media.value];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    media.value = next;
};

const onMediaDragStart = (event: DragEvent, index: number) => {
    dragMediaIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
};

const onMediaDragOver = (event: DragEvent, index: number) => {
    if (dragMediaIndex.value === null) return;
    event.preventDefault();
    event.stopPropagation();
    dragOverIndex.value = index;
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
};

const onMediaDrop = (event: DragEvent, index: number) => {
    if (dragMediaIndex.value === null) return;
    event.preventDefault();
    event.stopPropagation();
    const from = dragMediaIndex.value;
    dragMediaIndex.value = null;
    dragOverIndex.value = null;
    moveMediaItem(from, index);
};

const onMediaDragEnd = () => {
    dragMediaIndex.value = null;
    dragOverIndex.value = null;
};

const GRID_COLS = 4;

const onMediaKeydown = async (event: KeyboardEvent, index: number) => {
    if (props.readOnly) return;
    if (media.value.length < 2) return;
    if (!event.altKey) return;

    const deltas: Record<string, number> = {
        ArrowLeft: -1,
        ArrowRight: 1,
        ArrowUp: -GRID_COLS,
        ArrowDown: GRID_COLS,
    };
    const delta = deltas[event.key];
    if (delta === undefined) return;

    event.preventDefault();
    const target = index + delta;
    if (target < 0 || target >= media.value.length) return;
    moveMediaItem(index, target);

    await nextTick();
    mediaThumbRefs.value[target]?.focus();
};

const issueLabel = (reason: string): string =>
    trans(`posts.form.warnings.${reason}`);

const altDialogOpen = ref(false);
const altDialogIndex = ref<number | null>(null);
const altDialogItem = computed<MediaItem | null>(() =>
    altDialogIndex.value !== null
        ? (media.value[altDialogIndex.value] ?? null)
        : null,
);

const openAltText = (index: number) => {
    altDialogIndex.value = index;
    altDialogOpen.value = true;
};

const onAltTextSave = (alt: string): void => {
    if (altDialogIndex.value === null) return;

    const next = [...media.value];
    const trimmed = alt.trim();
    next[altDialogIndex.value] = {
        ...next[altDialogIndex.value],
        meta: {
            ...(next[altDialogIndex.value].meta ?? {}),
            alt_text: trimmed || undefined,
        },
    };
    media.value = next;
};
</script>

<template>
    <div class="mx-auto max-w-2xl px-6 py-10">
        <div class="relative">
            <!-- Media grid (top) — always shown so "Add" tile is discoverable -->
            <div v-if="!readOnly || media.length" class="mb-6">
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <div
                        v-for="(item, index) in media"
                        :key="item.id"
                        :ref="
                            (el) => {
                                if (el)
                                    mediaThumbRefs[index] = el as HTMLElement;
                            }
                        "
                        data-testid="media-thumbnail"
                        class="group relative aspect-square cursor-zoom-in overflow-hidden rounded-none border border-border bg-muted shadow-2xs transition-all focus:ring-2 focus:ring-foreground focus:ring-offset-2 focus:outline-none"
                        :class="[
                            dragMediaIndex === index ? 'opacity-40' : '',
                            dragOverIndex === index && dragMediaIndex !== index
                                ? 'ring-2 ring-foreground ring-offset-2'
                                : '',
                            mediaIssues[item.id] ? '!border-rose-500' : '',
                        ]"
                        tabindex="0"
                        :draggable="!readOnly && media.length > 1"
                        @click="openPreview(item)"
                        @dragstart="onMediaDragStart($event, index)"
                        @dragover="onMediaDragOver($event, index)"
                        @drop="onMediaDrop($event, index)"
                        @dragend="onMediaDragEnd"
                        @keydown="onMediaKeydown($event, index)"
                    >
                        <video
                            v-if="isVideo(item)"
                            :src="item.url"
                            class="h-full w-full object-cover"
                            muted
                        />
                        <div
                            v-else-if="isDocument(item)"
                            class="flex h-full w-full flex-col items-center justify-center gap-1 bg-rose-50 p-2 text-center"
                        >
                            <IconFileTypePdf class="size-7 text-rose-600" />
                            <span
                                class="line-clamp-2 text-[10px] font-medium break-all text-foreground/70"
                                >{{ item.original_filename || 'PDF' }}</span
                            >
                        </div>
                        <img
                            v-else
                            :src="item.url"
                            :alt="item.original_filename"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />

                        <div
                            class="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-black/60 to-transparent opacity-0 transition-opacity group-hover:opacity-100"
                        />

                        <div
                            class="pointer-events-none absolute inset-x-1.5 bottom-1.5 flex flex-col items-start gap-1 text-[10px] font-medium text-white"
                        >
                            <span
                                v-if="isVideo(item) && item.meta?.duration"
                                class="inline-flex items-center gap-0.5 rounded-md bg-black/65 px-1.5 py-0.5 backdrop-blur-sm"
                            >
                                <IconVideo class="size-2.5" />
                                {{ date.formatClock(item.meta.duration) }}
                            </span>
                            <span
                                v-if="isImage(item) && item.meta?.alt_text"
                                class="inline-block max-w-full truncate rounded-md bg-black/65 px-1.5 py-0.5 backdrop-blur-sm"
                                data-testid="alt-text-badge"
                            >
                                {{ item.meta.alt_text }}
                            </span>
                        </div>

                        <TooltipProvider
                            v-if="mediaIssues[item.id]"
                            :delay-duration="100"
                        >
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <span
                                        class="absolute right-1.5 bottom-1.5 inline-flex h-5 items-center gap-0.5 rounded-none border border-border bg-rose-100 px-1.5 text-[10px] font-bold text-rose-700"
                                    >
                                        <IconAlertTriangle class="size-2.5" />
                                        {{ mediaIssues[item.id].length }}
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <ul class="space-y-1 text-xs">
                                        <li
                                            v-for="iss in mediaIssues[item.id]"
                                            :key="iss.platform"
                                            class="flex items-center gap-1.5"
                                        >
                                            <img
                                                :src="
                                                    getPlatformLogo(
                                                        iss.platform,
                                                    )
                                                "
                                                :alt="iss.platform"
                                                class="size-3 object-contain"
                                            />
                                            <span class="font-medium"
                                                >{{
                                                    getPlatformLabel(
                                                        iss.platform,
                                                    )
                                                }}:</span
                                            >
                                            <span class="opacity-80">{{
                                                issueLabel(iss.reason)
                                            }}</span>
                                            <a
                                                :href="
                                                    mediaLimitsDocsUrl(
                                                        iss.platform,
                                                    )
                                                "
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex shrink-0 opacity-80 hover:opacity-100"
                                                :title="
                                                    $t(
                                                        'posts.edit.compliance.media_limits_docs',
                                                    )
                                                "
                                                :aria-label="
                                                    $t(
                                                        'posts.edit.compliance.media_limits_docs',
                                                    )
                                                "
                                            >
                                                <IconExternalLink
                                                    class="size-3"
                                                />
                                            </a>
                                        </li>
                                    </ul>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <span
                            v-if="media.length > 1 && !readOnly"
                            class="absolute top-1.5 left-1.5 inline-flex size-6 cursor-grab items-center justify-center rounded-md border border-border bg-card text-foreground opacity-100 shadow-2xs transition-opacity lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus:opacity-100"
                        >
                            <IconGripVertical class="size-3.5" />
                        </span>

                        <button
                            v-if="!readOnly && isImage(item)"
                            type="button"
                            :title="$t('posts.edit.alt_text.edit')"
                            :aria-label="$t('posts.edit.alt_text.edit')"
                            class="absolute top-1.5 right-9 inline-flex size-6 cursor-pointer items-center justify-center rounded-md border border-border bg-card text-foreground opacity-100 shadow-2xs transition-all hover:bg-muted lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus:opacity-100"
                            data-testid="alt-text-button"
                            @click.stop="openAltText(index)"
                        >
                            <IconAlt class="size-3.5" />
                        </button>

                        <button
                            v-if="!readOnly"
                            type="button"
                            class="absolute top-1.5 right-1.5 inline-flex size-6 cursor-pointer items-center justify-center rounded-md border border-border bg-card text-foreground opacity-100 shadow-2xs transition-all hover:bg-destructive/10 hover:text-destructive lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus:opacity-100"
                            data-testid="media-remove"
                            @click.stop="removeMedia(item.id)"
                        >
                            <IconTrash class="size-3.5" />
                        </button>
                    </div>

                    <button
                        v-if="!readOnly"
                        type="button"
                        data-testid="media-upload"
                        :disabled="uploading"
                        class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-none border border-dashed border-foreground/25 text-foreground/60 transition-colors hover:border-primary/40 hover:bg-foreground/5 hover:text-foreground disabled:cursor-wait"
                        @click="fileInput?.click()"
                    >
                        <template v-if="uploading">
                            <Spinner class="size-5" />
                            <span
                                class="text-[10px] font-bold tracking-widest uppercase tabular-nums"
                                >{{ uploadProgress }}%</span
                            >
                        </template>
                        <template v-else>
                            <IconLibraryPhoto class="size-5" />
                            <span
                                class="text-[10px] font-bold tracking-widest uppercase"
                                >{{ $t('posts.edit.add') }}</span
                            >
                        </template>
                    </button>
                    <input
                        v-if="!readOnly"
                        ref="fileInput"
                        type="file"
                        multiple
                        class="hidden"
                        data-testid="media-upload-input"
                        :accept="acceptAttribute()"
                        @change="onFileSelect"
                    />
                </div>
            </div>

            <!-- Toolbar: between photos and textarea -->
            <div v-if="!readOnly" class="mb-4 flex items-center gap-2">
                <Popover v-model:open="emojiOpen">
                    <PopoverAnchor as-child>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="outline"
                                        size="icon-sm"
                                        class="rounded-none"
                                        :aria-label="
                                            $t('posts.edit.emoji_picker.search')
                                        "
                                        @click="emojiOpen = !emojiOpen"
                                    >
                                        <IconMoodSmile class="size-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{{
                                    $t('posts.edit.emoji_picker.search')
                                }}</TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </PopoverAnchor>
                    <PopoverContent class="w-auto p-0" align="start">
                        <EmojiPicker @select="appendEmoji" />
                    </PopoverContent>
                </Popover>
            </div>

            <!-- Per-platform counters (below menu, above textarea) -->
            <div
                v-if="limitsWithUsage.length > 0"
                class="mb-4 flex flex-wrap items-center gap-2"
            >
                <TooltipProvider
                    v-for="limit in limitsWithUsage"
                    :key="limit.platform"
                    :delay-duration="200"
                >
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Badge
                                as="span"
                                variant="outline"
                                :data-testid="`content-counter-${limit.platform}`"
                                :class="[
                                    'gap-1.5 px-2 py-1 text-[11px] leading-none font-bold tabular-nums shadow-2xs',
                                    limitClass(limit.state),
                                ]"
                            >
                                <span
                                    class="inline-flex size-3.5 shrink-0 items-center justify-center overflow-hidden rounded-full"
                                >
                                    <img
                                        :src="getPlatformLogo(limit.platform)"
                                        :alt="limit.platform"
                                        class="size-full object-cover"
                                    />
                                </span>
                                <span
                                    >{{ limit.used
                                    }}<span class="opacity-60"
                                        >/{{ limit.maxLength }}</span
                                    ></span
                                >
                            </Badge>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            getPlatformLabel(limit.platform)
                        }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <!-- Textarea: borderless, sheet-of-paper feel.
                 Mirror div sits behind to highlight chars beyond the smallest platform limit.
                 Both share identical font/padding/leading/wrap so highlights align with the textarea text. -->
            <div class="relative font-sans text-base leading-[1.7]">
                <div
                    v-if="overflowParts.overflow"
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 p-0 font-sans text-base leading-[1.7] break-words whitespace-pre-wrap text-transparent"
                >
                    <span>{{ overflowParts.fits }}</span
                    ><span class="rounded-sm bg-rose-100 text-rose-700">{{
                        overflowParts.overflow
                    }}</span>
                </div>
                <textarea
                    v-model="content"
                    data-testid="post-content"
                    :readonly="readOnly"
                    :placeholder="
                        readOnly ? '' : $t('posts.edit.caption_placeholder')
                    "
                    class="relative block w-full resize-none border-0 bg-transparent p-0 font-sans text-base leading-[1.7] shadow-none outline-none placeholder:text-foreground/40"
                    style="min-height: 280px; field-sizing: content"
                />
            </div>
        </div>

        <ImagePreviewDialog ref="lightbox" />
        <AltTextDialog
            v-model:open="altDialogOpen"
            :media-item="altDialogItem"
            @save="onAltTextSave"
        />
    </div>
</template>
