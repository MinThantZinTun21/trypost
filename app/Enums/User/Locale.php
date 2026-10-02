<?php

declare(strict_types=1);

namespace App\Enums\User;

/**
 * The app is English only. Kept because an existing migration reads the
 * default when it adds the (since dropped) users.locale column.
 */
enum Locale: string
{
    case English = 'en';

    public const DEFAULT = self::English;
}
