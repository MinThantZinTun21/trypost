import { Platform, type PlatformValue } from '@/types/platform';

export const DOCS_URL = 'https://docs.trypost.it';

// Anchors of the per-network sections in the media knowledge-base page.
const MEDIA_LIMITS_ANCHOR: Record<PlatformValue, string> = {
    [Platform.Facebook]: 'facebook',
    [Platform.TikTok]: 'tiktok',
    [Platform.YouTube]: 'youtube',
};

export const mediaLimitsDocsUrl = (platform: string): string => {
    const anchor: string | undefined =
        MEDIA_LIMITS_ANCHOR[platform as PlatformValue];

    return `${DOCS_URL}/knowledge-base/media${anchor ? `#${anchor}` : ''}`;
};
