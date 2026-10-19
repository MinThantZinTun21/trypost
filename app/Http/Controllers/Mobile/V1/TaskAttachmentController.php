<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Actions\Task\AddTaskAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Mobile\StartTaskAttachmentUploadRequest;
use App\Http\Requests\App\Mobile\StoreTaskAttachmentRequest;
use App\Http\Resources\Mobile\V1\TaskAttachmentResource;
use App\Models\Media;
use App\Models\Task;
use App\Services\Media\ChunkedCloudUploader;
use App\Services\Media\DirectAssetReceiver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Task attachments (ADR 0016): photos, videos and audio uploaded straight to
 * object storage through the Videos path.
 */
class TaskAttachmentController extends Controller
{
    public function startUpload(StartTaskAttachmentUploadRequest $request, Task $task, DirectAssetReceiver $receiver): JsonResponse
    {
        $this->authorize('manage', $task);

        $uploadId = Str::uuid()->toString();
        $upload = $receiver->start($request->user(), (string) $request->validated('file_name'), $uploadId);

        if (! data_get($upload, 'direct')) {
            throw ValidationException::withMessages(['file_name' => __('tasks.attachment_not_object_storage')]);
        }

        return response()->json([
            'upload_id' => $uploadId,
            'url' => data_get($upload, 'url'),
            'headers' => (object) data_get($upload, 'headers', []),
            'expires_in_seconds' => ChunkedCloudUploader::UPLOAD_URL_TTL_SECONDS,
        ]);
    }

    public function store(StoreTaskAttachmentRequest $request, Task $task): JsonResponse
    {
        $this->authorize('manage', $task);

        $attachment = AddTaskAttachment::execute($task, $request->user(), (string) $request->validated('upload_id'));

        return (new TaskAttachmentResource($attachment))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Task $task, Media $media): Response
    {
        $this->authorize('manage', $task);

        abort_unless(
            $media->mediable_type === $task->getMorphClass()
                && $media->mediable_id === $task->id
                && $media->collection === Task::ATTACHMENTS_COLLECTION,
            Response::HTTP_NOT_FOUND,
        );

        $media->delete();

        return response()->noContent();
    }
}
