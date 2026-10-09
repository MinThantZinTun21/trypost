<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ContentIdea\Status;
use Illuminate\Validation\Rule;

/**
 * The one place that validates a Content idea, shared by the web FormRequests
 * and the MCP tools so the two entry points cannot drift.
 */
class ContentIdeaRules
{
    public const int TITLE_MAX = 255;

    public const int DETAILS_MAX = 20000;

    /**
     * @return array{title: array<int, mixed>, details: array<int, mixed>}
     */
    public static function content(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX],
            'details' => ['nullable', 'string', 'max:'.self::DETAILS_MAX],
        ];
    }

    /**
     * @return array{id: array<int, string>}
     */
    public static function id(): array
    {
        return [
            'id' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * @return array{status: array<int, mixed>}
     */
    public static function status(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(Status::class)],
        ];
    }
}
