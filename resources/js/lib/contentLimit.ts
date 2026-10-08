import { trimmedPostPlatformText } from '@/lib/postPlatformText';
import { ContentType } from '@/types/content-type';
import { Platform } from '@/types/platform';

/**
 * Mirror of PostPlatformMetaRules::contentLimitApplies(): whether the Post's
 * content counts against the platform's content cap. Text that replaces the
 * content lifts it: a YouTube Title, a TikTok caption on a Video and a TikTok
 * Description on a Photo.
 */
export const contentLimitApplies = (
    platform: string,
    contentType: string | null | undefined,
    meta: Record<string, unknown> | undefined,
): boolean => {
    if (platform === Platform.YouTube) {
        return trimmedPostPlatformText(meta, 'title') === null;
    }

    if (
        platform === Platform.TikTok &&
        contentType === ContentType.TikTokVideo
    ) {
        return trimmedPostPlatformText(meta, 'caption') === null;
    }

    if (
        platform === Platform.TikTok &&
        contentType === ContentType.TikTokPhoto
    ) {
        return trimmedPostPlatformText(meta, 'description') === null;
    }

    return true;
};
