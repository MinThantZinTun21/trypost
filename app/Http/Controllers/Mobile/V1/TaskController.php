<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Actions\Task\SaveTask;
use App\Actions\Task\SetTaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Mobile\ListTasksRequest;
use App\Http\Requests\App\Mobile\StoreTaskRequest;
use App\Http\Requests\App\Mobile\UpdateTaskRequest;
use App\Http\Requests\App\Mobile\UpdateTaskStatusRequest;
use App\Http\Resources\Mobile\V1\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tasks (ADR 0016): the Owner's to-dos, reached only from the Mobile app.
 */
class TaskController extends Controller
{
    private const array RELATIONS = ['taskTags.tagGroup', 'reminders', 'timerSessions'];

    public function index(ListTasksRequest $request): AnonymousResourceCollection
    {
        $query = $request->user()->resolveCurrentWorkspace()
            ->tasks()
            ->with(self::RELATIONS)
            ->withTaskTagsFromEachGroup($request->taskTagIdsByGroup())
            ->when($request->search(), fn ($query, string $text) => $query->matching($text));

        $tasks = $request->tab()->apply($query->getQuery())
            ->paginate((int) config('app.pagination.default'))
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = SaveTask::execute(
            $request->user()->resolveCurrentWorkspace(),
            $request->user(),
            null,
            $request->taskAttributes(),
            $request->taskTagIds(),
            $request->taskReminders(),
        );

        return $this->show($task)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Task $task): TaskResource
    {
        $this->authorize('manage', $task);

        return new TaskResource($task->load(self::RELATIONS));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('manage', $task);

        return $this->show(SaveTask::execute(
            $request->user()->resolveCurrentWorkspace(),
            $request->user(),
            $task,
            $request->taskAttributes(),
            $request->taskTagIds(),
            $request->taskReminders(),
        ));
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): TaskResource
    {
        $this->authorize('manage', $task);

        return $this->show(SetTaskStatus::execute($task, $request->status()));
    }

    public function destroy(Task $task): Response
    {
        $this->authorize('manage', $task);

        $task->delete();

        return response()->noContent();
    }
}
