/**
 * Single source of truth for media-type detection on the frontend — the mirror
 * of the backend `App\Enums\Media\Type`. Every "is this an image / video / PDF?"
 * check goes through here so the answer can't drift between components.
 */

export const MediaType = {
    Image: 'image',
    Video: 'video',
    Document: 'document',
    // Only Task attachments in the Mobile app take audio; Posts never do.
    Audio: 'audio',
} as const;

export type MediaType = (typeof MediaType)[keyof typeof MediaType];

/** MIME allow-list we accept on upload — mirrors Type::allowedMimeTypes(). */
export const ALLOWED_MIME_TYPES: Record<MediaType, readonly string[]> = {
    [MediaType.Image]: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    [MediaType.Video]: ['video/mp4', 'video/quicktime'],
    [MediaType.Document]: ['application/pdf'],
    [MediaType.Audio]: [
        'audio/mp4',
        'audio/x-m4a',
        'audio/m4a',
        'audio/aac',
        'audio/mpeg',
    ],
};

/** The types a Post's media may be — mirrors Type::forPosts(). */
export const POST_MEDIA_TYPES: readonly MediaType[] = [
    MediaType.Image,
    MediaType.Video,
    MediaType.Document,
];

const GIF_MIME = 'image/gif';
const MOV_MIME = 'video/quicktime';
const PDF_MIME = 'application/pdf';

const MEDIA_TYPES = Object.values(MediaType);

const isMediaType = (value: unknown): value is MediaType =>
    MEDIA_TYPES.includes(value as MediaType);

// Broader than the upload allow-list so already-stored files in legacy formats
// still resolve. The backend (Type::fromExtension) consults the MIME registry;
// the browser has none, so this is the subset of formats we have seen stored.
const CLASSIFIABLE_EXTENSIONS: Record<MediaType, readonly string[]> = {
    [MediaType.Image]: [
        'jpg',
        'jpeg',
        'jfif',
        'png',
        'gif',
        'webp',
        'avif',
        'bmp',
        'tif',
        'tiff',
        'svg',
        'psd',
        'heic',
        'heif',
    ],
    [MediaType.Video]: [
        'mp4',
        'm4v',
        'mov',
        'avi',
        'wmv',
        'webm',
        'mkv',
        'mpeg',
        'mpg',
        '3gp',
        'flv',
        'ogv',
    ],
    [MediaType.Document]: ['pdf'],
    [MediaType.Audio]: [
        'm4a',
        'aac',
        'mp3',
        'wav',
        'oga',
        'opus',
        'flac',
        'weba',
    ],
};

/**
 * A `File.name`, a storage key or a full URL, lower-cased. Only an absolute
 * URL is parsed (to drop the host, `?query` and `#hash`); a bare filename is
 * taken as-is, so `Photo #3.jpg` keeps its extension.
 */
const pathOf = (nameOrPath: string | null | undefined): string => {
    const value = (nameOrPath ?? '').toLowerCase();

    if (!value.includes('://')) {
        return value;
    }

    try {
        return new URL(value).pathname;
    } catch {
        return '';
    }
};

/** Whether the path ends in one of the extensions. The dot is part of the match, so `clip` and `/v1.2/clip` match nothing. */
const hasExtension = (path: string, extensions: readonly string[]): boolean =>
    extensions.some((extension) => path.endsWith(`.${extension}`));

/** Lower-cased `type/subtype` with any `; codecs=…` parameters dropped, so comparisons are exact. */
const normalizeMime = (mime: string | null | undefined): string =>
    (mime ?? '').split(';')[0].trim().toLowerCase();

/** Image, video and audio own their MIME family (`image/*`, `video/*`, `audio/*`); Document is `application/pdf` alone. */
const ownsMime = (type: MediaType, mime: string): boolean =>
    type === MediaType.Document
        ? mime === PDF_MIME
        : mime.startsWith(`${type}/`);

/** The `accept` attribute value for a file input that takes any media a Post allows. */
export const acceptAttribute = (): string =>
    POST_MEDIA_TYPES.flatMap((type) => ALLOWED_MIME_TYPES[type]).join(',');

/** The structural shape every classifiable media item satisfies. */
interface ClassifiableMedia {
    type?: string | null;
    mime_type?: string | null;
    original_filename?: string | null;
    path?: string | null;
}

/** Resolve a MediaType from a raw MIME string (e.g. a browser `File.type`). */
export const fromMimeType = (
    mime: string | null | undefined,
): MediaType | null => {
    const normalized = normalizeMime(mime);

    return MEDIA_TYPES.find((type) => ownsMime(type, normalized)) ?? null;
};

/** Resolve a MediaType from a filename or path extension. */
export const fromExtension = (
    nameOrPath: string | null | undefined,
): MediaType | null => {
    const path = pathOf(nameOrPath);

    return (
        MEDIA_TYPES.find((type) =>
            hasExtension(path, CLASSIFIABLE_EXTENSIONS[type]),
        ) ?? null
    );
};

/**
 * Mirror of `Type::classify($mimeType, $path)`: a present MIME decides on its
 * own (an unrecognised one is null, never overridden by the extension); the
 * extension is consulted only when there is no MIME at all.
 */
export const classifyBy = (
    mime: string | null | undefined,
    nameOrPath: string | null | undefined,
): MediaType | null =>
    normalizeMime(mime) === '' ? fromExtension(nameOrPath) : fromMimeType(mime);

/**
 * Classify a media item. Trusts the server-assigned `type` first, then the MIME,
 * then falls back to the filename extension so already-stored items still
 * resolve. Returns null only when nothing identifies the item.
 */
export const classify = (
    item: ClassifiableMedia | null | undefined,
): MediaType | null => {
    if (!item) return null;

    return isMediaType(item.type)
        ? item.type
        : classifyBy(item.mime_type, item.original_filename ?? item.path);
};

export const isImage = (item: ClassifiableMedia | null | undefined): boolean =>
    classify(item) === MediaType.Image;

export const isVideo = (item: ClassifiableMedia | null | undefined): boolean =>
    classify(item) === MediaType.Video;

export const isDocument = (
    item: ClassifiableMedia | null | undefined,
): boolean => classify(item) === MediaType.Document;

/** Whether the item is an animated GIF — several platforms treat it specially. */
export const isGif = (item: ClassifiableMedia | null | undefined): boolean =>
    normalizeMime(item?.mime_type) === GIF_MIME;

export const isMov = (item: ClassifiableMedia | null | undefined): boolean =>
    normalizeMime(item?.mime_type) === MOV_MIME ||
    hasExtension(pathOf(item?.original_filename ?? item?.path), ['mov']);
