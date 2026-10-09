import {
    IconCircleCheck,
    IconCircleDashed,
    IconProgress,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';

export const CONTENT_IDEA_STATUSES = ['new', 'in_progress', 'done'] as const;

export type ContentIdeaStatus = (typeof CONTENT_IDEA_STATUSES)[number];

type BadgeVariant = 'outline' | 'warning' | 'success';

interface ContentIdeaStatusConfig {
    variant: BadgeVariant;
    icon: typeof IconCircleDashed;
    label: string;
}

const CONFIGS: Record<
    ContentIdeaStatus,
    Pick<ContentIdeaStatusConfig, 'variant' | 'icon'>
> = {
    new: { variant: 'outline', icon: IconCircleDashed },
    in_progress: { variant: 'warning', icon: IconProgress },
    done: { variant: 'success', icon: IconCircleCheck },
};

export const getContentIdeaStatusConfig = (
    status: ContentIdeaStatus,
): ContentIdeaStatusConfig => ({
    ...CONFIGS[status],
    label: trans(`ideas.status.${status}`),
});
