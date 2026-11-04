<?php

declare(strict_types=1);

namespace App\Services\Social\Concerns;

use App\Models\SocialAccount;
use Google\Client as GoogleClient;

/**
 * Builds a Google API client signed in as a YouTube Social account.
 */
trait CreatesGoogleClient
{
    private function createGoogleClient(SocialAccount $account): GoogleClient
    {
        $client = app(GoogleClient::class);
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));

        $remainingSeconds = $account->token_expires_at
            ? max(0, (int) now()->diffInSeconds($account->token_expires_at, false))
            : 3600;

        $tokenData = [
            'access_token' => $account->access_token,
            'created' => time(),
            'expires_in' => $remainingSeconds,
        ];

        if ($account->refresh_token) {
            $tokenData['refresh_token'] = $account->refresh_token;
        }

        $client->setAccessToken($tokenData);

        return $client;
    }
}
