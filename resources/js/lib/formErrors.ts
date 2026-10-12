/**
 * The first error for a field or any of its array entries, so `media_ids`
 * also shows the message Laravel keyed as `media_ids.0`.
 */
export const errorForField = (
    errors: Record<string, string>,
    field: string,
): string | undefined =>
    Object.entries(errors).find(
        ([key]) => key === field || key.startsWith(`${field}.`),
    )?.[1];
