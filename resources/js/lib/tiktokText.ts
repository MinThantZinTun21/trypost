// Mirror of App\Support\TikTokText. TikTok counts these limits in UTF-16
// runes, which is what a JavaScript string's length measures.
export const TIKTOK_CAPTION_MAX = 2200;
export const TIKTOK_PHOTO_TITLE_MAX = 90;
export const TIKTOK_PHOTO_DESCRIPTION_MAX = 4000;

export const tiktokTextLength = (text: string): number => text.length;
