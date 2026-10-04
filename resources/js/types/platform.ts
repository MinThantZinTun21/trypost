export const Platform = {
    TikTok: 'tiktok',
    YouTube: 'youtube',
    Facebook: 'facebook',
} as const;

export type PlatformValue = (typeof Platform)[keyof typeof Platform];
