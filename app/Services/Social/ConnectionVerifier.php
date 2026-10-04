<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Exceptions\PlatformUnavailableException;
use App\Exceptions\Social\TikTokPublishException;
use App\Exceptions\Social\YouTubePublishException;
use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use App\Services\Social\Meta\GraphError;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ConnectionVerifier
{
    /**
     * Read and connect timeouts for a token refresh. Stated explicitly, even
     * though they match the client's defaults, so a change to those cannot
     * silently break the lock invariant below. Generous on purpose: giving up
     * on a request the provider already processed loses a single-use
     * refresh_token for good.
     */
    public const REFRESH_TIMEOUT_SECONDS = 30;

    public const REFRESH_CONNECT_TIMEOUT_SECONDS = 10;

    /**
     * Must exceed the slowest refresh the timeouts above allow, or the lock
     * lapses mid-flight and a second process
     * reuses the same single-use refresh_token. Pinned by a test.
     */
    public const REFRESH_LOCK_SECONDS = 120;

    /**
     * Verify that a social account connection is still valid.
     *
     * @throws TokenExpiredException if the connection is invalid
     * @throws PlatformUnavailableException if the platform's API is down
     */
    public function verify(SocialAccount $account): bool
    {
        // Hard-expired tokens cannot make API calls — refresh is mandatory.
        // For tokens that are still valid OR only "expiring soon", try the
        // verify endpoint FIRST with the current access_token. This avoids
        // rotating refresh_tokens unnecessarily — TikTok rotates its own, so
        // refreshing during a race disconnects an account whose access_token
        // still works.
        if ($account->is_token_expired) {
            return $this->refreshThenVerify($account);
        }

        try {
            return $this->callVerifyEndpoint($account);
        } catch (TokenExpiredException $e) {
            if (! $account->platform->hasTokenRefreshFlow()) {
                // Facebook Page tokens don't expire, so there is nothing to
                // refresh — retrying would just repeat this identical
                // rejection while burning a call against a budget shared
                // app-wide.
                throw $e;
            }

            // Verify returned 401: the access_token is actually invalid.
            // Refresh and retry once with the new token.
            return $this->refreshThenVerify($account, $e);
        }
    }

    /**
     * Refresh the token, then verify with the new one.
     *
     * If either the refresh is rejected (4xx) or the verify that follows it hits
     * a token a concurrent refresh has already rotated — including the sub-commit
     * window where a lock-skipped refresh reloads a not-yet-persisted token —
     * reload and, when another process has since persisted a fresh access_token,
     * verify with that instead of giving up. Providers that rotate their
     * refresh_token would otherwise disconnect a still-usable
     * account whenever two refreshes race and one loses.
     *
     * @throws TokenExpiredException
     * @throws PlatformUnavailableException
     */
    private function refreshThenVerify(SocialAccount $account, ?TokenExpiredException $original = null): bool
    {
        $accessTokenBeforeRefresh = $account->access_token;

        try {
            $this->refreshToken($account);

            return $this->callVerifyEndpoint($account);
        } catch (TokenExpiredException $e) {
            $account->refresh();

            if ($account->access_token !== $accessTokenBeforeRefresh) {
                return $this->callVerifyEndpoint($account);
            }

            throw $original ?? $e;
        }
    }

    /**
     * Pull a token out of a refresh response, refusing to persist a blank one.
     *
     * TokenRefreshClient classifies on HTTP status alone, so a 200 carrying no
     * token would otherwise overwrite a credential that still works.
     *
     * @param  array<string, mixed>|null  $data
     *
     * @throws PlatformUnavailableException
     */
    private function rotatedTokenFrom(?array $data, string $key, string $current): string
    {
        $token = data_get($data, $key);

        // Blank, not just missing: data_get()'s own default lets an explicit
        // null through and overwrite.
        return blank($token) ? $current : (string) $token;
    }

    private function tokenFrom(?array $data, Platform $platform, string $key = 'access_token'): string
    {
        $token = data_get($data, $key);

        if (blank($token)) {
            throw new PlatformUnavailableException(
                "{$platform->label()} returned a successful refresh with no {$key}."
            );
        }

        return (string) $token;
    }

    private function refreshHttp(): PendingRequest
    {
        return Http::timeout(self::REFRESH_TIMEOUT_SECONDS)
            ->connectTimeout(self::REFRESH_CONNECT_TIMEOUT_SECONDS);
    }

    /**
     * Check the stored access token as it is, skipping the refresh-and-retry
     * ladder verify() runs — which would re-send a refresh_token the provider
     * just rejected.
     *
     * @throws TokenExpiredException if the access token itself is rejected
     * @throws PlatformUnavailableException if the platform is unreachable
     */
    public function verifyAccessToken(SocialAccount $account): bool
    {
        return $this->callVerifyEndpoint($account);
    }

    /**
     * @throws TokenExpiredException
     */
    private function callVerifyEndpoint(SocialAccount $account): bool
    {
        return match ($account->platform) {
            Platform::Facebook => $this->verifyFacebook($account),
            Platform::TikTok => $this->verifyTikTok($account),
            Platform::YouTube => $this->verifyYouTube($account),
        };
    }

    /**
     * Refresh the account's token via the platform-specific OAuth flow.
     * Callers that want the smart "try access_token first" behavior should
     * use verify() instead. This method always attempts a refresh under
     * the per-account lock.
     *
     * @return bool whether a refresh actually ran — false means another
     *              process held the lock and this call proved nothing.
     *
     * @throws TokenExpiredException if refresh is rejected by the provider (4xx)
     * @throws PlatformUnavailableException if the platform is unreachable (5xx / network)
     */
    public function refreshToken(SocialAccount $account): bool
    {
        $lock = Cache::lock("token_refresh:{$account->id}", self::REFRESH_LOCK_SECONDS);

        if (! $lock->get()) {
            // Another process is already refreshing this token.
            $account->refresh();

            if ($account->is_token_expired) {
                // Returning false would hand the caller a token it knows is
                // dead; a publisher then posts with it, fails the post and
                // disconnects the account. Transient is the truth here.
                throw new PlatformUnavailableException(
                    "A {$account->platform->label()} token refresh is already in progress."
                );
            }

            return false;
        }

        try {
            if (! $account->platform->hasTokenRefreshFlow()) {
                // Facebook Page tokens have nothing per-account to refresh.
                return false;
            }

            match ($account->platform) {
                Platform::YouTube => $this->refreshYouTubeToken($account),
                Platform::TikTok => $this->refreshTikTokToken($account),
            };

            return true;
        } finally {
            $lock->release();
        }
    }

    private function refreshYouTubeToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for YouTube account');
        }

        $response = TokenRefreshClient::for(Platform::YouTube)->send(fn () => $this->refreshHttp()->asForm()
            ->post(config('trypost.platforms.youtube.oauth_api').'/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
            ]));

        $data = $response->json();

        $account->update([
            'access_token' => $this->tokenFrom($data, $account->platform),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);

        $account->refresh();
    }

    private function refreshTikTokToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for TikTok account');
        }

        $response = TokenRefreshClient::for(Platform::TikTok)->send(fn () => $this->refreshHttp()->asForm()
            ->post(config('trypost.platforms.tiktok.api').'/oauth/token/', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
                'client_key' => config('services.tiktok.client_id'),
                'client_secret' => config('services.tiktok.client_secret'),
            ]));

        $data = $response->json();

        $account->update([
            'access_token' => $this->tokenFrom($data, $account->platform),
            'refresh_token' => $this->rotatedTokenFrom($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);

        $account->refresh();
    }

    private function verifyFacebook(SocialAccount $account): bool
    {
        $response = Http::get(config('trypost.platforms.facebook.graph_api').'/me', [
            'fields' => 'id,name',
            'access_token' => $account->access_token,
        ]);

        if ($response->successful()) {
            return true;
        }

        throw GraphError::classifyVerifyFailure($response, 'Facebook');
    }

    private function verifyTikTok(SocialAccount $account): bool
    {
        $response = Http::withToken($account->access_token)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->get(config('trypost.platforms.tiktok.api').'/user/info/', [
                'fields' => 'open_id,display_name',
            ]);

        // 401 here (unlike a publish-time 401, which TikTok also returns for
        // scope_not_authorized/scope_permission_missed) is unambiguous: this
        // endpoint only needs the always-granted user.info.basic scope, so a
        // 401 can't be a scope gap. See TikTokPublishException::isConfirmedDeadToken().
        if (TikTokPublishException::isConfirmedDeadToken($response) || $response->status() === 401) {
            throw new TokenExpiredException('TikTok access token is invalid or expired');
        }

        if ($response->successful()) {
            return true;
        }

        throw new PlatformUnavailableException(
            "{$account->platform->label()} verify failed ({$response->status()}).",
            $response->status(),
        );
    }

    private function verifyYouTube(SocialAccount $account): bool
    {
        $response = Http::withToken($account->access_token)
            ->get(config('trypost.platforms.youtube.data_api').'/channels', [
                'part' => 'id',
                'mine' => 'true',
            ]);

        if (YouTubePublishException::isConfirmedDeadToken($response)) {
            throw new TokenExpiredException('YouTube access token is invalid or expired');
        }

        if ($response->successful()) {
            return true;
        }

        throw new PlatformUnavailableException(
            "{$account->platform->label()} verify failed ({$response->status()}).",
            $response->status(),
        );
    }
}
