<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Notification\Channel;
use App\Enums\Notification\Type;
use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stores an in-app Notification (the bell) and broadcasts it. The app sends
 * no email: this is the only way an owner is told about something.
 */
class SendNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public User $user,
        public string $workspaceId,
        public Type $type,
        public string $title,
        public string $body,
        public ?array $data = null,
    ) {}

    public function handle(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspaceId,
            'type' => $this->type,
            'channel' => Channel::InApp,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ]);

        NotificationCreated::dispatch($notification);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendNotification job failed', [
            'user_id' => $this->user->id,
            'type' => $this->type->value,
            'error' => $exception->getMessage(),
        ]);
    }
}
