<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Actions\Task\StartTimer;
use App\Actions\Task\StopTimer;
use App\Exceptions\Task\TimerRefusedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Mobile\StartTimerRequest;
use App\Http\Resources\Mobile\V1\TimerSessionResource;
use App\Models\Task;
use App\Models\TimerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Task timer (ADR 0016): one running session at a time, a Pomodoro or a
 * free-running clock.
 */
class TimerController extends Controller
{
    /**
     * The running session with its Task, or null, wrapped in `data`.
     */
    public function show(Request $request): JsonResponse
    {
        $session = TimerSession::query()
            ->where('workspace_id', $request->user()->resolveCurrentWorkspace()->id)
            ->running()
            ->with('task')
            ->get()
            ->first(fn (TimerSession $session): bool => $session->finishIfElapsed()->isRunning());

        return response()->json(['data' => $session === null ? null : new TimerSessionResource($session)]);
    }

    public function start(StartTimerRequest $request, Task $task): JsonResponse
    {
        $this->authorize('manage', $task);

        try {
            $session = StartTimer::execute($task, $request->mode(), $request->plannedSeconds());
        } catch (TimerRefusedException $refusal) {
            return response()->json(['message' => $refusal->getMessage()], Response::HTTP_CONFLICT);
        }

        return (new TimerSessionResource($session->load('task')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function stop(TimerSession $timerSession): TimerSessionResource
    {
        $this->authorize('manage', $timerSession->task);

        return new TimerSessionResource(StopTimer::execute($timerSession)->load('task'));
    }
}
