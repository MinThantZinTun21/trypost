// Mirror of App\Support\TikTokText. TikTok counts these limits in UTF-16
// runes, which is what a JavaScript string's length measures.
export const TIKTOK_CAPTION_MAX = 2200;
export const TIKTOK_PHOTO_TITLE_MAX = 90;
export const TIKTOK_PHOTO_DESCRIPTION_MAX = 4000;

export const tiktokTextLength = (text: string): number => text.length;

/**
 * A trimmed text field from the platform's meta, or null when blank.
 */
export const filledTikTokText = (
    meta: Record<string, unknown> | undefined,
    key: string,
): string | null => {
    const value = meta?.[key];

    return typeof value === 'string' && value.trim() !== ''
        ? value.trim()
        : null;
};
