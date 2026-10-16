<script setup lang="ts">
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';
import {
    getPlatformStatusConfig,
    getPostStatusConfig,
} from '@/composables/usePostStatus';

const props = withDefaults(
    defineProps<{
        status: string;
        /** A Post status, or one Social account's (Post platform) status. */
        kind?: 'post' | 'platform';
        /** Icon only, with the label for screen readers and on hover. */
        iconOnly?: boolean;
    }>(),
    { kind: 'post', iconOnly: false },
);

const config = computed(() =>
    props.kind === 'platform'
        ? getPlatformStatusConfig(props.status)
        : getPostStatusConfig(props.status),
);
</script>

<template>
    <Badge
        :variant="config.variant"
        :title="iconOnly ? config.label : undefined"
        :data-status="status"
    >
        <component :is="config.icon" aria-hidden="true" />
        <span :class="{ 'sr-only': iconOnly }">{{ config.label }}</span>
    </Badge>
</template>
