<?php

declare(strict_types=1);

use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\TikTok\PrivacyLevel;
use App\Enums\UserWorkspace\Role;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

test('youtube description checks effective web metadata before scheduling or publishing', function (string $patch, bool $allowed, string $status) {
    $account = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
    $this->post->update([
        'content' => 'Short title',
        'status' => Status::Draft,
        'media' => $this->mediaPayload,
    ]);
    $this->postPlatform->update(['enabled' => false]);
    $platform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $account->id,
        'enabled' => true,
        'meta' => ['description' => str_repeat('a', 5001)],
    ]);
    $data = [
        'status' => $status,
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'media' => $this->mediaPayload,
    ];

    if ($patch !== 'omit') {
        $data['platforms'] = [['id' => $platform->id, 'content_type' => ContentType::YouTubeShort->value]];
        if ($patch !== 'row') {
            $data['platforms'][0]['meta'] = ['description' => $patch === 'clear' ? null : 'Valid description'];
        }
    }
    Queue::fake();
    $response = $this->actingAs($this->user)->put(route('app.posts.update', $this->post), $data);

    if ($allowed) {
        $response->assertSessionHasNoErrors();
        expect($this->post->fresh()->status->value)->toBe($status);

        if ($status === Status::Publishing->value) {
            Queue::assertPushed(PublishPost::class);
        }
    } else {
        $response->assertSessionHasErrors('platforms.0.meta.description');
        expect($this->post->fresh()->status)->toBe(Status::Draft);
        Queue::assertNotPushed(PublishPost::class);
    }
})->with([
    'stored invalid description' => ['omit', false],
    'retained invalid description' => ['row', false],
    'replaced description' => ['replace', true],
    'cleared description' => ['clear', true],
])->with([Status::Scheduled->value, Status::Publishing->value]);

test('youtube description reports and persists independent selected channel values', function () {
    $platforms = collect(range(1, 2))->map(function () {
        $account = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);

        return PostPlatform::factory()->youtube()->create([
            'post_id' => $this->post->id,
            'social_account_id' => $account->id,
            'meta' => [],
        ]);
    });
    $this->actingAs($this->user)->put(route('app.posts.update', $this->post), [
        'status' => Status::Draft->value,
        'platforms' => [
            ['id' => $platforms[0]->id, 'meta' => ['description' => 'First channel']],
            ['id' => $platforms[1]->id, 'meta' => ['description' => str_repeat('é', 2501)]],
        ],
    ])->assertSessionHasErrors('platforms.1.meta.description')->assertSessionDoesntHaveErrors('platforms.0.meta.description');
    $this->actingAs($this->user)->put(route('app.posts.update', $this->post), [
        'status' => Status::Draft->value,
        'content' => 'Short title',
        'platforms' => [
            ['id' => $platforms[0]->id, 'meta' => ['description' => 'First channel']],
            ['id' => $platforms[1]->id, 'meta' => ['description' => str_repeat('é', 2500)]],
        ],
    ])->assertSessionHasNoErrors();
    expect(data_get($platforms[0]->fresh()->meta, 'description'))->toBe('First channel')
        ->and(data_get($platforms[1]->fresh()->meta, 'description'))->toBe(str_repeat('é', 2500))
        ->and($this->post->fresh()->content)->toBe('Short title');
});

test('youtube description update reports one validation message', function (bool $hasSubmittedError) {
    $account = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
    $platform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $account->id,
        'meta' => ['description' => str_repeat('a', 5001)],
    ]);
    $data = [
        'status' => Status::Scheduled->value,
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'content' => 'Short title',
        'media' => $this->mediaPayload,
        'platforms' => [[
            'id' => $platform->id,
            'content_type' => ContentType::YouTubeShort->value,
        ]],
    ];
    $key = 'platforms.0.meta.description';

    if ($hasSubmittedError) {
        $data['platforms'][0]['meta'] = ['description' => str_repeat('é', 2501)];
    }

    $this->actingAs($this->user)->put(route('app.posts.update', $this->post), $data)
        ->assertSessionHasErrors($key);

    expect(session('errors')->get($key))->toBe([
        __('posts.form.youtube.description_max'),
    ]);
})->with([
    'stored invalid description' => [false],
    'submitted invalid description' => [true],
]);

test('youtube description validation keeps submitted channel order and rolls back updates', function () {
    $account = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
    $platform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $account->id,
        'enabled' => true,
        'meta' => ['description' => str_repeat('a', 5001)],
    ]);
    $secondAccount = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
    $secondPlatform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $secondAccount->id,
        'enabled' => false,
        'meta' => ['description' => 'Valid description'],
    ]);
    $this->post->update(['content' => 'Original title']);

    $this->actingAs($this->user)->put(route('app.posts.update', $this->post), [
        'status' => Status::Scheduled->value,
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'content' => 'Changed title',
        'media' => $this->mediaPayload,
        'platforms' => [
            ['id' => $secondPlatform->id, 'content_type' => ContentType::YouTubeShort->value],
            ['id' => $platform->id, 'content_type' => ContentType::YouTubeShort->value],
        ],
    ])->assertSessionHasErrors('platforms.1.meta.description')
        ->assertSessionDoesntHaveErrors('platforms.0.meta.description');

    expect($this->post->fresh()->status)->toBe(Status::Draft)
        ->and($this->post->fresh()->content)->toBe('Original title')
        ->and($this->post->fresh()->scheduled_at)->toBeNull()
        ->and($platform->fresh()->enabled)->toBeTrue()
        ->and($secondPlatform->fresh()->enabled)->toBeFalse()
        ->and($this->postPlatform->fresh()->enabled)->toBeTrue();
});

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    // Media payload used by tests that need to satisfy ContentTypeCompatibleWithMedia.
    $this->mediaPayload = [
        [
            'id' => 'test-media-video',
            'path' => 'media/2026-01/test-video.mp4',
            'url' => 'https://example.com/media/2026-01/test-video.mp4',
            'type' => 'video',
            'mime_type' => 'video/mp4',
            'original_filename' => 'test-video.mp4',
        ],
    ];
    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
    ]);
    $this->postPlatform = PostPlatform::factory()->tiktok()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        // Override factory default so we control privacy_level per test.
        'meta' => [],
    ]);
});

test('publishing a tiktok post without privacy_level is rejected', function () {
    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Publishing->value,
            'media' => $this->mediaPayload,
            'platforms' => [
                [
                    'id' => $this->postPlatform->id,
                    'content_type' => ContentType::TikTokVideo->value,
                    'meta' => [],
                ],
            ],
        ]);

    $response->assertSessionHasErrors('platforms.0.meta.privacy_level');
});

test('publishing a tiktok post with privacy_level passes privacy_level validation', function () {
    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Publishing->value,
            'media' => $this->mediaPayload,
            'platforms' => [
                [
                    'id' => $this->postPlatform->id,
                    'content_type' => ContentType::TikTokVideo->value,
                    'meta' => ['privacy_level' => PrivacyLevel::SelfOnly->value],
                ],
            ],
        ]);

    $response->assertSessionDoesntHaveErrors(['platforms.0.meta.privacy_level']);
});

test('saving a draft does not enforce media compatibility', function () {
    $youtubeAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube,
    ]);
    $youtubePlatform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $youtubeAccount->id,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Draft->value,
            'media' => [[
                'id' => 'test-media-image',
                'path' => 'media/2026-01/photo.jpg',
                'url' => 'https://example.com/media/2026-01/photo.jpg',
                'type' => 'image',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'photo.jpg',
            ]],
            'platforms' => [
                ['id' => $youtubePlatform->id, 'content_type' => ContentType::YouTubeShort->value],
            ],
        ]);

    $response->assertSessionDoesntHaveErrors(['platforms.0.content_type']);
});

test('publishing a tiktok post with an unknown privacy_level is rejected', function () {
    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Publishing->value,
            'media' => $this->mediaPayload,
            'platforms' => [
                [
                    'id' => $this->postPlatform->id,
                    'content_type' => ContentType::TikTokVideo->value,
                    'meta' => ['privacy_level' => 'EVERYONE'],
                ],
            ],
        ]);

    $response->assertSessionHasErrors('platforms.0.meta.privacy_level');
});

test('publishing a tiktok post as self only branded content is rejected', function () {
    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Publishing->value,
            'media' => $this->mediaPayload,
            'platforms' => [
                [
                    'id' => $this->postPlatform->id,
                    'content_type' => ContentType::TikTokVideo->value,
                    'meta' => [
                        'privacy_level' => PrivacyLevel::SelfOnly->value,
                        'brand_content_toggle' => true,
                    ],
                ],
            ],
        ]);

    $response->assertSessionHasErrors(['platforms.0.meta.privacy_level' => trans('posts.form.tiktok.privacy.private_disabled_branded')]);
});

test('saving a tiktok post as draft without privacy_level skips the privacy_level rule', function () {
    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Draft->value,
            'platforms' => [
                [
                    'id' => $this->postPlatform->id,
                    'content_type' => ContentType::TikTokVideo->value,
                    'meta' => [],
                ],
            ],
        ]);

    $response->assertSessionDoesntHaveErrors(['platforms.0.meta.privacy_level']);
});

test('scheduling a youtube post over 100 chars is rejected with the platform name', function () {
    $youtubeAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube,
    ]);
    $youtubePlatform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $youtubeAccount->id,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Scheduled->value,
            'content' => str_repeat('a', 137),
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'platforms' => [
                [
                    'id' => $youtubePlatform->id,
                    'content_type' => ContentType::YouTubeShort->value,
                    'meta' => [],
                ],
            ],
        ]);

    $response->assertSessionHasErrors('content');
    expect(session('errors')->get('content')[0])
        ->toContain('YouTube')
        ->toContain('100')
        ->toContain('37'); // over by 37
});

test('scheduling a youtube post within 100 chars passes content-length validation', function () {
    $youtubeAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube,
    ]);
    $youtubePlatform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $youtubeAccount->id,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Scheduled->value,
            'content' => str_repeat('a', 100),
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'platforms' => [
                [
                    'id' => $youtubePlatform->id,
                    'content_type' => ContentType::YouTubeShort->value,
                    'meta' => [],
                ],
            ],
        ]);

    $response->assertSessionDoesntHaveErrors('content');
});

test('saving an over-limit youtube post as draft skips the content-length rule', function () {
    $youtubeAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube,
    ]);
    $youtubePlatform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $youtubeAccount->id,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Draft->value,
            'content' => str_repeat('a', 1000),
            'platforms' => [
                [
                    'id' => $youtubePlatform->id,
                    'content_type' => ContentType::YouTubeShort->value,
                    'meta' => [],
                ],
            ],
        ]);

    $response->assertSessionDoesntHaveErrors('content');
});

test('scheduling across multiple platforms enforces the strictest content-length cap', function () {
    $facebookAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
    ]);
    $facebookPlatform = PostPlatform::factory()->facebook()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $facebookAccount->id,
    ]);

    $youtubeAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube,
    ]);
    $youtubePlatform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $youtubeAccount->id,
    ]);

    // 600 chars: fine for Facebook (10000 cap), over for YouTube (100 cap).
    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Scheduled->value,
            'content' => str_repeat('a', 600),
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'platforms' => [
                [
                    'id' => $facebookPlatform->id,
                    'content_type' => ContentType::FacebookPost->value,
                    'meta' => [],
                ],
                [
                    'id' => $youtubePlatform->id,
                    'content_type' => ContentType::YouTubeShort->value,
                    'meta' => [],
                ],
            ],
        ]);

    $response->assertSessionHasErrors('content');
    expect(session('errors')->get('content')[0])->toContain('YouTube');
});

test('draft save drops the removed media source keys', function () {
    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Draft->value,
            'media' => [[
                'id' => 'media-keep-meta',
                'path' => 'medias/photo.jpg',
                'url' => 'https://example.com/medias/photo.jpg',
                'type' => 'image',
                'mime_type' => 'image/jpeg',
                'source' => 'unsplash',
                'source_meta' => ['photo_id' => 'abc123'],
            ]],
            'platforms' => [],
        ]);

    $response->assertSessionDoesntHaveErrors();

    $this->post->refresh();
    expect(data_get($this->post->media, '0.path'))->toBe('medias/photo.jpg')
        ->and(data_get($this->post->media, '0'))->not->toHaveKey('source')
        ->and(data_get($this->post->media, '0'))->not->toHaveKey('source_meta');
});

test('youtube title saves on a draft and reloads in the editor', function () {
    $account = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
    $platform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $account->id,
        'meta' => [],
    ]);

    $this->actingAs($this->user)->put(route('app.posts.update', $this->post), [
        'status' => Status::Draft->value,
        'platforms' => [
            ['id' => $platform->id, 'meta' => ['title' => 'My own Short title']],
        ],
    ])->assertSessionHasNoErrors();

    expect(data_get($platform->fresh()->meta, 'title'))->toBe('My own Short title');

    $this->actingAs($this->user)->get(route('app.posts.edit', $this->post))
        ->assertInertia(fn ($page) => $page->where(
            'post.post_platforms',
            fn ($platforms) => data_get(collect($platforms)->firstWhere('id', $platform->id), 'meta.title') === 'My own Short title',
        ));
});

test('youtube title limits hold on a draft', function (string $title, string $key) {
    $account = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
    $platform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $account->id,
        'meta' => [],
    ]);

    $this->actingAs($this->user)->put(route('app.posts.update', $this->post), [
        'status' => Status::Draft->value,
        'platforms' => [
            ['id' => $platform->id, 'meta' => ['title' => $title]],
        ],
    ])->assertSessionHasErrors(['platforms.0.meta.title' => __($key)]);

    expect(data_get($platform->fresh()->meta, 'title'))->toBeNull();
})->with([
    'too long' => [str_repeat('a', 101), 'posts.form.youtube.title_max'],
    'angle brackets' => ['Use <b>bold</b>', 'posts.form.youtube.title_angle_brackets'],
]);

test('a title the youtube limits reject is fine on another platform', function () {
    $this->actingAs($this->user)->put(route('app.posts.update', $this->post), [
        'status' => Status::Draft->value,
        'platforms' => [
            ['id' => $this->postPlatform->id, 'meta' => ['title' => 'Use <b>bold</b>']],
        ],
    ])->assertSessionDoesntHaveErrors('platforms.0.meta.title');
});

test('a youtube title lifts the 100 character cap on the content', function (array $meta, bool $capped) {
    $account = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
    $platform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $account->id,
        'meta' => ['title' => 'Stored title'],
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $this->post), [
        'status' => Status::Scheduled->value,
        'content' => str_repeat('Long caption for every platform. ', 10),
        'media' => $this->mediaPayload,
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'platforms' => [
            ['id' => $platform->id, 'content_type' => ContentType::YouTubeShort->value, 'meta' => $meta],
        ],
    ]);

    $capped
        ? $response->assertSessionHasErrors('content')
        : $response->assertSessionDoesntHaveErrors('content');
})->with([
    'submitted title' => [['title' => 'My own Short title'], false],
    'stored title kept' => [[], false],
    'title cleared' => [['title' => null], true],
    'blank title' => [['title' => '   '], true],
]);
