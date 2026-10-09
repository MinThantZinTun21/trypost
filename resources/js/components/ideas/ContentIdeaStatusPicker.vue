<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconCheck, IconChevronDown } from '@tabler/icons-vue';
import { ref } from 'vue';

import { updateStatus } from '@/actions/App/Http/Controllers/App/ContentIdeaController';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    CONTENT_IDEA_STATUSES,
    type ContentIdeaStatus,
    getContentIdeaStatusConfig,
} from '@/composables/useContentIdeaStatus';

const props = defineProps<{
    ideaId: string;
    status: ContentIdeaStatus;
    reset?: string[];
}>();

const processing = ref(false);

const changeStatus = (status: ContentIdeaStatus) => {
    if (status === props.status) {
        return;
    }

    router.put(
        updateStatus.url(props.ideaId),
        { status },
        {
            preserveScroll: true,
            reset: props.reset ?? [],
            onStart: () => {
                processing.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child :disabled="processing" @click.stop>
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :aria-label="$t('ideas.change_status')"
                :data-testid="`idea-status-${ideaId}`"
            >
                <Badge :variant="getContentIdeaStatusConfig(status).variant">
                    <component
                        :is="getContentIdeaStatusConfig(status).icon"
                        class="size-3"
                    />
                    {{ getContentIdeaStatusConfig(status).label }}
                    <IconChevronDown class="size-3" />
                </Badge>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" @click.stop>
            <DropdownMenuLabel>{{
                $t('ideas.change_status')
            }}</DropdownMenuLabel>
            <DropdownMenuItem
                v-for="option in CONTENT_IDEA_STATUSES"
                :key="option"
                :data-testid="`idea-status-option-${option}`"
                @click="changeStatus(option)"
            >
                <component
                    :is="getContentIdeaStatusConfig(option).icon"
                    class="size-4"
                />
                {{ getContentIdeaStatusConfig(option).label }}
                <IconCheck v-if="option === status" class="ms-auto size-4" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
