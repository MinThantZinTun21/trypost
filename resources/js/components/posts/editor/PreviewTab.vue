<script setup lang="ts">
import { IconDeviceMobile } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import PhoneMockup from '@/components/PhoneMockup.vue';
import { PlatformPreview } from '@/components/posts/previews';
import { Avatar } from '@/components/ui/avatar';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import type { MediaItem } from '@/types/media';

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
    platform: string;
    platform_name: string | null;
    platform_avatar: string | null;
    content_type: string | null;
    social_account: SocialAccount | null;
}

const props = defineProps<{
    platforms: PostPlatform[];
    content: string;
    media: MediaItem[];
    platformContentTypes: Record<string, string>;
    platformMeta?: Record<string, Record<string, any>>;
    postedAt?: string | null;
}>();

const getPlatformAvatar = (pp: PostPlatform): string | null =>
    pp.social_account?.avatar_url ?? pp.platform_avatar ?? null;
const getPlatformDisplayName = (pp: PostPlatform): string =>
    pp.social_account?.display_label ?? pp.platform_name ?? pp.platform;

const activeId = ref<string | null>(props.platforms[0]?.id ?? null);

watch(
    () => props.platforms,
    (next) => {
        if (!next.find((pp) => pp.id === activeId.value)) {
            activeId.value = next[0]?.id ?? null;
        }
    },
);

const activePlatform = computed(
    () => props.platforms.find((pp) => pp.id === activeId.value) ?? null,
);
const activeContentType = computed((): string | undefined => {
    if (!activePlatform.value) return undefined;
    return (
        props.platformContentTypes[activePlatform.value.id] ??
        activePlatform.value.content_type ??
        undefined
    );
});
</script>

<template>
    <div class="flex h-full flex-col">
        <div
            v-if="platforms.length > 1"
            class="border-b border-border px-4 py-3"
        >
            <div class="flex flex-wrap gap-3">
                <TooltipProvider
                    v-for="pp in platforms"
                    :key="pp.id"
                    :delay-duration="200"
                >
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                class="relative cursor-pointer transition-opacity"
                                :class="
                                    activeId === pp.id
                                        ? 'opacity-100'
                                        : 'opacity-40 hover:opacity-70'
                                "
                                :data-testid="`preview-platform-${pp.id}`"
                                @click="activeId = pp.id"
                            >
                                <Avatar
                                    :src="getPlatformAvatar(pp)"
                                    :name="getPlatformDisplayName(pp)"
                                    class="size-9 shrink-0 rounded-full border"
                                    :class="
                                        activeId === pp.id
                                            ? 'border-border shadow-2xs'
                                            : 'border-foreground/20'
                                    "
                                />
                                <span
                                    class="absolute -right-1 -bottom-1 inline-flex size-5 items-center justify-center overflow-hidden rounded-full border border-border bg-card shadow-2xs lg:size-4"
                                >
                                    <img
                                        :src="getPlatformLogo(pp.platform)"
                                        :alt="pp.platform"
                                        class="size-full object-cover"
                                    />
                                </span>
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <div class="space-y-0.5 text-xs">
                                <p class="font-semibold">
                                    {{ getPlatformDisplayName(pp)
                                    }}<span
                                        v-if="pp.social_account?.username"
                                        class="font-normal opacity-80"
                                        >&nbsp;·&nbsp;@{{
                                            pp.social_account.username
                                        }}</span
                                    >
                                </p>
                                <p class="opacity-70">
                                    {{ getPlatformLabel(pp.platform) }}
                                </p>
                            </div>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </div>

        <div
            class="flex flex-1 items-start justify-center overflow-x-hidden px-4 pt-6 pb-8"
        >
            <PhoneMockup v-if="activePlatform">
                <PlatformPreview
                    :platform="activePlatform.platform"
                    :content="content"
                    :media="media"
                    :social-account="activePlatform.social_account"
                    :content-type="activeContentType"
                    :meta="platformMeta?.[activePlatform.id] ?? {}"
                    :posted-at="postedAt"
                />
            </PhoneMockup>
            <Empty v-else class="flex-none gap-3 p-0 md:p-0">
                <EmptyHeader class="gap-3">
                    <EmptyMedia
                        class="mb-0 size-12 rounded-none bg-primary text-primary-foreground"
                    >
                        <IconDeviceMobile class="size-6" stroke-width="2" />
                    </EmptyMedia>
                    <EmptyTitle
                        class="text-base font-bold tracking-normal text-foreground"
                        style="font-family: var(--font-display)"
                    >
                        {{ $t('posts.edit.preview_empty.title') }}
                    </EmptyTitle>
                    <EmptyDescription
                        class="text-xs font-medium text-foreground/60"
                    >
                        {{ $t('posts.edit.preview_empty.description') }}
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        </div>
    </div>
</template>
