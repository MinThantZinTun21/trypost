import type { MediaType } from '@/lib/mediaType';

export interface MediaItem {
    id: string;
    url: string;
    path?: string;
    type?: MediaType;
    mime_type?: string;
    original_filename?: string;
    size?: number;
    meta?: {
        width?: number;
        height?: number;
        duration?: number;
        alt_text?: string;
    };
}
