<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Status;
use App\Jobs\PostHog\IdentifyConnectedPlatforms;
use App\Jobs\PostHog\SendEvent;
use App\Models\Account;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    config(['services.posthog.enabled' => true, 'services.posthog.api_key' => 'phc_test_key']);

    $this->account = Account::factory()->create();
    $this->user = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->user->id]);
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);
});

test('creating a social account dispatches IdentifyConnectedPlatforms', function () {
    Bus::fake();

    SocialAccount::factory()->linkedin()->create(['workspace_id' => $this->workspace->id]);

    Bus::assertDispatched(IdentifyConnectedPlatforms::class, function (IdentifyConnectedPlatforms $job): bool {
        return $job->workspaceId === (string) $this->workspace->id;
    });
});

test('deleting a social account dispatches IdentifyConnectedPlatforms', function () {
    $socialAccount = SocialAccount::factory()->linkedin()->create(['workspace_id' => $this->workspace->id]);

    Bus::fake();

    $socialAccount->delete();

    Bus::assertDispatched(IdentifyConnectedPlatforms::class, function (IdentifyConnectedPlatforms $job): bool {
        return $job->workspaceId === (string) $this->workspace->id;
    });
});

test('disconnecting a social account dispatches IdentifyConnectedPlatforms', function () {
    $socialAccount = SocialAccount::factory()->linkedin()->create(['workspace_id' => $this->workspace->id]);

    Bus::fake();

    $socialAccount->update(['status' => Status::Disconnected]);

    Bus::assertDispatched(IdentifyConnectedPlatforms::class, function (IdentifyConnectedPlatforms $job): bool {
        return $job->workspaceId === (string) $this->workspace->id;
    });
});

test('updating a social account does not dispatch posthog jobs', function () {
    $socialAccount = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    Bus::fake();

    $socialAccount->update(['is_active' => false]);

    Bus::assertNotDispatched(IdentifyConnectedPlatforms::class);
    Bus::assertNotDispatched(SendEvent::class);
});

test('does not dispatch when PostHog is disabled', function () {
    config(['services.posthog.enabled' => false]);

    Bus::fake();

    SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    Bus::assertNotDispatched(IdentifyConnectedPlatforms::class);
    Bus::assertNotDispatched(SendEvent::class);
});

test('does not identify connected platforms without PostHog', function () {
    config([
        'services.posthog.enabled' => false,
        'services.posthog.api_key' => null,
    ]);

    Bus::fake();

    $socialAccount = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $this->assertModelExists($socialAccount);
    Bus::assertNotDispatched(IdentifyConnectedPlatforms::class);
    Bus::assertNotDispatched(SendEvent::class);
});

test('updating status on multiple batch-hydrated social accounts does not throw a lazy loading violation', function () {
    $accounts = SocialAccount::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'status' => Status::Connected,
    ]);

    $batch = SocialAccount::query()
        ->whereIn('id', $accounts->pluck('id'))
        ->get();

    foreach ($batch as $socialAccount) {
        $socialAccount->update(['status' => Status::Disconnected]);
    }
})->throwsNoExceptions();
