<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\TikTok\PrivacyLevel;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\TikTokVideoLookup;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    $this->account = SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $this->workspace->id,
        'username' => 'tiktoker',
        'token_expires_at' => now()->addDays(1),
    ]);
    $this->api = config('trypost.platforms.tiktok.api');
});

function tiktokLookupPostPlatform(string $platformPostId): PostPlatform
{
    return PostPlatform::factory()->tiktok()->create([
        'post_id' => test()->post->id,
        'social_account_id' => test()->account->id,
        'platform' => Platform::TikTok,
        'platform_post_id' => $platformPostId,
        'platform_url' => 'https://www.tiktok.com/@tiktoker',
        'meta' => ['privacy_level' => PrivacyLevel::PublicToEveryone->value],
    ]);
}

test('tiktok video lookup matches the public video by caption', function () {
    $this->post->update([
        'content' => 'Eu bato nessa tecla há 7 anos: construam produtos globais.',
    ]);

    Http::fake([
        $this->api.'/video/list/*' => Http::response([
            'data' => [
                'videos' => [[
                    'id' => '7682891910226234644',
                    'title' => 'Eu bato nessa tecla há 7 anos: construam produtos globais.',
                    'create_time' => now()->getTimestamp(),
                ]],
                'has_more' => false,
            ],
            'error' => ['code' => 'ok'],
        ]),
    ]);

    $postPlatform = tiktokLookupPostPlatform('v_pub_url~v2-1.7682889326782842900');

    expect((new TikTokVideoLookup)->findVideoIdByCaption($postPlatform))->toBe('7682891910226234644');
});

test('tiktok video lookup never matches an untitled video from the list', function () {
    $this->post->update(['content' => 'A caption that no listed video carries']);

    Http::fake([
        $this->api.'/video/list/*' => Http::response([
            'data' => [
                'videos' => [['id' => '7000000000000000001', 'title' => '', 'create_time' => now()->getTimestamp()]],
                'has_more' => false,
            ],
            'error' => ['code' => 'ok'],
        ]),
    ]);

    $postPlatform = tiktokLookupPostPlatform('v_pub_url~v2-1.untitled');

    expect((new TikTokVideoLookup)->findVideoIdByCaption($postPlatform))->toBeNull();
});

test('tiktok video lookup does not scan the video list for a self only post', function () {
    $this->post->update(['content' => 'Private caption']);

    Http::fake();

    $postPlatform = tiktokLookupPostPlatform('v_pub_url~v2-1.private');
    $postPlatform->update(['meta' => ['privacy_level' => PrivacyLevel::SelfOnly->value]]);

    expect((new TikTokVideoLookup)->findVideoIdByCaption($postPlatform))->toBeNull();

    Http::assertNothingSent();
});

test('tiktok video lookup stops scanning at videos older than the publish instead of claiming a same-caption repost', function () {
    $this->post->update(['content' => 'Same caption, posted twice']);

    Http::fake([
        $this->api.'/video/list/*' => Http::response([
            'data' => [
                'videos' => [[
                    'id' => '7000000000000000002',
                    'title' => 'Same caption, posted twice',
                    'create_time' => now()->subDays(3)->getTimestamp(),
                ]],
                'has_more' => true,
                'cursor' => now()->subDays(3)->getTimestampMs(),
            ],
            'error' => ['code' => 'ok'],
        ]),
    ]);

    $postPlatform = tiktokLookupPostPlatform('v_pub_url~v2-1.repost');
    $postPlatform->update(['published_at' => now()]);

    expect((new TikTokVideoLookup)->findVideoIdByCaption($postPlatform))->toBeNull();

    Http::assertSentCount(1);
});
