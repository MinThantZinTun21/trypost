<script setup lang="ts">
import { computed } from 'vue';

import { getPlatformLabel } from '@/composables/usePlatformLogo';
import type { MediaItem } from '@/types/media';

import FacebookPreview from './FacebookPreview.vue';
import TikTokPreview from './TikTokPreview.vue';
import YouTubePreview from './YouTubePreview.vue';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    display_label: string;
    handle_label: string;
    avatar_url: string | null;
}

interface Props {
    platform: string;
    socialAccount: SocialAccount | null | undefined;
    content: string;
    media: MediaItem[];
    contentType?: string;
    meta?: Record<string, any>;
    /** Local scheduled datetime (datetime-local); falls back to now in previews. */
    postedAt?: string | null;
}

const props = defineProps<Props>();

const previewContent = computed((): string => props.content);

const resolvedSocialAccount = computed(
    (): SocialAccount =>
        props.socialAccount ?? {
            id: '',
            platform: props.platform,
            display_name: '',
            username: '',
            display_label: getPlatformLabel(props.platform),
            handle_label: getPlatformLabel(props.platform),
            avatar_url: null,
        },
);

const previewComponent = computed(() => {
    switch (props.platform) {
        case 'tiktok':
            return TikTokPreview;
        case 'youtube':
            return YouTubePreview;
        default:
            return FacebookPreview;
    }
});
</script>

<template>
    <component
        :is="previewComponent"
        :social-account="resolvedSocialAccount"
        :content="previewContent"
        :media="media"
        :content-type="contentType"
        :meta="meta"
        :posted-at="postedAt"
    />
</template>
