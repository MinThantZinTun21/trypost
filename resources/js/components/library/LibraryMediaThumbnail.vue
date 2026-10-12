<script setup lang="ts">
import { IconPlayerPlayFilled } from '@tabler/icons-vue';

import { isVideo } from '@/lib/mediaType';
import type { MediaItem } from '@/types/media';

defineProps<{
    media: MediaItem;
}>();
</script>

<template>
    <div class="relative h-full w-full">
        <video
            v-if="isVideo(media)"
            :src="media.url"
            class="h-full w-full object-cover"
            muted
            preload="metadata"
        />
        <img
            v-else
            :src="media.url"
            :alt="media.original_filename"
            class="h-full w-full object-cover"
            loading="lazy"
        />
        <span
            v-if="isVideo(media)"
            class="pointer-events-none absolute inset-0 flex items-center justify-center"
        >
            <span
                class="inline-flex size-8 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur-sm"
            >
                <IconPlayerPlayFilled class="size-4" />
            </span>
        </span>
    </div>
</template>
