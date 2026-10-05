<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Status;
use App\Exceptions\PlatformUnavailableException;
use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use App\Services\Social\ConnectionVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('verifies account without refresh when token is not expired', function () {
    Http::fake([
        config('trypost.platforms.tiktok.api').'/user/info/*' => Http::response(['data' => ['user' => []]], 200),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'token_expires_at' => now()->addDays(30),
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), config('trypost.platforms.tiktok.api').'/user/info/'));
});

test('refreshes youtube token before verifying when expired', function () {
    Http::fake([
        config('trypost.platforms.youtube.oauth_api').'/token' => Http::response([
            'access_token' => 'new_token',
            'expires_in' => 3600,
        ], 200),
        config('trypost.platforms.youtube.data_api').'/*' => Http::response(['items' => []], 200),
    ]);

    $account = SocialAccount::factory()->youtube()->create([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), config('trypost.platforms.youtube.oauth_api').'/token'));
});

test('refreshes tiktok token before verifying when expired', function () {
    Http::fake([
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 86400,
        ], 200),
        config('trypost.platforms.tiktok.api').'/user/info/*' => Http::response(['data' => ['user' => []]], 200),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), config('trypost.platforms.tiktok.api').'/oauth/token'));
});

test('does not refresh facebook token as it uses long-lived tokens', function () {
    Http::fake([
        config('trypost.platforms.facebook.graph_api').'/*' => Http::response(['id' => '123', 'name' => 'Test'], 200),
    ]);

    $account = SocialAccount::factory()->facebook()->create([
        'token_expires_at' => now()->subHour(),
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    // Should only call the verify endpoint, no refresh
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), config('trypost.platforms.facebook.graph_api')));
});

test('does NOT refresh proactively when token still works (lazy refresh)', function () {
    Http::fake([
        config('trypost.platforms.tiktok.api').'/user/info/*' => Http::response(['data' => ['user' => []]], 200),
    ]);

    // Token is "expiring soon" but access_token still works.
    $account = SocialAccount::factory()->tiktok()->create([
        'token_expires_at' => now()->addMinutes(10),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;

    expect($verifier->verify($account))->toBeTrue();

    // Refresh endpoint must NOT have been called — verify worked without it.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), config('trypost.platforms.tiktok.api').'/oauth/token/'));
    Http::assertSent(fn ($request) => str_contains($request->url(), config('trypost.platforms.tiktok.api').'/user/info/'));
});

test('refreshes lazily on 401 then retries verify', function () {
    Http::fake([
        // First verify call returns 401, second (after refresh) returns 200.
        config('trypost.platforms.tiktok.api').'/user/info/*' => Http::sequence()
            ->push(['error' => 'unauthorized'], 401)
            ->push(['data' => ['user' => []]], 200),
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 86400,
        ], 200),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'token_expires_at' => now()->addHours(2),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;

    expect($verifier->verify($account))->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), config('trypost.platforms.tiktok.api').'/oauth/token/'));
});

test('throws when verify returns 401 AND refresh also fails', function () {
    Http::fake([
        config('trypost.platforms.tiktok.api').'/user/info/*' => Http::response(['error' => 'unauthorized'], 401),
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'token_expires_at' => now()->addHours(2),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;

    expect(fn () => $verifier->verify($account))->toThrow(TokenExpiredException::class);
});

test('forces refresh when token is hard-expired', function () {
    Http::fake([
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 86400,
        ], 200),
        config('trypost.platforms.tiktok.api').'/user/info/*' => Http::response(['data' => ['user' => []]], 200),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'token_expires_at' => now()->subMinutes(5),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;

    expect($verifier->verify($account))->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), config('trypost.platforms.tiktok.api').'/oauth/token/'));
});

test('throws when refresh fails AND token is hard-expired', function () {
    Http::fake([
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'token_expires_at' => now()->subMinutes(5),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;

    expect(fn () => $verifier->verify($account))->toThrow(TokenExpiredException::class);
});

test('5xx during refresh raises PlatformUnavailableException, not TokenExpiredException', function () {
    Http::fake([
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response('upstream timeout', 503),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'token_expires_at' => now()->subMinutes(5),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;

    expect(fn () => $verifier->refreshToken($account))->toThrow(PlatformUnavailableException::class);
});

test('connection failure during refresh raises PlatformUnavailableException', function () {
    Http::fake([
        config('trypost.platforms.youtube.oauth_api').'/token' => fn () => throw new ConnectionException('cURL error 7: connection refused'),
    ]);

    $account = SocialAccount::factory()->youtube()->create([
        'token_expires_at' => now()->subMinutes(5),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;

    expect(fn () => $verifier->refreshToken($account))->toThrow(PlatformUnavailableException::class);
});

test('does not disconnect when a concurrent refresh already rotated the token (lost-rotation race)', function () {
    Http::fake([
        // Our stale refresh_token is rejected — a concurrent process already used it.
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response(['error' => 'invalid_grant'], 400),
        // But the access_token the winning refresh persisted still works.
        config('trypost.platforms.tiktok.api').'/user/info/*' => Http::response(['data' => ['user' => []]], 200),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'status' => Status::Connected,
        'access_token' => 'stale-token',
        'refresh_token' => 'already-rotated',
        'token_expires_at' => now()->subHour(),
    ]);

    // Simulate the concurrent refresh: a separate instance persists a fresh,
    // valid token (through the encrypted cast) while our in-memory copy stays
    // the stale, expired one.
    SocialAccount::find($account->id)->update([
        'access_token' => 'fresh-token',
        'token_expires_at' => now()->addHours(2),
    ]);

    expect((new ConnectionVerifier)->verify($account))->toBeTrue();
    expect($account->fresh()->status)->toBe(Status::Connected);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/user/info/')
        && $request->header('Authorization')[0] === 'Bearer fresh-token');
});

test('does not disconnect when the verify after a refresh 401s once but a fresh token is available', function () {
    Http::fake([
        // Refresh succeeds and rotates the token...
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response([
            'access_token' => 'refreshed-token',
            'refresh_token' => 'new-refresh',
            'expires_in' => 7200,
        ], 200),
        // ...but the verify that follows 401s once (the sub-commit window where a
        // lock-skipped refresh reloads a not-yet-persisted token) before
        // succeeding on the reload + retry.
        config('trypost.platforms.tiktok.api').'/user/info/*' => Http::sequence()
            ->push(['error' => 'unauthorized'], 401)
            ->push(['data' => ['user' => []]], 200),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'status' => Status::Connected,
        'access_token' => 'stale-token',
        'refresh_token' => 'old-refresh',
        'token_expires_at' => now()->subHour(),
    ]);

    expect((new ConnectionVerifier)->verify($account))->toBeTrue();
    expect($account->fresh()->status)->toBe(Status::Connected);
});

test('4xx during refresh keeps raising TokenExpiredException', function () {
    Http::fake([
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'token_expires_at' => now()->subMinutes(5),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;

    expect(fn () => $verifier->refreshToken($account))->toThrow(TokenExpiredException::class);
});

test('429 during refresh raises PlatformUnavailableException (rate limit is transient)', function () {
    Http::fake([
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response(['error' => 'rate_limit_exceeded'], 429),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'token_expires_at' => now()->subMinutes(5),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;

    expect(fn () => $verifier->refreshToken($account))->toThrow(PlatformUnavailableException::class);
});

test('facebook verify treats a dead token reported under a non-190 code as genuinely expired', function () {
    Http::fake([
        config('trypost.platforms.facebook.graph_api').'/me*' => Http::response([
            'error' => ['message' => 'The requested resource does not exist', 'type' => 'OAuthException', 'code' => 100],
        ], 400),
    ]);

    $account = SocialAccount::factory()->facebook()->create([
        'token_expires_at' => now()->addDays(30),
    ]);

    expect(fn () => (new ConnectionVerifier)->verify($account))
        ->toThrow(TokenExpiredException::class);

    // Facebook Page tokens don't expire — no per-account refresh flow — so a
    // confirmed rejection must not trigger a second, identical /me call.
    Http::assertSentCount(1);
});

test('facebook verify treats a Meta rate-limit as transient, not a disconnect', function () {
    Http::fake([
        config('trypost.platforms.facebook.graph_api').'/me*' => Http::response([
            'error' => ['message' => 'Application request limit reached', 'type' => 'OAuthException', 'code' => 4],
        ], 400),
    ]);

    $account = SocialAccount::factory()->facebook()->create([
        'token_expires_at' => now()->addDays(30),
    ]);

    expect(fn () => (new ConnectionVerifier)->verify($account))
        ->toThrow(PlatformUnavailableException::class);
});

test('facebook verify treats a Business Use Case rate-limit (Page token, code 80001) as transient, not a disconnect', function () {
    // Facebook and InstagramFacebook accounts use Page tokens, which are
    // throttled by BUC limits (code 80001) rather than Platform Rate Limits
    // (codes 4/17) — and BUC rejections come back as a plain 400, not 429.
    Http::fake([
        config('trypost.platforms.facebook.graph_api').'/me*' => Http::response([
            'error' => ['message' => 'There have been too many calls to this Page account.', 'code' => 80001],
        ], 400),
    ]);

    $account = SocialAccount::factory()->facebook()->create([
        'token_expires_at' => now()->addDays(30),
    ]);

    expect(fn () => (new ConnectionVerifier)->verify($account))
        ->toThrow(PlatformUnavailableException::class);
});

test('facebook verify treats a 5xx as platform unavailable, not a disconnect', function () {
    Http::fake([
        config('trypost.platforms.facebook.graph_api').'/me*' => Http::response('upstream timeout', 503),
    ]);

    $account = SocialAccount::factory()->facebook()->create([
        'token_expires_at' => now()->addDays(30),
    ]);

    expect(fn () => (new ConnectionVerifier)->verify($account))
        ->toThrow(PlatformUnavailableException::class);
});

test('facebook verify treats a non-JSON failure body as platform unavailable, not a confirmed dead token', function () {
    Http::fake([
        config('trypost.platforms.facebook.graph_api').'/me*' => Http::response('<html>blocked</html>', 400),
    ]);

    $account = SocialAccount::factory()->facebook()->create([
        'token_expires_at' => now()->addDays(30),
    ]);

    expect(fn () => (new ConnectionVerifier)->verify($account))
        ->toThrow(PlatformUnavailableException::class);
});

test('youtube verify treats a 5xx as platform unavailable, not a disconnect', function () {
    Http::fake([
        config('trypost.platforms.youtube.data_api').'/channels*' => Http::response(['error' => ['message' => 'Backend Error']], 500),
    ]);

    $account = SocialAccount::factory()->youtube()->create();

    expect(fn () => (new ConnectionVerifier)->verify($account))
        ->toThrow(PlatformUnavailableException::class);
});

test('youtube verify throws TokenExpiredException on a bare 401', function () {
    Http::fake([
        config('trypost.platforms.youtube.oauth_api').'/token' => Http::response(['error' => 'invalid_grant'], 400),
        config('trypost.platforms.youtube.data_api').'/channels*' => Http::response(['error' => ['message' => 'Unauthorized']], 401),
    ]);

    $account = SocialAccount::factory()->youtube()->create();

    expect(fn () => (new ConnectionVerifier)->verify($account))
        ->toThrow(TokenExpiredException::class);
});

test('tiktok verify treats a 5xx as platform unavailable, not a disconnect', function () {
    Http::fake([
        config('trypost.platforms.tiktok.api').'/user/info/*' => Http::response(['error' => ['code' => 'internal_error']], 500),
    ]);

    $account = SocialAccount::factory()->tiktok()->create();

    expect(fn () => (new ConnectionVerifier)->verify($account))
        ->toThrow(PlatformUnavailableException::class);
});

test('tiktok verify throws TokenExpiredException on a bare 401 from user/info', function () {
    // /v2/user/info/ only needs the always-granted user.info.basic scope, so
    // unlike a publish-time 401 (which can be a video.publish scope gap), a
    // 401 here is unambiguous — the token itself is dead. A 401 also triggers
    // verify()'s built-in refresh-and-retry, so the refresh endpoint needs a
    // response too, even though the retried verify call fails identically.
    Http::fake([
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 86400,
        ], 200),
        config('trypost.platforms.tiktok.api').'/user/info/*' => Http::response(['error' => ['code' => 'access_token_invalid']], 401),
    ]);

    $account = SocialAccount::factory()->tiktok()->create();

    expect(fn () => (new ConnectionVerifier)->verify($account))
        ->toThrow(TokenExpiredException::class);
});

test('tiktok verify throws TokenExpiredException on a bare 401 with no recognized error code', function () {
    // Isolates ConnectionVerifier's own status-based check from
    // TikTokPublishException::isConfirmedDeadToken()'s error-code check:
    // 'access_token_revoked' IS present as error.code, but it's not one of
    // the codes that check recognizes (access_token_invalid,
    // access_token_expired, 10001, 10002) — so only the bare
    // `$response->status() === 401` clause in verifyTikTok() can catch it.
    Http::fake([
        config('trypost.platforms.tiktok.api').'/oauth/token/' => Http::response([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 86400,
        ], 200),
        config('trypost.platforms.tiktok.api').'/user/info/*' => Http::response(['error' => ['code' => 'access_token_revoked']], 401),
    ]);

    $account = SocialAccount::factory()->tiktok()->create();

    expect(fn () => (new ConnectionVerifier)->verify($account))
        ->toThrow(TokenExpiredException::class);

    // 1 refresh call (hits the faked 200) + verify called before, after, and
    // once more when the refresh rotated the token (refreshThenVerify's own
    // retry) — those three all hit the same faked 401, since the fake
    // ignores the token used.
    Http::assertSentCount(4);
});
