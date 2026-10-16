import { usePage } from '@inertiajs/vue3';

/**
 * True when TikTok posts go to the Owner's Telegram channel instead of
 * TikTok (ADR 0009), so TikTok's own publish settings do not apply.
 */
export const tiktokViaTelegram = (): boolean =>
    Boolean(usePage().props.tiktokViaTelegram);
