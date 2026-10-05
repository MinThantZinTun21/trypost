<script setup lang="ts">
import { computed } from 'vue';

import PreviewTab from '@/components/posts/editor/PreviewTab.vue';
import ScheduleTab from '@/components/posts/editor/ScheduleTab.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { PlatformIssue } from '@/composables/usePostCompliance';
import type { MediaItem } from '@/types/media';
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
    post_platforms: PostPlatform[];
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
    post: Post;
    workspaceId: string;
    content: string;
    media: MediaItem[];
    selectedPlatformIds: string[];
    platformMeta: Record<string, Record<string, any>>;
    platformContentTypes: Record<string, string>;
    platformIssues: Record<string, PlatformIssue>;
    platformConfigs: Record<string, any>;
    tiktokCreatorInfos?: Record<string, TikTokCreatorInfo> | null;
    isReadOnly: boolean;
    postedAt?: string | null;
}>();

const activeTab = defineModel<string>('activeTab', { required: true });

const emit = defineEmits<{
    (e: 'toggle-platform', platformId: string): void;
    (
        e: 'update:platformMeta',
        platformId: string,
        meta: Record<string, any>,
    ): void;
    (
        e: 'update:platformContentType',
        platformId: string,
        contentType: string,
    ): void;
}>();

const previewablePlatforms = computed(() =>
    props.post.post_platforms.filter((pp) =>
        props.selectedPlatformIds.includes(pp.id),
    ),
);
</script>

<template>
    <Tabs v-model="activeTab" class="flex h-full flex-col">
        <TabsList
            class="mx-4 mt-4 hidden w-fit shrink-0 self-start lg:inline-flex"
        >
            <TabsTrigger value="preview" data-testid="editor-tab-preview">{{
                $t('posts.edit.tabs.preview')
            }}</TabsTrigger>
            <TabsTrigger value="schedule" data-testid="editor-tab-channels">{{
                $t('posts.edit.tabs.channels')
            }}</TabsTrigger>
        </TabsList>

        <TabsContent value="preview" class="flex-1 overflow-y-auto">
            <PreviewTab
                :platforms="previewablePlatforms"
                :content="content"
                :media="media"
                :platform-content-types="platformContentTypes"
                :platform-meta="platformMeta"
                :posted-at="postedAt"
            />
        </TabsContent>

        <TabsContent
            value="schedule"
            force-mount
            data-testid="channels-panel"
            :class="[
                'flex-1 overflow-y-auto p-4',
                { hidden: activeTab !== 'schedule' },
            ]"
        >
            <ScheduleTab
                :post-platforms="post.post_platforms"
                :selected-platform-ids="selectedPlatformIds"
                :is-read-only="isReadOnly"
                :platform-configs="platformConfigs"
                :platform-meta="platformMeta"
                :platform-content-types="platformContentTypes"
                :platform-issues="platformIssues"
                :tiktok-creator-infos="tiktokCreatorInfos"
                :media="media"
                @toggle-platform="(id) => emit('toggle-platform', id)"
                @update:platform-meta="
                    (id, meta) => emit('update:platformMeta', id, meta)
                "
                @update:platform-content-type="
                    (id, contentType) =>
                        emit('update:platformContentType', id, contentType)
                "
            />
        </TabsContent>
    </Tabs>
</template>
