const PLATFORM_LOGOS: Record<string, string> = {
    tiktok: '/images/accounts/tiktok.png',
    facebook: '/images/accounts/facebook.png',
    youtube: '/images/accounts/youtube.png',
};

const PLATFORM_LABELS: Record<string, string> = {
    tiktok: 'TikTok',
    facebook: 'Facebook',
    youtube: 'YouTube',
};

const PLATFORM_CONTENT_TYPES: Record<string, string[]> = {
    facebook: ['facebook_post', 'facebook_reel', 'facebook_story'],
    tiktok: ['tiktok_video'],
    youtube: ['youtube_short'],
};

export interface ContentTypeOption {
    value: string;
    labelKey: string;
}

const PLATFORM_THEMES: Record<string, { bg: string; rotate: string }> = {
    facebook: { bg: 'bg-sky-200', rotate: 'rotate-1' },
    tiktok: { bg: 'bg-fuchsia-200', rotate: '-rotate-1' },
    youtube: { bg: 'bg-red-200', rotate: 'rotate-1' },
};

export const getPlatformLogo = (platform: string): string =>
    PLATFORM_LOGOS[platform] ?? PLATFORM_LOGOS.facebook;

export const getPlatformTheme = (
    platform: string,
): { bg: string; rotate: string; image: string } => ({
    ...(PLATFORM_THEMES[platform] ?? { bg: 'bg-muted', rotate: '' }),
    image: getPlatformLogo(platform),
});

export const getPlatformLabel = (platform: string): string =>
    PLATFORM_LABELS[platform] ?? platform;

const translationKeyFor = (contentType: string): string =>
    `posts.content_types.${contentType}.label`;

export const getContentTypeOptions = (platform: string): ContentTypeOption[] =>
    (PLATFORM_CONTENT_TYPES[platform] ?? []).map((value) => ({
        value,
        labelKey: translationKeyFor(value),
    }));

/** Whether the user picks a format on this platform, or it only has one. */
export const hasMultipleContentTypes = (platform: string): boolean =>
    getContentTypeOptions(platform).length > 1;

/**
 * Translation key for the badge that names a published format, or null when
 * the format was never a choice: tagging "Short" on YouTube would just repeat
 * the platform name.
 */
export const getContentTypeBadgeKey = (
    platform: string,
    contentType: string | null,
): string | null =>
    contentType && hasMultipleContentTypes(platform)
        ? translationKeyFor(contentType)
        : null;
