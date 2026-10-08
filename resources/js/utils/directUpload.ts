import { classifyBy, MediaType } from '@/lib/mediaType';
import { probeVideoDuration } from '@/lib/videoDuration';
import type { ChunkedUploadResult } from '@/utils/chunkedUpload';

interface DirectUploadOptions {
    file: File;
    startUrl: string;
    completeUrl: string;
    onProgress?: (progress: number) => void;
}

interface DirectUploadTicket {
    direct: boolean;
    url?: string;
    headers?: Record<string, string>;
}

const postJson = async <T>(url: string, body: object): Promise<T> => {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN':
                document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content ?? '',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) throw new Error(`Upload failed: ${response.status}`);

    return response.json();
};

const putWithProgress = (
    file: File,
    url: string,
    headers: Record<string, string>,
    onProgress?: (progress: number) => void,
): Promise<void> =>
    new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', url);
        Object.entries(headers).forEach(([name, value]) =>
            xhr.setRequestHeader(name, value),
        );
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable)
                onProgress?.(Math.round((event.loaded / event.total) * 100));
        };
        xhr.onload = () =>
            xhr.status >= 200 && xhr.status < 300
                ? resolve()
                : reject(new Error(`Upload failed: ${xhr.status}`));
        xhr.onerror = () => reject(new Error('Upload failed'));
        xhr.send(file);
    });

/**
 * Sends the file straight to object storage with a presigned PUT, so it never
 * passes through the app's request body limit. Resolves to null when the
 * server stores media locally, and the caller falls back to chunked upload.
 */
export const uploadDirect = async (
    options: DirectUploadOptions,
): Promise<ChunkedUploadResult | null> => {
    const { file, startUrl, completeUrl, onProgress } = options;
    const uploadId = crypto.randomUUID();

    const ticket = await postJson<DirectUploadTicket>(startUrl, {
        file_name: file.name,
        total_size: file.size,
        upload_id: uploadId,
    });

    if (!ticket.direct || !ticket.url) return null;

    // The server reads the duration from the file; the browser value is the fallback for containers without one.
    const isVideo = classifyBy(file.type, file.name) === MediaType.Video;
    const duration = isVideo ? await probeVideoDuration(file) : null;

    await putWithProgress(file, ticket.url, ticket.headers ?? {}, onProgress);

    return postJson<ChunkedUploadResult>(completeUrl, {
        upload_id: uploadId,
        duration: duration?.toFixed(2) ?? null,
    });
};
