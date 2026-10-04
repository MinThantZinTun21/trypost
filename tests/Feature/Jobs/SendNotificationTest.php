<?php

declare(strict_types=1);

use App\Enums\Notification\Channel;
use App\Enums\Notification\Type;
use App\Jobs\SendNotification;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
});

test('send notification creates an in-app notification and sends no email', function () {
    (new SendNotification(
        user: $this->user,
        workspaceId: $this->workspace->id,
        type: Type::PostFailed,
        title: 'Test title',
        body: 'Test body',
    ))->handle();

    expect(Notification::count())->toBe(1);

    $notification = Notification::first();
    expect($notification->user_id)->toBe($this->user->id);
    expect($notification->title)->toBe('Test title');
    expect($notification->type)->toBe(Type::PostFailed);
    expect($notification->channel)->toBe(Channel::InApp);

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

test('send notification stores data json', function () {
    (new SendNotification(
        user: $this->user,
        workspaceId: $this->workspace->id,
        type: Type::PostFailed,
        title: 'Test',
        body: 'Body',
        data: ['post_id' => 'abc-123'],
    ))->handle();

    $notification = Notification::first();
    expect($notification->data)->toBe(['post_id' => 'abc-123']);
});
