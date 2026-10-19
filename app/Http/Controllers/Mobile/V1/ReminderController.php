<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Enums\Task\Status;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\V1\ReminderResource;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reminders (ADR 0016). The phone keeps the upcoming list to set its alarms
 * again after a reboot or while offline; Dismiss stops one for good.
 */
class ReminderController extends Controller
{
    /**
     * Every undismissed Reminder of an open Task, soonest first, wrapped in
     * `data` like the Social accounts list. Past ones are included: the phone
     * decides what still rings.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $workspaceId = $request->user()->resolveCurrentWorkspace()->id;

        $reminders = Reminder::query()
            ->whereNull('dismissed_at')
            ->whereHas('task', fn (Builder $task) => $task
                ->where('workspace_id', $workspaceId)
                ->whereIn('status', Status::open()))
            ->with('task')
            ->orderBy('remind_at')
            ->get();

        return response()->json(['data' => ReminderResource::collection($reminders)]);
    }

    /**
     * Stops the Reminder ringing, keeping the first dismissal's time.
     */
    public function dismiss(Reminder $reminder): ReminderResource
    {
        $this->authorize('manage', $reminder->task);

        $reminder->dismissed_at ??= now();
        $reminder->save();

        return new ReminderResource($reminder->load('task'));
    }
}
