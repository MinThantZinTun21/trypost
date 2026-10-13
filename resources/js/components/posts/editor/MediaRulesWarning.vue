<script setup lang="ts">
import { IconAlertTriangle, IconExternalLink } from '@tabler/icons-vue';
import { computed } from 'vue';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { getMediaValidationWarning } from '@/composables/useMedia';
import { mediaLimitsDocsUrl } from '@/lib/docs';
import type { MediaItem } from '@/types/media';

const props = defineProps<{
    contentType: string;
    media: MediaItem[];
    platform: string;
}>();

const warning = computed(() =>
    getMediaValidationWarning(props.contentType, props.media),
);
</script>

<template>
    <Alert
        v-if="warning"
        variant="destructive"
        class="px-3 py-2"
        data-testid="media-rules-warning"
    >
        <IconAlertTriangle />
        <AlertDescription class="block text-xs font-semibold">
            {{ $t(`posts.form.warnings.${warning.key}`, warning.params) }}
            <a
                :href="mediaLimitsDocsUrl(platform)"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-0.5 underline underline-offset-2"
            >
                {{ $t('posts.edit.compliance.media_limits_docs') }}
                <IconExternalLink class="size-3" />
            </a>
        </AlertDescription>
    </Alert>
</template>
