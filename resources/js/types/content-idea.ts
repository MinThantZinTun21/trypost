import type { ContentIdeaStatus } from '@/composables/useContentIdeaStatus';

export interface ContentIdeaSummary {
    id: string;
    title: string;
    excerpt: string;
    status: ContentIdeaStatus;
    created_via: 'web' | 'mcp';
    created_at: string;
    updated_at: string;
}
