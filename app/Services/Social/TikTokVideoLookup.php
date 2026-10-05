<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Enums\TikTok\PrivacyLevel;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TikTokVideoLookup
{
    use HasSocialHttpClient;

    private const string VIDEO_LIST_FIELDS = 'id,title,create_time';

    private const int VIDEO_LIST_PAGE_SIZE = 20;

    private const int VIDEO_LIST_MAX_PAGES = 5;

    /**
     * `published_at` is stamped after TikTok finishes processing, which can trail
     * the video's `create_time` by up to the status-poll window (~1 h). A day of
     * slack keeps our own video inside the scan on slow publishes.
     */
    private const int PUBLISH_CLOCK_SLACK_SECONDS = 86400;

    private string $baseUrl;

    private string $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('trypost.platforms.tiktok.api');
    }

    /**
     * Public posts often stay on a Content Posting `publish_id` because TikTok
     * omits `publicaly_available_post_id` even after PUBLISH_COMPLETE. The video
     * still shows up on `video/list` with the caption we sent — match that so
     * the show-page link stops pointing at the profile. SELF_ONLY posts never
     * appear on the list, so they are not looked up.
     */
    public function findVideoIdByCaption(PostPlatform $postPlatform): ?string
    {
        $account = $postPlatform->socialAccount;

        if (! $account) {
            return null;
        }

        $this->prepareAccessToken($account);

        return $this->matchVideoFromRecentList($postPlatform);
    }

    /**
     * `video/list` is sorted by `create_time` desc, so scanning stops at the
     * first video older than the publish — anything past it cannot be ours, and
     * an older repost with the same caption must never be claimed.
     */
    private function matchVideoFromRecentList(PostPlatform $postPlatform): ?string
    {
        if (PrivacyLevel::tryFrom((string) data_get($postPlatform->meta, 'privacy_level')) === PrivacyLevel::SelfOnly) {
            return null;
        }

        $postPlatform->loadMissing('post');

        $caption = $this->normalizeCaption((string) $postPlatform->post?->content);

        if ($caption === '') {
            return null;
        }

        $notBefore = ($postPlatform->published_at ?? now())->getTimestamp() - self::PUBLISH_CLOCK_SLACK_SECONDS;
        $cursor = null;

        for ($page = 0; $page < self::VIDEO_LIST_MAX_PAGES; $page++) {
            $payload = ['max_count' => self::VIDEO_LIST_PAGE_SIZE];

            if (filled($cursor)) {
                $payload['cursor'] = $cursor;
            }

            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/video/list/?fields=".self::VIDEO_LIST_FIELDS, $payload);

            if ($response->failed()) {
                Log::warning('TikTok video list match failed', [
                    'body' => $this->redactResponseBody($response->body()),
                ]);

                return null;
            }

            $data = $response->json('data', []);

            foreach (data_get($data, 'videos', []) as $video) {
                if ((int) data_get($video, 'create_time', 0) < $notBefore) {
                    return null;
                }

                $videoId = $this->digitsOrNull(data_get($video, 'id'));
                $title = $this->normalizeCaption((string) data_get($video, 'title', ''));

                if ($videoId !== null && $this->captionsMatch($caption, $title)) {
                    return $videoId;
                }
            }

            $cursor = data_get($data, 'cursor');

            if (! data_get($data, 'has_more') || blank($cursor)) {
                return null;
            }
        }

        return null;
    }

    /**
     * `video/list` titles may be a truncated form of the caption we posted, so a
     * prefix match in either direction counts. An empty title never matches:
     * `str_starts_with($x, '')` is true and would claim any untitled video.
     */
    private function captionsMatch(string $posted, string $title): bool
    {
        return $title !== ''
            && (str_starts_with($posted, $title) || str_starts_with($title, $posted));
    }

    private function digitsOrNull(mixed $value): ?string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return ctype_digit($value) ? $value : null;
    }

    private function normalizeCaption(string $text): string
    {
        return (string) Str::of(app(ContentSanitizer::class)->displayText($text, Platform::TikTok))
            ->squish()
            ->lower();
    }

    private function prepareAccessToken(SocialAccount $account): void
    {
        if ($account->needsProactiveTokenRefresh()) {
            try {
                app(ConnectionVerifier::class)->refreshToken($account);
                $account->refresh();
            } catch (Throwable $e) {
                Log::warning('TikTok token refresh before video lookup failed', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->accessToken = $account->access_token;
    }

    private function getHttpClient(): PendingRequest
    {
        return $this->socialHttp()->asJson()->withToken($this->accessToken);
    }
}
