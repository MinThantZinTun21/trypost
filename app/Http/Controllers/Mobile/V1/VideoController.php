<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Actions\Video\CreateVideo;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Mobile\StartVideoUploadRequest;
use App\Http\Requests\App\Mobile\StoreVideoRequest;
use App\Http\Resources\Mobile\V1\VideoResource;
use App\Models\Video;
use App\Services\Media\ChunkedCloudUploader;
use App\Services\Media\DirectAssetReceiver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Videos (ADR 0013): the Mobile app uploads them straight to object storage,
 * lists them and deletes them.
 */
class VideoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $videos = $request->user()->resolveCurrentWorkspace()
            ->videos()
            ->with('file')
            ->latest()
            ->latest('id')
            ->paginate((int) config('app.pagination.default'));

        return VideoResource::collection($videos);
    }

    /**
     * Hands out a presigned PUT URL for the file, through the composer's
     * direct upload.
     */
    public function startUpload(StartVideoUploadRequest $request, DirectAssetReceiver $receiver): JsonResponse
    {
        $uploadId = Str::uuid()->toString();
        $upload = $receiver->start($request->user(), (string) $request->validated('file_name'), $uploadId);

        if (! data_get($upload, 'direct')) {
            throw ValidationException::withMessages(['file_name' => __('videos.not_object_storage')]);
        }

        return response()->json([
            'upload_id' => $uploadId,
            'url' => data_get($upload, 'url'),
            'headers' => (object) data_get($upload, 'headers', []),
            'expires_in_seconds' => ChunkedCloudUploader::UPLOAD_URL_TTL_SECONDS,
        ]);
    }

    public function store(StoreVideoRequest $request): JsonResponse
    {
        $video = CreateVideo::execute(
            $request->user()->resolveCurrentWorkspace(),
            $request->user(),
            (string) $request->validated('name'),
            (string) $request->validated('upload_id'),
        );

        return (new VideoResource($video->load('file')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Video $video): Response
    {
        $this->authorize('delete', $video);

        $video->delete();

        return response()->noContent();
    }
}
