export const YOUTUBE_TITLE_MAX_CHARACTERS = 100;

/**
 * Mirror of App\Support\YouTubeTitle::custom(): the Owner's own Title,
 * trimmed, or null when it is not set.
 */
export const customYouTubeTitle = (meta: unknown): string | null => {
    const title = (meta as Record<string, unknown> | null | undefined)?.title;

    return typeof title === 'string' && title.trim() !== ''
        ? title.trim()
        : null;
};

export const youtubeTitleCharacters = (title: string): number =>
    [...title].length;
