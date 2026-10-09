<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostPlatformResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform?->value,
            'content_type' => $this->content_type?->value,
            'meta' => $this->meta,
            'status' => $this->status?->value,
            'enabled' => $this->enabled,
            'platform_url' => $this->platform_url,
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'error_message' => $this->error_message,
            // Display fields fall back to snapshots (platform_name/username/avatar)
            // so deleted accounts still render correctly in the post history.
            'display_name' => $this->display_name,
            'display_username' => $this->display_username,
            'display_avatar' => $this->display_avatar,
            'social_account' => new SocialAccountSummaryResource($this->whenLoaded('socialAccount')),
            // Only Post platforms that have (or will get) Post insights carry
            // the key, so Stories and old unread Posts show no empty panel.
            'insights' => $this->when(
                $this->relationLoaded('insight') && ($this->insight?->read_at !== null || $this->readsInsights()),
                fn () => PostInsightResource::resolveOrNull($this->insight),
            ),
        ];
    }
}
