import type { ReloadOptions } from '@inertiajs/core';
import { usePoll } from '@inertiajs/vue3';
import type { WatchSource } from 'vue';
import { watch } from 'vue';

export const PUBLISHING_POLL_INTERVAL_MS = 5000;

/**
 * Reloads the given props on an interval only while `active` is true, and
 * stops as soon as it turns false. Replaces live websocket updates: a page
 * showing a publishing post refreshes until the post settles, and does no
 * polling at all otherwise.
 */
export const usePollWhile = (
    active: WatchSource<boolean>,
    reloadOptions: ReloadOptions,
    intervalMs: number = PUBLISHING_POLL_INTERVAL_MS,
) => {
    const { start, stop } = usePoll(intervalMs, reloadOptions, {
        autoStart: false,
        mode: 'rest',
    });

    watch(
        active,
        (isActive) => {
            if (isActive) {
                start();
            } else {
                stop();
            }
        },
        { immediate: true },
    );

    return { start, stop };
};
