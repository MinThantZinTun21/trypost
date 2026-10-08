<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\Media\Type as MediaType;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use App\Support\VideoDurationProbe;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Browser-to-bucket uploads: hand out a presigned PUT URL, then register the
 * stored object once the browser reports it finished. Only available when the
 * default disk is object storage; otherwise the client falls back to the
 * chunked endpoint.
 */
final class DirectAssetReceiver
{
    private const CACHE_PREFIX = 'direct-asset-upload:';

    private const CACHE_TTL_HOURS = 6;

    public function __construct(
        private readonly ChunkedCloudUploader $cloud,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @return array{direct: bool, url?: string, headers?: array<string, string>}
     */
    public function start(User $user, string $fileName, string $uploadId): array
    {
        if (! $this->cloud->isObjectStorageDisk()) {
            return ['direct' => false];
        }

        $upload = $this->cloud->presignUpload($fileName);

        $this->cache->put($this->cacheKey($user, $uploadId), [
            'key' => data_get($upload, 'key'),
            'file_name' => $fileName,
        ], now()->addHours(self::CACHE_TTL_HOURS));

        return [
            'direct' => true,
            'url' => data_get($upload, 'url'),
            'headers' => data_get($upload, 'headers'),
        ];
    }

    public function complete(Workspace $workspace, User $user, string $uploadId, ?float $duration = null): ChunkReceipt
    {
        $upload = $this->cache->pull($this->cacheKey($user, $uploadId));
        $key = (string) data_get($upload, 'key', '');
        $size = $key === '' ? null : $this->cloud->storedSize($key);

        if ($size === null) {
            throw ValidationException::withMessages(['upload_id' => __('assets.upload.not_found')]);
        }

        $fileName = (string) data_get($upload, 'file_name');
        $mimeType = $this->cloud->storedMimeType($key, $size);
        $type = MediaType::classify($mimeType);

        if ($type === null) {
            $this->reject($key, __('assets.upload.unsupported'));
        }

        if ($size > $type->maxSizeInBytes()) {
            $this->reject($key, __('assets.upload.file_too_large', ['max' => $type->maxSizeInMb()]));
        }

        $media = $type === MediaType::Image
            ? $this->registerImage($workspace, $key, $fileName, $mimeType)
            : $this->registerStored($workspace, $key, $fileName, $mimeType, $size, $type, $duration);

        return ChunkReceipt::completed($media);
    }

    /**
     * Images are resized and re-encoded, so the uploaded original is pulled
     * down, stored through the image pipeline, and then discarded.
     */
    private function registerImage(Workspace $workspace, string $key, string $fileName, string $mimeType): Media
    {
        $localPath = (string) tempnam(sys_get_temp_dir(), 'direct-upload-');

        try {
            $this->cloud->download($key, $localPath);

            return $workspace->addMediaFromPath($localPath, $fileName, 'assets', [], null, $mimeType);
        } finally {
            @unlink($localPath);
            Storage::delete($key);
        }
    }

    private function registerStored(
        Workspace $workspace,
        string $key,
        string $fileName,
        string $mimeType,
        int $size,
        MediaType $type,
        ?float $duration,
    ): Media {
        $meta = $type === MediaType::Video
            ? VideoDurationProbe::mergeInto(VideoDurationProbe::mergeInto([], $duration), $this->storedVideoDuration($key, $size))
            : [];

        try {
            return $workspace->addMediaFromStoredPath($key, $fileName, $mimeType, $size, 'assets', $meta);
        } catch (Throwable $exception) {
            Storage::delete($key);

            throw $exception;
        }
    }

    /**
     * The file never touches local disk, so the probe reads the atom headers
     * straight from object storage with ranged GETs.
     */
    private function storedVideoDuration(string $key, int $size): ?float
    {
        try {
            return VideoDurationProbe::fromReader(
                fn (int $offset, int $length): string => $this->cloud->readRange($key, $offset, $length),
                $size,
            );
        } catch (Throwable $exception) {
            Log::warning('Could not probe video duration from object storage', ['path' => $key, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    private function reject(string $key, string $message): never
    {
        Storage::delete($key);

        throw ValidationException::withMessages(['upload_id' => $message]);
    }

    private function cacheKey(User $user, string $uploadId): string
    {
        return self::CACHE_PREFIX."{$user->id}:{$uploadId}";
    }
}
