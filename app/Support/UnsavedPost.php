<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\PostPlatform\Status as PostPlatformStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The Post the composer opens for "New post" and Duplicate (ADR 0011). It is
 * never saved: it has no id, and each Post platform stands for one active
 * Social account, keyed by that account's id, until the Owner schedules it
 * or posts it now. A duplicate copies the original's content, media and,
 * for each account still active, its toggle, Content type and meta.
 */
class UnsavedPost
{
    /**
     * @param  Collection<int, SocialAccount>  $socialAccounts
     */
    public static function make(Collection $socialAccounts, ?Post $original = null, ?CarbonInterface $scheduledAt = null): Post
    {
        $originalPlatforms = $original?->postPlatforms()->get()->keyBy('social_account_id') ?? collect();

        $post = new Post([
            'content' => $original?->content ?? '',
            'media' => $original?->media ?? [],
            'status' => PostStatus::Scheduled,
            'scheduled_at' => $scheduledAt,
        ]);
        $post->id = null;

        return $post->setRelation('postPlatforms', $socialAccounts->map(
            fn (SocialAccount $account): PostPlatform => self::platform($account, $originalPlatforms->get($account->id)),
        )->values());
    }

    private static function platform(SocialAccount $account, ?PostPlatform $original): PostPlatform
    {
        $postPlatform = new PostPlatform([
            'social_account_id' => $account->id,
            'platform' => $account->platform->value,
            'platform_name' => $account->accountDisplayName(),
            'platform_username' => $account->username,
            'platform_avatar' => $account->getRawOriginal('avatar_url'),
            'content_type' => $original?->content_type ?? ContentType::defaultFor($account->platform),
            'status' => PostPlatformStatus::Pending,
            'enabled' => $original?->enabled ?? false,
            'meta' => $original?->meta ?? [],
        ]);

        $postPlatform->id = $account->id;

        return $postPlatform->setRelation('socialAccount', $account);
    }
}
