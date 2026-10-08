/**
 * Mirror of App\Support\PostPlatformText::trimmed(): the trimmed text under
 * `key` in a platform's meta, or null when it is missing or blank.
 */
export const trimmedPostPlatformText = (
    meta: unknown,
    key: string,
): string | null => {
    const value = (meta as Record<string, unknown> | null | undefined)?.[key];

    return typeof value === 'string' && value.trim() !== ''
        ? value.trim()
        : null;
};
