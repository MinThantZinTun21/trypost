<script setup lang="ts">
import { computed } from 'vue';

import PlatformLogo from '@/components/PlatformLogo.vue';
import PostStatusBadge from '@/components/posts/PostStatusBadge.vue';
import { getPlatformLabel } from '@/composables/usePlatformLogo';
import type { CalendarPostPlatform } from '@/types/calendar';

const props = withDefaults(
    defineProps<{
        postPlatforms: CalendarPostPlatform[];
        limit?: number;
        showHandle?: boolean;
        /** Each account's own status: pending, published, failed, … */
        showStatus?: boolean;
    }>(),
    { limit: 4, showHandle: false, showStatus: false },
);

const visiblePlatforms = computed(() =>
    props.postPlatforms.slice(0, props.limit),
);

const hiddenCount = computed(() =>
    Math.max(0, props.postPlatforms.length - props.limit),
);

const accountName = (postPlatform: CalendarPostPlatform): string =>
    postPlatform.social_account?.display_label ??
    getPlatformLabel(postPlatform.platform);
</script>

<template>
    <ul class="space-y-1" data-testid="calendar-post-accounts">
        <li
            v-for="postPlatform in visiblePlatforms"
            :key="postPlatform.id"
            class="flex min-w-0 items-center gap-1.5"
            data-testid="calendar-post-account"
        >
            <PlatformLogo :platform="postPlatform.platform" size="xs" plain />
            <span class="truncate font-semibold">
                {{ accountName(postPlatform) }}
            </span>
            <span
                v-if="showHandle && postPlatform.social_account?.username"
                class="truncate text-muted-foreground"
            >
                @{{ postPlatform.social_account.username }}
            </span>
            <PostStatusBadge
                v-if="showStatus"
                :status="postPlatform.status"
                kind="platform"
                icon-only
                class="ml-auto"
                data-testid="calendar-post-account-status"
            />
        </li>
        <li v-if="hiddenCount > 0" class="text-muted-foreground">
            {{ $t('calendar.more', { count: String(hiddenCount) }) }}
        </li>
    </ul>
</template>
