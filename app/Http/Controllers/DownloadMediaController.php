<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dto\MediaItem;
use App\Models\Media;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Download link in a TikTok post sent to Telegram (ADR 0009): a permanent signed URL
 * that downloads a Post's video without signing in. Remote storage that can
 * presign (R2) serves the file itself through a short-lived URL; a local disk
 * streams it, because its own temporary URLs cannot force a download.
 */
class DownloadMediaController extends Controller
{
    /** Minutes the presigned storage URL stays valid after a click. */
    private const int STORAGE_URL_MINUTES = 30;

    public function __invoke(Media $media): RedirectResponse|StreamedResponse
    {
        $filename = MediaItem::fromMedia($media)->filename();
        $disk = Storage::disk();

        if ($disk instanceof LocalFilesystemAdapter || ! $disk->providesTemporaryUrls()) {
            return $disk->download($media->path, $filename);
        }

        return redirect()->away($disk->temporaryUrl(
            $media->path,
            now()->addMinutes(self::STORAGE_URL_MINUTES),
            ['ResponseContentDisposition' => $this->attachment($filename)],
        ));
    }

    /**
     * An attachment disposition with an ASCII fallback for older clients.
     */
    private function attachment(string $filename): string
    {
        $filename = str_replace(['/', '\\'], '-', $filename);
        $fallback = preg_replace('/[^\x20-\x7e]|[%"]/', '', Str::ascii($filename)) ?: 'download';

        return HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, $fallback);
    }
}
