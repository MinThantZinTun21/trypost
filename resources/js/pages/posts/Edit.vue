<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconLoader2 } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onUnmounted, ref, watch } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import PostEditorActionBar from '@/components/posts/editor/PostEditorActionBar.vue';
import PostEditorComposer from '@/components/posts/editor/PostEditorComposer.vue';
import PostEditorHeader from '@/components/posts/editor/PostEditorHeader.vue';
import PostEditorMobileNav from '@/components/posts/editor/PostEditorMobileNav.vue';
import PostEditorTabs from '@/components/posts/editor/PostEditorTabs.vue';
import { usePollWhile } from '@/composables/usePollWhile';
import {
    firstCompatibleVariant,
    getMediaIncompatibilityReason,
    usePostCompliance,
} from '@/composables/usePostCompliance';
import { isActivelyPublishing } from '@/composables/usePostStatus';
import date from '@/date';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    destroy as destroyPost,
    update as updatePost,
} from '@/routes/app/posts';
import type { MediaItem } from '@/types/media';
import { PostStatus } from '@/types/post';
import type { TikTokPrivacyLevelValue } from '@/types/tiktok-privacy';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    display_label: string;
    handle_label: string;
    avatar_url: string | null;
}

interface PostPlatform {
    id: string;
    social_account_id: string | null;
    enabled: boolean;
    platform: string;
    platform_name: string | null;
    platform_username: string | null;
    platform_avatar: string | null;
    content_type: string | null;
    status: string;
    platform_url: string | null;
    error_message: string | null;
    published_at: string | null;
    social_account: SocialAccount | null;
    meta?: Record<string, any>;
}

interface Post {
    id: string;
    content: string;
    media: MediaItem[];
    status: string;
    scheduled_at: string | null;
    published_at: string | null;
    post_platforms: PostPlatform[];
}

interface Workspace {
    id: string;
    name: string;
}

interface TikTokCreatorInfo {
    creator_nickname: string | null;
    creator_username: string | null;
    creator_avatar_url: string | null;
    privacy_level_options: TikTokPrivacyLevelValue[];
    comment_disabled: boolean;
    duet_disabled: boolean;
    stitch_disabled: boolean;
    max_video_post_duration_sec: number | null;
}

const props = defineProps<{
    workspace: Workspace;
    post: Post;
    socialAccounts: SocialAccount[];
    platformConfigs: Record<string, any>;
    tiktokCreatorInfos?: Record<string, TikTokCreatorInfo> | null;
}>();

const post = computed(() => props.post);
const READONLY_STATUSES: readonly string[] = [
    PostStatus.Publishing,
    PostStatus.Published,
    PostStatus.PartiallyPublished,
    PostStatus.Failed,
];
const isReadOnly = computed(() =>
    READONLY_STATUSES.includes(post.value.status),
);
const isPublishing = computed(() =>
    isActivelyPublishing(post.value.status, post.value.post_platforms),
);
const isScheduled = computed(() => post.value.status === PostStatus.Scheduled);
const isLocked = computed(() => isReadOnly.value || isScheduled.value);

// Content
const content = ref(post.value.content || '');
const media = ref<MediaItem[]>(post.value.media || []);

// Platforms
const selectedPlatformIds = ref<string[]>(
    post.value.post_platforms.filter((pp) => pp.enabled).map((pp) => pp.id),
);

// Per-platform meta (TikTok settings, YouTube description, etc.)
const platformMeta = ref<Record<string, Record<string, any>>>(
    Object.fromEntries(
        post.value.post_platforms.map((pp) => [pp.id, { ...(pp.meta ?? {}) }]),
    ),
);

const updatePlatformMeta = (platformId: string, meta: Record<string, any>) => {
    platformMeta.value = { ...platformMeta.value, [platformId]: meta };
};

// Per-platform content_type (Facebook Post/Reel/Story, TikTok Video/Photo, etc.)
const platformContentTypes = ref<Record<string, string>>(
    Object.fromEntries(
        post.value.post_platforms.map((pp) => [pp.id, pp.content_type ?? '']),
    ),
);

const updatePlatformContentType = (platformId: string, contentType: string) => {
    platformContentTypes.value = {
        ...platformContentTypes.value,
        [platformId]: contentType,
    };
};

const {
    platformLimits,
    mediaIssues,
    platformIssues,
    canSchedule,
    postActionTooltip,
} = usePostCompliance({
    post,
    content,
    media,
    selectedPlatformIds,
    platformContentTypes,
    platformMeta,
    platformConfigs: props.platformConfigs,
});

// Schedule
const scheduledDateTime = ref(
    date.formatUtcForDateTimeLocalInput(post.value.scheduled_at),
);
const hasPickedTime = ref(Boolean(post.value.scheduled_at));

const pickTimeLabel = computed(() => {
    if (!hasPickedTime.value || !scheduledDateTime.value) {
        return trans('posts.edit.pick_time');
    }
    return date.formatLocalDateTime(scheduledDateTime.value);
});

// UI state
const isSubmitting = ref(false);
const isSaving = ref(false);
const showSaved = ref(false);
const isPostActionDisabled = computed(
    () =>
        isSubmitting.value ||
        selectedPlatformIds.value.length === 0 ||
        !canSchedule.value,
);
const queryParams =
    typeof window !== 'undefined'
        ? new URLSearchParams(window.location.search)
        : null;
const initialTabFromQuery = (() => {
    const tab = queryParams?.get('tab');
    if (['preview', 'schedule'].includes(tab ?? '')) {
        return tab as string;
    }
    return 'schedule';
})();
const activeTab = ref(initialTabFromQuery);

// On mobile a single switcher (PostEditorMobileNav) drives which panel shows;
// 'compose' reveals the composer, the rest reveal the tabs panel. It writes into
// the shared `activeTab` (mapping 'channels' to the existing 'schedule' tab value)
// so the tabs panel stays the single source of truth.
type MobileView = 'compose' | 'channels' | 'preview';
const mobileView = ref<MobileView>('compose');
const mobileViewToTab: Record<Exclude<MobileView, 'compose'>, string> = {
    channels: 'schedule',
    preview: 'preview',
};
watch(mobileView, (view) => {
    if (view !== 'compose') {
        activeTab.value = mobileViewToTab[view];
    }
});

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const snapToCompatibleVariant = (platformId: string) => {
    const pp = post.value.post_platforms.find((p) => p.id === platformId);
    const current = platformContentTypes.value[platformId];
    if (!pp || !current) return;
    if (!getMediaIncompatibilityReason(current, media.value)) return;

    const fallback = firstCompatibleVariant(pp.platform, media.value);
    if (!fallback) return;

    platformContentTypes.value = {
        ...platformContentTypes.value,
        [platformId]: fallback,
    };
};

const togglePlatform = (platformId: string) => {
    if (isLocked.value) return;

    if (selectedPlatformIds.value.includes(platformId)) {
        selectedPlatformIds.value = selectedPlatformIds.value.filter(
            (id) => id !== platformId,
        );
        return;
    }

    snapToCompatibleVariant(platformId);
    selectedPlatformIds.value.push(platformId);
};

// Save logic
const getSubmitData = () => {
    const platforms = post.value.post_platforms
        .filter((pp) => selectedPlatformIds.value.includes(pp.id))
        .map((pp) => ({
            id: pp.id,
            content_type: platformContentTypes.value[pp.id] ?? pp.content_type,
            meta: platformMeta.value[pp.id] ?? pp.meta ?? {},
        }));

    return {
        content: content.value,
        media: media.value,
        platforms,
        scheduled_at: date.formatLocalDateTimeForApi(scheduledDateTime.value),
    };
};

const save = () => {
    if (isSubmitting.value || isLocked.value || isSaving.value) return;

    const data = getSubmitData();

    isSaving.value = true;
    showSaved.value = false;

    router.put(
        updatePost.url(post.value.id),
        {
            status: post.value.status,
            ...data,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showSaved.value = true;
                setTimeout(() => {
                    showSaved.value = false;
                }, 2000);
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
};

const debouncedSave = debounce(() => {
    if (!isLocked.value && !isSubmitting.value) {
        save();
    }
}, 1500);

const triggerAutosave = () => {
    if (!isLocked.value) {
        showSaved.value = false;
        debouncedSave();
    }
};

watch(
    [
        content,
        media,
        selectedPlatformIds,
        scheduledDateTime,
        platformMeta,
        platformContentTypes,
    ],
    triggerAutosave,
    { deep: true },
);

onUnmounted(() => {
    debouncedSave.cancel();
});

const submit = (status: string = PostStatus.Scheduled) => {
    if (isSubmitting.value || isReadOnly.value) return;
    debouncedSave.cancel();

    const data = getSubmitData();
    isSubmitting.value = true;

    router.put(
        updatePost.url(post.value.id),
        {
            status,
            ...data,
        },
        {
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const deletePost = () => {
    if (isReadOnly.value) return;
    deleteModal.value?.open({
        url: destroyPost.url(post.value.id),
        confirmText: trans('common.confirm_modal.delete_keyword'),
    });
};

const unschedulePost = () => {
    if (isReadOnly.value || isSubmitting.value) return;
    debouncedSave.cancel();
    scheduledDateTime.value = '';
    hasPickedTime.value = false;
    submit(PostStatus.Draft);
};

usePollWhile(() => post.value.status === PostStatus.Publishing, {
    only: ['post'],
});
</script>

<template>
    <Head :title="$t('posts.edit.title')" />

    <AppLayout :full-width="true">
        <div class="flex min-h-0 flex-1 flex-col">
            <!-- On mobile the status moves into the switcher and the actions into the
                 bottom bar, so the header is desktop-only — except the scheduled banner,
                 which stays as the locked-state explanation. -->
            <div :class="isScheduled ? 'shrink-0' : 'hidden shrink-0 lg:block'">
                <PostEditorHeader
                    :post="post"
                    :is-saving="isSaving"
                    :show-saved="showSaved"
                    :is-submitting="isSubmitting"
                    :is-post-action-disabled="isPostActionDisabled"
                    :post-action-tooltip="postActionTooltip"
                    :pick-time-label="pickTimeLabel"
                    v-model:has-picked-time="hasPickedTime"
                    v-model:scheduled-date-time="scheduledDateTime"
                    @delete="deletePost"
                    @unschedule="unschedulePost"
                    @submit="submit"
                />
            </div>

            <PostEditorMobileNav
                v-model:active-view="mobileView"
                :status="post.status"
            />

            <div class="relative flex-1 overflow-hidden">
                <div
                    v-if="isPublishing"
                    class="absolute inset-0 z-40 flex flex-col items-center justify-center gap-4 bg-background/80 backdrop-blur-sm"
                >
                    <div
                        class="inline-flex size-14 -rotate-3 items-center justify-center rounded-2xl border-2 border-foreground bg-violet-200 shadow-2xs"
                    >
                        <IconLoader2
                            class="size-7 animate-spin text-foreground"
                            stroke-width="2"
                        />
                    </div>
                    <div class="text-center">
                        <p
                            class="text-2xl leading-tight font-semibold text-foreground"
                            style="font-family: var(--font-display)"
                        >
                            {{ $t('posts.edit.publishing_overlay_title') }}
                        </p>
                        <p class="mt-1 text-sm text-foreground/70">
                            {{ $t('posts.edit.publishing_overlay_subtitle') }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex h-full"
                    :class="{
                        'pointer-events-none opacity-60 select-none':
                            isScheduled,
                    }"
                >
                    <div
                        class="w-full overflow-y-auto lg:w-2/3 lg:border-r-2 lg:border-foreground"
                        :class="{ 'hidden lg:block': mobileView !== 'compose' }"
                    >
                        <PostEditorComposer
                            v-model:content="content"
                            v-model:media="media"
                            :platform-limits="platformLimits"
                            :media-issues="mediaIssues"
                        />
                    </div>

                    <div
                        class="w-full overflow-hidden lg:block lg:w-1/3"
                        :class="{ 'hidden lg:block': mobileView === 'compose' }"
                    >
                        <PostEditorTabs
                            v-model:active-tab="activeTab"
                            :post="post"
                            :workspace-id="workspace.id"
                            :content="content"
                            :media="media"
                            :selected-platform-ids="selectedPlatformIds"
                            :platform-meta="platformMeta"
                            :platform-content-types="platformContentTypes"
                            :platform-issues="platformIssues"
                            :platform-configs="platformConfigs"
                            :tiktok-creator-infos="tiktokCreatorInfos"
                            :is-read-only="isLocked"
                            :posted-at="scheduledDateTime || null"
                            @toggle-platform="togglePlatform"
                            @update:platform-meta="updatePlatformMeta"
                            @update:platform-content-type="
                                updatePlatformContentType
                            "
                        />
                    </div>
                </div>
            </div>

            <PostEditorActionBar
                :is-read-only="isReadOnly"
                :is-scheduled="isScheduled"
                :is-saving="isSaving"
                :is-submitting="isSubmitting"
                :is-post-action-disabled="isPostActionDisabled"
                :post-action-tooltip="postActionTooltip"
                :pick-time-label="pickTimeLabel"
                v-model:has-picked-time="hasPickedTime"
                v-model:scheduled-date-time="scheduledDateTime"
                @delete="deletePost"
                @unschedule="unschedulePost"
                @submit="submit"
            />
        </div>
    </AppLayout>

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('posts.delete.title')"
        :description="$t('posts.delete.description')"
        :action="$t('posts.delete.confirm')"
        :cancel="$t('posts.delete.cancel')"
    />
</template>
