<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Models\PostPlatform;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Social account's part of a Post for the Mobile app. The Social account
 * falls back to the Post platform's snapshot when the account was deleted.
 *
 * @mixin PostPlatform
 */
class PostPlatformResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'content_type' => $this->content_type?->value,
            'status' => $this->status->value,
            'meta' => (object) ($this->meta ?? []),
            'platform_url' => $this->platform_url,
            'error_message' => $this->error_message,
            'published_at' => $this->published_at?->toIso8601String(),
            'social_account' => [
                'id' => $this->social_account_id,
                'display_name' => $this->display_name,
                'username' => $this->display_username,
                'avatar_url' => $this->display_avatar,
            ],
        ];
    }
}
