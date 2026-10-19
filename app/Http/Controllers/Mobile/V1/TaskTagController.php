<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Mobile\StoreTaskTagRequest;
use App\Http\Requests\App\Mobile\UpdateTaskTagRequest;
use App\Http\Resources\Mobile\V1\TaskTagResource;
use App\Models\TagGroup;
use App\Models\TaskTag;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TaskTagController extends Controller
{
    public function store(StoreTaskTagRequest $request, TagGroup $tagGroup): JsonResponse
    {
        $this->authorize('manage', $tagGroup);

        $taskTag = $tagGroup->taskTags()->create($request->validated());

        return (new TaskTagResource($taskTag))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateTaskTagRequest $request, TaskTag $taskTag): TaskTagResource
    {
        $this->authorize('manage', $taskTag);

        $taskTag->update($request->validated());

        return new TaskTagResource($taskTag);
    }

    /**
     * Deletes the Task tag, which leaves every Task.
     */
    public function destroy(TaskTag $taskTag): Response
    {
        $this->authorize('manage', $taskTag);

        $taskTag->delete();

        return response()->noContent();
    }
}
