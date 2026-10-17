import { trans } from 'laravel-vue-i18n';
import { ref, type Ref } from 'vue';
import { toast } from 'vue-sonner';

import { completeDirect, storeChunked, storeDirect } from '@/routes/app/assets';
import type { MediaItem } from '@/types/media';
import { uploadChunked } from '@/utils/chunkedUpload';
import { uploadDirect } from '@/utils/directUpload';

/**
 * Uploads files as workspace assets and appends each one to `media` as it
 * finishes. Uploads go straight to object storage when the server offers it
 * (no request body limit), otherwise through the chunked endpoint.
 */
export const useMediaUpload = (media: Ref<MediaItem[]>) => {
    const uploading = ref(false);
    const uploadProgress = ref(0);

    const uploadFiles = async (files: File[]) => {
        if (uploading.value || files.length === 0) return;
        uploading.value = true;

        for (const file of files) {
            uploadProgress.value = 0;
            try {
                const onProgress = (progress: number) => {
                    uploadProgress.value = progress;
                };
                const uploaded =
                    (await uploadDirect({
                        file,
                        startUrl: storeDirect.url(),
                        completeUrl: completeDirect.url(),
                        onProgress,
                    })) ??
                    (await uploadChunked({
                        file,
                        url: storeChunked.url(),
                        collection: 'assets',
                        onProgress,
                    }));
                media.value = [
                    ...media.value,
                    {
                        id: uploaded.id,
                        path: uploaded.path,
                        url: uploaded.url,
                        type: uploaded.type,
                        mime_type: uploaded.mime_type,
                        original_filename: uploaded.original_filename,
                        size: uploaded.size,
                        meta: uploaded.meta ?? undefined,
                    },
                ];
            } catch {
                toast.error(trans('assets.upload.failed', { file: file.name }));
            }
        }

        uploading.value = false;
    };

    return { uploading, uploadProgress, uploadFiles };
};
