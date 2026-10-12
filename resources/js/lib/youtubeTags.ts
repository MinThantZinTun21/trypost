/**
 * Mirrors App\Support\YouTubeTags: YouTube allows 500 characters for the whole
 * list, counting the commas between tags and the quotes around a tag with a space.
 */
export const YOUTUBE_TAGS_MAX_CHARACTERS = 500;

export const parseYouTubeTags = (text: string): string[] => {
    const seen = new Set<string>();

    return text
        .split(',')
        .map((tag) => tag.replace(/\s+/g, ' ').trim().replace(/^#+/, '').trim())
        .filter((tag) => {
            const key = tag.toLowerCase();

            if (tag === '' || seen.has(key)) {
                return false;
            }

            seen.add(key);

            return true;
        });
};

export const youtubeTagsCharacters = (tags: string[]): number =>
    tags.length === 0
        ? 0
        : tags.reduce(
              (total, tag) =>
                  total + [...tag].length + (tag.includes(' ') ? 2 : 0),
              0,
          ) +
          tags.length -
          1;

export const toYouTubeTags = (value: unknown): string[] =>
    Array.isArray(value)
        ? value.filter((tag): tag is string => typeof tag === 'string')
        : [];
