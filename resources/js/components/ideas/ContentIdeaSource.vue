<script setup lang="ts">
import { IconRobot } from '@tabler/icons-vue';

import type { ContentIdeaSummary } from '@/types/content-idea';

defineProps<{
    createdVia: ContentIdeaSummary['created_via'];
}>();
</script>

<template>
    <span class="flex items-center gap-1">
        <IconRobot v-if="createdVia === 'mcp'" class="size-3.5" />
        {{ $t(`ideas.created_via.${createdVia}`) }}
    </span>
</template>
