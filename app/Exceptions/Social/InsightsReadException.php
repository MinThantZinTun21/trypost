<?php

declare(strict_types=1);

namespace App\Exceptions\Social;

use App\Services\Social\TokenRedactor;
use Exception;
use Illuminate\Http\Client\Response;

/**
 * Facebook refused or failed an Insights read. The message is shown to the
 * Owner on the Insights page, so it never carries a token.
 */
class InsightsReadException extends Exception
{
    public static function fromResponse(Response $response): self
    {
        $message = data_get($response->json(), 'error.message') ?? "Facebook answered HTTP {$response->status()}.";

        return new self((string) TokenRedactor::redact((string) $message));
    }

    public static function unreachable(string $reason): self
    {
        return new self((string) TokenRedactor::redact($reason));
    }
}
