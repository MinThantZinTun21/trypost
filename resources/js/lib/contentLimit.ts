import { customYouTubeTitle } from '@/lib/youtubeTitle';
import { Platform } from '@/types/platform';

/**
 * Mirror of PostPlatformMetaRules::contentLimitApplies(): whether the Post's
 * content counts against the platform's content cap. A YouTube Title replaces
 * the content as the video title, so the cap no longer applies.
 */
export const contentLimitApplies = (
    platform: string,
    meta: Record<string, unknown> | undefined,
): boolean =>
    platform === Platform.YouTube ? customYouTubeTitle(meta) === null : true;
