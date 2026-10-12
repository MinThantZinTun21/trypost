import type { MediaItem } from '@/types/media';

export interface LibraryItem {
    id: string;
    name: string;
    media: MediaItem[];
    facebook_text: string | null;
    tiktok_caption: string | null;
    youtube_title: string | null;
    youtube_description: string | null;
    youtube_tags: string[];
    created_via: 'web' | 'mcp';
    created_at: string;
    updated_at: string;
}
