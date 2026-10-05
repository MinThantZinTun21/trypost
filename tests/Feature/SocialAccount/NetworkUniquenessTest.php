<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Exceptions\SocialAccount\NetworkAlreadyConnectedException;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
});

test('allows a second account of the same network', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-a',
    ]);

    $second = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-b',
    ]);

    expect($second->exists)->toBeTrue()
        ->and($this->workspace->socialAccounts()->count())->toBe(2);
});

test('allows different networks in the same workspace', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-a',
    ]);

    $youtube = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube,
        'platform_user_id' => 'yt-a',
    ]);

    expect($youtube->exists)->toBeTrue();
});

test('allows the same network in different workspaces', function () {
    $other = Workspace::factory()->create();

    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-a',
    ]);

    $second = SocialAccount::factory()->create([
        'workspace_id' => $other->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-a',
    ]);

    expect($second->exists)->toBeTrue();
});

test('reconnecting the same account via updateOrCreate is allowed', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-a',
        'username' => 'old',
    ]);

    $this->workspace->socialAccounts()->updateOrCreate(
        ['platform' => Platform::TikTok->value, 'platform_user_id' => 'tt-a'],
        ['username' => 'new', 'status' => Status::Connected],
    );

    expect($this->workspace->socialAccounts()->count())->toBe(1)
        ->and($this->workspace->socialAccounts()->first()->username)->toBe('new');
});

test('the same workspace platform identity cannot be stored twice', function () {

    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-a',
    ]);

    expect(fn () => SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-a',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('connectIdentity refuses to repoint the reconnect target at another identity', function () {

    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-a',
        'username' => 'old',
    ]);

    expect(fn () => SocialAccount::connectIdentity(
        $this->workspace,
        Platform::TikTok,
        'tt-b',
        ['username' => 'new', 'status' => Status::Connected],
        $account,
    ))->toThrow(NetworkAlreadyConnectedException::class);

    expect($account->fresh()->platform_user_id)->toBe('tt-a')
        ->and($account->fresh()->username)->toBe('old')
        ->and($this->workspace->socialAccounts()->count())->toBe(1);
});

test('connectIdentity keeps posts on the card when a stray identity is authorized', function () {

    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube,
        'platform_user_id' => 'yt-brand',
        'username' => 'brand',
    ]);

    expect(fn () => SocialAccount::connectIdentity(
        $this->workspace,
        Platform::YouTube,
        'yt-personal',
        [
            'username' => 'personal',
            'status' => Status::Connected,
            'access_token' => 'personal-token',
        ],
        $account,
    ))->toThrow(NetworkAlreadyConnectedException::class);

    expect($account->fresh()->platform_user_id)->toBe('yt-brand')
        ->and($account->fresh()->username)->toBe('brand')
        ->and($this->workspace->socialAccounts()->where('platform_user_id', 'yt-personal')->exists())->toBeFalse();
});

test('connectIdentity reconnect throws when the new identity is already taken', function () {

    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-keep',
    ]);

    $move = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-move',
    ]);

    expect(fn () => SocialAccount::connectIdentity(
        $this->workspace,
        Platform::TikTok,
        'tt-keep',
        ['username' => 'taken', 'status' => Status::Connected],
        $move,
    ))->toThrow(NetworkAlreadyConnectedException::class);
});

test('connectIdentity ignores a reconnect target from another network', function () {
    $facebook = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
        'platform_user_id' => 'page-1',
    ]);

    $tiktok = SocialAccount::connectIdentity(
        $this->workspace,
        Platform::TikTok,
        'tt-new',
        [
            'username' => 'fresh',
            'status' => Status::Connected,
            'access_token' => 'tiktok-token',
        ],
        $facebook,
    );

    expect($tiktok->id)->not->toBe($facebook->id)
        ->and($tiktok->platform)->toBe(Platform::TikTok)
        ->and($facebook->fresh()->platform)->toBe(Platform::Facebook)
        ->and($this->workspace->socialAccounts()->count())->toBe(2);
});

test('the observer leaves a missing platform to the database instead of a type error', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'platform_user_id' => 'tt-a',
    ]);

    $account = new SocialAccount([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'no-platform',
    ]);

    expect(fn () => $account->save())
        ->toThrow(QueryException::class)
        ->and(fn () => $account->save())->not->toThrow(TypeError::class);
});

test('connectIdentity serializes connects on the same network', function () {
    $lock = Cache::lock("social_connect:{$this->workspace->id}:tiktok", 10);

    expect($lock->get())->toBeTrue();

    try {
        try {
            SocialAccount::connectIdentity(
                $this->workspace,
                Platform::TikTok,
                'tt-a',
                ['username' => 'blocked', 'status' => Status::Connected],
            );

            $this->fail('A busy network lock should reject the connect.');
        } catch (NetworkAlreadyConnectedException $e) {
            expect($e->messageKey)->toBe('busy');
        }

        expect($this->workspace->socialAccounts()->count())->toBe(0);
    } finally {
        $lock->release();
    }
});

test('connectIdentity does not serialize connects on different networks', function () {
    $lock = Cache::lock("social_connect:{$this->workspace->id}:tiktok", 10);

    expect($lock->get())->toBeTrue();

    try {
        $account = SocialAccount::connectIdentity(
            $this->workspace,
            Platform::YouTube,
            'yt-a',
            ['username' => 'free', 'status' => Status::Connected, 'access_token' => 'yt-token'],
        );

        expect($account->exists)->toBeTrue();
    } finally {
        $lock->release();
    }
});

test('connectIdentity releases the lock so the next connect proceeds', function () {
    SocialAccount::connectIdentity(
        $this->workspace,
        Platform::YouTube,
        'yt-a',
        ['username' => 'first', 'status' => Status::Connected, 'access_token' => 'yt-token'],
    );

    $second = SocialAccount::connectIdentity(
        $this->workspace,
        Platform::YouTube,
        'yt-a',
        ['username' => 'second', 'status' => Status::Connected, 'access_token' => 'yt-token-2'],
    );

    expect($second->username)->toBe('second')
        ->and($this->workspace->socialAccounts()->count())->toBe(1);
});
