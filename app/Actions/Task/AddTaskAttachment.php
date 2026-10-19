<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Media;
use App\Models\Task;
use App\Models\User;
use App\Services\Media\DirectAssetReceiver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddTaskAttachment
{
    /**
     * Attaches the finished upload to the Task. The Task row is locked while
     * its attachments are counted, so two uploads finishing together cannot
     * pass the limit; a refused upload is removed from storage.
     *
     * @throws ValidationException
     */
    public static function execute(Task $task, User $user, string $uploadId): Media
    {
        return DB::transaction(function () use ($task, $user, $uploadId): Media {
            Task::query()->whereKey($task->id)->lockForUpdate()->first();

            $maxCount = (int) config('trypost.tasks.attachments.max_count');
            if ($task->attachments()->count() >= $maxCount) {
                app(DirectAssetReceiver::class)->discard($user, $uploadId);

                throw ValidationException::withMessages(['upload_id' => __('tasks.attachments_full', ['max' => $maxCount])]);
            }

            return app(DirectAssetReceiver::class)->completeTaskAttachment($task, $user, $uploadId);
        });
    }
}
