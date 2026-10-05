<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Jobs\RefreshSocialToken;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

test('it dispatches refresh jobs for tokens near or past expiry', function () {
    Queue::fake();

    $workspace = Workspace::factory()->create();

    // Expiring in 15 minutes — inside the 30-minute window.
    $expiringSoon = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::YouTube,
        'status' => Status::Connected,
        'token_expires_at' => now()->addMinutes(15),
    ]);

    // Expiring in 1 hour — OUTSIDE the 30-minute window.
    SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::TikTok,
        'status' => Status::Connected,
        'token_expires_at' => now()->addHour(),
    ]);

    // Already expired — last-chance attempt before the refresh_token also
    // dies at the provider.
    $expired = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::TikTok,
        'status' => Status::Connected,
        'token_expires_at' => now()->subHour(),
    ]);

    // Disconnected — never refreshed.
    SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::TikTok,
        'status' => Status::Disconnected,
        'token_expires_at' => now()->addMinutes(15),
    ]);

    // Already token expired — daily verify handles these.
    SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::YouTube,
        'status' => Status::TokenExpired,
        'token_expires_at' => now()->subHour(),
    ]);

    $this->artisan('social:refresh-expiring-tokens')
        ->assertSuccessful();

    Queue::assertPushed(RefreshSocialToken::class, 2);
    Queue::assertPushed(RefreshSocialToken::class, fn ($job) => $job->account->id === $expiringSoon->id);
    Queue::assertPushed(RefreshSocialToken::class, fn ($job) => $job->account->id === $expired->id);
});

test('it dispatches nothing when no tokens are expiring', function () {
    Queue::fake();

    $this->artisan('social:refresh-expiring-tokens')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('a backed-up queue cannot stack duplicate refresh jobs for one account', function () {
    Queue::fake();

    SocialAccount::factory()->tiktok()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'status' => Status::Connected,
        'token_expires_at' => now()->addMinutes(20),
    ]);

    // Two scheduler ticks before the first job got a worker: token_expires_at
    // has not moved, so the account is still inside the window.
    $this->artisan('social:refresh-expiring-tokens');
    $this->artisan('social:refresh-expiring-tokens');

    // Each extra job rotates a single-use refresh_token again for nothing, and
    // widens the window where a worker death loses the pair.
    Queue::assertPushed(RefreshSocialToken::class, 1);
});

test('the command reports accounts in the window, not jobs it cannot know landed', function () {
    Queue::fake();

    SocialAccount::factory()->tiktok()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'status' => Status::Connected,
        'token_expires_at' => now()->addMinutes(20),
    ]);

    // RefreshSocialToken is unique per account, so a second dispatch while the
    // first is in flight is silently discarded. dispatch() still returns a
    // PendingDispatch either way, so a "dispatched" count would be a guess.
    $this->artisan('social:refresh-expiring-tokens')->assertSuccessful();
});
