<?php

declare(strict_types=1);

namespace App\Enums\Notification;

enum Type: string
{
    case PostPublished = 'post_published';
    case PostFailed = 'post_failed';
    case PostPartiallyPublished = 'post_partially_published';
    case AccountDisconnected = 'account_disconnected';
    case PostAtRisk = 'post_at_risk';
}
