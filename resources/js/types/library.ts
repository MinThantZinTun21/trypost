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
    posts_count?: number;
    created_via: 'web' | 'mcp';
    created_at: string;
    updated_at: string;
}

export interface LibrarySocialAccount {
    id: string;
    platform: 'facebook' | 'tiktok' | 'youtube';
    display_name: string | null;
    username: string | null;
}
