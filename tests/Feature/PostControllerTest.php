<?php

declare(strict_types=1);

use App\Enums\Post\CreatedVia;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
    ]);
});

// Index tests
test('posts index requires authentication', function () {
    $response = $this->get(route('app.posts.index'));

    $response->assertRedirect(route('login'));
});

test('posts index shows posts for current workspace', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('app.posts.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('posts/Index', false)
        ->has('posts.data', 1)
    );
});

test('posts index falls back to the single workspace when none is current', function () {
    $this->user->update(['current_workspace_id' => null]);

    $response = $this->actingAs($this->user)->get(route('app.posts.index'));

    $response->assertOk();
    expect($this->user->fresh()->current_workspace_id)->toBe($this->workspace->id);
});

// Calendar tests
test('calendar requires authentication', function () {
    $response = $this->get(route('app.calendar'));

    $response->assertRedirect(route('login'));
});

test('calendar shows posts for current week', function () {
    $response = $this->actingAs($this->user)->get(route('app.calendar'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('posts/Calendar')
        ->has('workspace')
        ->has('posts')
        ->has('currentWeekStart')
        ->has('view')
    );
});

test('calendar supports month view', function () {
    $response = $this->actingAs($this->user)->get(route('app.calendar', ['view' => 'month']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('view', 'month')
    );
});

test('calendar payload exposes post content for rendering', function () {
    $scheduledAt = now('UTC')->startOfWeek()->addDays(2)->setTime(12, 0);

    Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'content' => 'Caption visible in the calendar',
        'status' => PostStatus::Scheduled,
        'scheduled_at' => $scheduledAt,
    ]);

    $dateKey = $scheduledAt->format('Y-m-d');

    $response = $this->actingAs($this->user)->get(route('app.calendar'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where("posts.{$dateKey}.0.content", 'Caption visible in the calendar')
    );
});

test('calendar payload names the social account of each target', function () {
    $scheduledAt = now('UTC')->startOfWeek()->addDays(2)->setTime(12, 0);
    $this->socialAccount->update(['display_name' => 'Lucky Studio', 'username' => 'luckystudio']);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => $scheduledAt,
    ]);
    PostPlatform::factory()->facebook()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
        'enabled' => true,
    ]);

    $dateKey = $scheduledAt->format('Y-m-d');

    $this->actingAs($this->user)->get(route('app.calendar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where("posts.{$dateKey}.0.post_platforms.0.social_account.display_label", 'Lucky Studio')
            ->where("posts.{$dateKey}.0.post_platforms.0.social_account.username", 'luckystudio')
        );
});

test('calendar filtered to a social account shows only the posts that publish to it', function () {
    $scheduledAt = now('UTC')->startOfWeek()->addDays(2)->setTime(12, 0);
    $tiktok = SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id]);

    $schedule = function (string $content, SocialAccount $account, bool $enabled = true) use ($scheduledAt): Post {
        $post = Post::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'content' => $content,
            'status' => PostStatus::Scheduled,
            'scheduled_at' => $scheduledAt,
        ]);
        PostPlatform::factory()->{$account->platform->value}()->create([
            'post_id' => $post->id,
            'social_account_id' => $account->id,
            'enabled' => $enabled,
        ]);

        return $post;
    };

    $facebookPost = $schedule('Facebook post', $this->socialAccount);
    $schedule('TikTok post', $tiktok);
    $schedule('Facebook target switched off', $this->socialAccount, enabled: false);

    $dateKey = $scheduledAt->format('Y-m-d');

    $this->actingAs($this->user)->get(route('app.calendar', ['account' => $this->socialAccount->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has("posts.{$dateKey}", 1)
            ->where("posts.{$dateKey}.0.id", $facebookPost->id)
            ->where('filters.account', $this->socialAccount->id)
        );
});

test('calendar lists the workspace social accounts to filter by', function () {
    $tiktok = SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $this->workspace->id,
        'display_name' => 'Lucky Clips',
    ]);
    SocialAccount::factory()->tiktok()->create();

    $this->actingAs($this->user)->get(route('app.calendar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('socialAccounts', 2)
            ->where('socialAccounts.1.id', $tiktok->id)
            ->where('socialAccounts.1.platform', 'tiktok')
            ->where('socialAccounts.1.display_label', $tiktok->display_label)
            ->where('filters.account', null)
        );
});

test('calendar rejects a social account filter outside the workspace', function (Closure $makeAccountId) {
    $this->actingAs($this->user)
        ->get(route('app.calendar', ['account' => $makeAccountId()]))
        ->assertSessionHasErrors('account');
})->with([
    'another workspace' => [fn () => SocialAccount::factory()->create()->id],
    'not a uuid' => [fn () => 'facebook'],
]);

test('new post opens the composer without saving a Post and with no AI', function () {
    $this->actingAs($this->user)
        ->get(route('app.posts.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('posts/Edit', false)
            ->where('post.id', null)
            ->where('post.scheduled_at', null)
            ->has('post.post_platforms', 1)
            ->where('post.post_platforms.0.id', $this->socialAccount->id)
            ->where('post.post_platforms.0.social_account_id', $this->socialAccount->id)
            ->where('post.post_platforms.0.enabled', false)
            ->where('post.post_platforms.0.content_type', ContentType::FacebookPost->value)
            ->missing('templates')
            ->missing('aiEnabled')
        );

    expect(Post::query()->count())->toBe(0);
});

test('new post from a calendar day starts on that day', function () {
    $this->actingAs($this->user)
        ->get(route('app.posts.create', ['date' => '2026-06-15']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('post.scheduled_at', fn (string $scheduledAt): bool => str_starts_with($scheduledAt, '2026-06-15T09:00:00'))
        );

    expect(Post::query()->count())->toBe(0);
});

test('new post rejects an invalid date', function () {
    $this->actingAs($this->user)
        ->get(route('app.posts.create', ['date' => 'not-a-date']))
        ->assertSessionHasErrors(['date']);
});

test('new post requires authentication', function () {
    $this->get(route('app.posts.create'))->assertRedirect(route('login'));
});

test('new post sends the Owner to connect accounts when none is active', function () {
    $this->socialAccount->delete();

    $this->actingAs($this->user)
        ->get(route('app.posts.create'))
        ->assertRedirect(route('app.accounts'));
});

test('new post gives a user with no workspace one and sends them to connect accounts', function () {
    $newUser = User::factory()->create();

    $this->actingAs($newUser)->get(route('app.posts.create'))->assertRedirect(route('app.accounts'));

    expect($newUser->fresh()->current_workspace_id)->not->toBeNull();
});

test('duplicate opens the composer pre-filled from the original without saving a Post', function () {
    $youtube = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube,
    ]);
    $turnedOff = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
        'is_active' => false,
    ]);
    $original = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
        'content' => 'Copy me',
        'media' => [['id' => 'media-1', 'path' => 'medias/clip.mp4', 'type' => 'video', 'mime_type' => 'video/mp4']],
    ]);
    PostPlatform::factory()->create([
        'post_id' => $original->id,
        'social_account_id' => $youtube->id,
        'platform' => Platform::YouTube,
        'content_type' => ContentType::YouTubeShort,
        'enabled' => true,
        'meta' => ['title' => 'My Short'],
    ]);
    PostPlatform::factory()->create([
        'post_id' => $original->id,
        'social_account_id' => $turnedOff->id,
        'platform' => Platform::TikTok,
        'content_type' => ContentType::TikTokVideo,
        'enabled' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('app.posts.create', ['duplicate' => $original->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('post.id', null)
            ->where('post.content', 'Copy me')
            ->where('post.media.0.id', 'media-1')
            ->where('post.scheduled_at', null)
            ->has('post.post_platforms', 2)
            ->where('post.post_platforms', fn ($platforms): bool => collect($platforms)->contains(
                fn (array $platform): bool => $platform['social_account_id'] === $youtube->id
                    && $platform['enabled'] === true
                    && $platform['content_type'] === ContentType::YouTubeShort->value
                    && $platform['meta'] === ['title' => 'My Short'],
            ) && collect($platforms)->contains(
                fn (array $platform): bool => $platform['social_account_id'] === $this->socialAccount->id
                    && $platform['enabled'] === false,
            ))
        );

    expect(Post::query()->count())->toBe(1);
});

test('duplicate refuses a Post from another workspace', function () {
    $other = Post::factory()->create();

    $this->actingAs($this->user)
        ->get(route('app.posts.create', ['duplicate' => $other->id]))
        ->assertSessionHasErrors(['duplicate']);
});

// Store tests
test('store post requires authentication', function () {
    $this->post(route('app.posts.store'))->assertRedirect(route('login'));
});

test('store schedules a new Post with the composer payload', function () {
    $scheduledAt = now()->addDay()->startOfSecond();

    $response = $this->actingAs($this->user)->post(route('app.posts.store'), [
        'status' => PostStatus::Scheduled->value,
        'content' => 'Hello from the composer',
        'scheduled_at' => $scheduledAt->toIso8601String(),
        'platforms' => [
            ['social_account_id' => $this->socialAccount->id, 'content_type' => ContentType::FacebookPost->value, 'meta' => []],
        ],
    ]);

    $post = Post::query()->sole();

    $response->assertRedirect(route('app.posts.show', $post));
    $response->assertSessionHas('flash.bannerStyle', 'success');
    expect($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->content)->toBe('Hello from the composer')
        ->and($post->created_via)->toBe(CreatedVia::Web)
        ->and($post->scheduled_at->equalTo($scheduledAt))->toBeTrue()
        ->and($post->postPlatforms()->enabled()->sole()->social_account_id)->toBe($this->socialAccount->id);
});

test('store posts a new Post now through the publish job', function () {
    Bus::fake();

    $response = $this->actingAs($this->user)->post(route('app.posts.store'), [
        'status' => PostStatus::Publishing->value,
        'content' => 'Right now',
        'platforms' => [
            ['social_account_id' => $this->socialAccount->id, 'content_type' => ContentType::FacebookPost->value],
        ],
    ]);

    $post = Post::query()->sole();

    $response->assertRedirect(route('app.posts.show', $post));
    expect($post->status)->toBe(PostStatus::Publishing);
    Bus::assertDispatched(PublishPost::class, fn (PublishPost $job): bool => $job->post->is($post));
});

test('store saves nothing when the composer checks fail', function () {
    $this->actingAs($this->user)->post(route('app.posts.store'), [
        'status' => PostStatus::Scheduled->value,
        'content' => 'Too late',
        'scheduled_at' => now()->subHour()->toIso8601String(),
        'platforms' => [
            ['social_account_id' => $this->socialAccount->id, 'content_type' => ContentType::FacebookPost->value],
        ],
    ])->assertSessionHasErrors('scheduled_at');

    expect(Post::query()->count())->toBe(0);
});

test('store refuses a status other than scheduled or publishing', function () {
    $this->actingAs($this->user)->post(route('app.posts.store'), [
        'status' => 'draft',
        'content' => 'Nope',
        'platforms' => [
            ['social_account_id' => $this->socialAccount->id, 'content_type' => ContentType::FacebookPost->value],
        ],
    ])->assertSessionHasErrors('status');

    expect(Post::query()->count())->toBe(0);
});

test('store needs at least one Social account of the workspace', function () {
    $foreign = SocialAccount::factory()->create();

    $this->actingAs($this->user)->post(route('app.posts.store'), [
        'status' => PostStatus::Publishing->value,
        'content' => 'Nope',
        'platforms' => [],
    ])->assertSessionHasErrors('platforms');

    $this->actingAs($this->user)->post(route('app.posts.store'), [
        'status' => PostStatus::Publishing->value,
        'content' => 'Nope',
        'platforms' => [['social_account_id' => $foreign->id, 'content_type' => ContentType::FacebookPost->value]],
    ])->assertSessionHasErrors('platforms.0.social_account_id');

    expect(Post::query()->count())->toBe(0);
});

// Edit tests
test('edit post requires authentication', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->get(route('app.posts.edit', $post));

    $response->assertRedirect(route('login'));
});

test('edit post shows edit page', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
    ]);

    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('app.posts.edit', $post));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('posts/Edit')
        ->has('post')
        ->has('socialAccounts')
    );
});

test('edit post returns 404 for post from different workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $post = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('app.posts.edit', $post));

    $response->assertNotFound();
});

test('edit redirects to show for non-editable statuses', function () {
    foreach ([PostStatus::Published, PostStatus::PartiallyPublished, PostStatus::Publishing, PostStatus::Failed] as $status) {
        $post = Post::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'status' => $status,
        ]);

        PostPlatform::factory()->create([
            'post_id' => $post->id,
            'social_account_id' => $this->socialAccount->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('app.posts.edit', $post))
            ->assertRedirect(route('app.posts.show', $post));
    }
});

test('edit allows scheduled posts', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
    ]);

    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('app.posts.edit', $post))
        ->assertOk();
});

// Update tests
test('update post requires authentication', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->put(route('app.posts.update', $post), []);

    $response->assertRedirect(route('login'));
});

test('update post saves changes', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'content' => 'Original content',
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'content' => 'Updated content',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
            ],
        ],
    ]);

    $response->assertRedirect();

    $post->refresh();
    expect($post->content)->toBe('Updated content');
    $postPlatform->refresh();
    expect($postPlatform->content_type)->toBe(ContentType::FacebookPost);
});

test('update post cannot update published posts', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'content' => 'Test content',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
            ],
        ],
    ]);

    $response->assertRedirect();
});

test('cannot re-publish a failed post', function () {
    Bus::fake();

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Failed,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'publishing',
        'content' => 'Test content',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.bannerStyle', 'danger');

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Failed);
    Bus::assertNotDispatched(PublishPost::class);
});

test('cannot update a post in publishing state', function () {
    Bus::fake();

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Publishing,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'content' => 'Test content',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.bannerStyle', 'danger');

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Publishing);
    Bus::assertNotDispatched(PublishPost::class);
});

test('cannot update a partially published post', function () {
    Bus::fake();

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::PartiallyPublished,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'publishing',
        'content' => 'Test content',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.bannerStyle', 'danger');

    $post->refresh();
    expect($post->status)->toBe(PostStatus::PartiallyPublished);
    Bus::assertNotDispatched(PublishPost::class);
});

test('cannot update a published post', function () {
    Bus::fake();

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'publishing',
        'content' => 'Test content',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.bannerStyle', 'danger');

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Published);
    Bus::assertNotDispatched(PublishPost::class);
});

test('publish now updates scheduled_at to current time', function () {
    Mail::fake();
    $this->freezeTime();

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'content' => 'Test content',
        'scheduled_at' => now()->addDays(7),
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'publishing',
        'content' => 'Test content',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
            ],
        ],
    ]);

    $response->assertRedirect();

    $post->refresh();
    expect($post->scheduled_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('publish now is allowed when the stored scheduled_at has passed', function () {
    Bus::fake();
    $this->freezeTime();

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'content' => 'Test content',
        'scheduled_at' => now()->subHour(),
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'publishing',
        'content' => 'Test content',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
            ],
        ],
    ])->assertRedirect();

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Publishing)
        ->and($post->scheduled_at->toDateTimeString())->toBe(now()->toDateTimeString());
    Bus::assertDispatched(PublishPost::class);
});

test('update rejects scheduled status without a future scheduled_at', function (?string $existingScheduledAt) {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => $existingScheduledAt,
        'content' => 'Test content',
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $payload = [
        'status' => 'scheduled',
        'content' => 'Test content',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
            ],
        ],
    ];

    $this->actingAs($this->user)
        ->put(route('app.posts.update', $post), $payload)
        ->assertSessionHasErrors('scheduled_at');

    $this->actingAs($this->user)
        ->put(route('app.posts.update', $post), [
            ...$payload,
            'scheduled_at' => now()->subHour()->toIso8601String(),
        ])
        ->assertSessionHasErrors('scheduled_at');

    expect($post->fresh()->status)->toBe(PostStatus::Scheduled);
})->with([
    'missing schedule' => [null],
    'past schedule' => [now()->subDay()->toDateTimeString()],
]);

test('update accepts scheduled status reusing an existing future scheduled_at', function () {
    $scheduledAt = now()->addDay()->startOfSecond();

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => $scheduledAt,
        'content' => 'Test content',
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'scheduled',
        'content' => 'Test content',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
            ],
        ],
    ])->assertRedirect();

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->scheduled_at->toDateTimeString())->toBe($scheduledAt->toDateTimeString());
});

test('update moves a scheduled post to an explicit future scheduled_at', function () {
    $scheduledAt = now()->addDay()->startOfSecond();

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now()->addDays(3),
        'content' => 'Test content',
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'scheduled',
        'scheduled_at' => $scheduledAt->toIso8601String(),
        'content' => 'Test content',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
            ],
        ],
    ])->assertRedirect();

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->scheduled_at->toDateTimeString())->toBe($scheduledAt->toDateTimeString());
});

test('destroy post requires authentication', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->delete(route('app.posts.destroy', $post));

    $response->assertRedirect(route('login'));
});

test('destroy post deletes the post and redirects to posts index', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('app.posts.destroy', $post));

    $response->assertRedirect(route('app.posts.index'));
    expect(Post::find($post->id))->toBeNull();
});

test('destroy post with redirect param redirects to calendar', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('app.posts.destroy', $post).'?redirect=app.calendar');

    $response->assertRedirect(route('app.calendar'));
    expect(Post::find($post->id))->toBeNull();
});

test('destroy post with redirect param redirects to specified route', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('app.posts.destroy', $post).'?redirect=app.posts.index');

    $response->assertRedirect(route('app.posts.index'));
    expect(Post::find($post->id))->toBeNull();
});

test('destroy post returns 404 for post from different workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $post = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('app.posts.destroy', $post));

    $response->assertNotFound();
});

test('show page renders for non-editable posts', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
        'content' => 'Hello world',
    ]);

    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
        'enabled' => true,
        'platform_url' => 'https://facebook.com/posts/abc',
    ]);

    $response = $this->actingAs($this->user)->get(route('app.posts.show', $post));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('posts/Show', false)
        ->has('post.platforms', 1)
    );
});

test('show page exposes the content type of each platform', function () {
    $facebookAccount = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
    ]);

    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $facebookAccount->id,
        'platform' => Platform::Facebook,
        'content_type' => ContentType::FacebookReel,
        'enabled' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('app.posts.show', $post))
        ->assertInertia(fn ($page) => $page
            ->component('posts/Show', false)
            ->where('post.platforms.0.content_type', ContentType::FacebookReel->value)
        );
});

test('show page redirects scheduled posts to edit', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
    ]);

    $this->actingAs($this->user)
        ->get(route('app.posts.show', $post))
        ->assertRedirect(route('app.posts.edit', $post));
});

test('failed posts render show without redirecting to edit', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Failed,
    ]);

    $this->actingAs($this->user)
        ->get(route('app.posts.show', $post))
        ->assertOk();
});

test('destroy blocks published posts', function () {
    foreach ([PostStatus::Publishing, PostStatus::Published, PostStatus::PartiallyPublished] as $status) {
        $post = Post::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'status' => $status,
        ]);

        $this->actingAs($this->user)
            ->delete(route('app.posts.destroy', $post))
            ->assertRedirect();

        expect(Post::find($post->id))->not->toBeNull();
    }
});

test('show page returns 404 for post in another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $post = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('app.posts.show', $post))
        ->assertNotFound();
});

test('update post redirects to show page after publishing', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'content' => 'Test',
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'publishing',
        'content' => 'Test',
        'platforms' => [
            ['id' => $postPlatform->id, 'content_type' => ContentType::FacebookPost->value],
        ],
    ]);

    $response->assertRedirect(route('app.posts.show', $post));
});

test('update post rejects scheduling youtube short with image', function () {
    $youtubeAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'content' => 'Test',
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $youtubeAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'media' => [
            [
                'id' => 'media-1',
                'path' => 'media/foo.jpg',
                'url' => 'https://example.com/foo.jpg',
                'type' => 'image',
                'mime_type' => 'image/jpeg',
            ],
        ],
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::YouTubeShort->value,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('platforms.0.content_type');
});

test('update post rejects scheduling facebook reel with no media', function () {
    $facebookAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'content' => 'Test',
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $facebookAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookReel->value,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('platforms.0.content_type');
});

test('update post rejects invalid facebook aspect_ratio meta', function () {
    $facebookAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $facebookAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
                'meta' => ['aspect_ratio' => '2:1'],
            ],
        ],
    ]);

    $response->assertSessionHasErrors('platforms.0.meta.aspect_ratio');
});

test('update post accepts valid facebook aspect_ratio meta', function () {
    $facebookAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $facebookAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content_type' => ContentType::FacebookPost->value,
                'meta' => ['aspect_ratio' => '4:5'],
            ],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors('platforms.0.meta.aspect_ratio');
    $postPlatform->refresh();
    expect(data_get($postPlatform->meta, 'aspect_ratio'))->toBe('4:5');
});

test('scheduling without content_type per platform fails', function () {
    $youtubeAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'content' => 'Test',
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $youtubeAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'platforms' => [
            ['id' => $postPlatform->id],
        ],
    ]);

    $response->assertSessionHasErrors('platforms.0.content_type');
});

// Member authorization tests
test('member can view posts index', function () {
    $member = User::factory()->create([
        'account_id' => $this->workspace->account_id,
    ]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($member)->get(route('app.posts.index'));

    $response->assertOk();
});

test('member can create post', function () {
    $member = User::factory()->create([
        'account_id' => $this->workspace->account_id,
    ]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($member)->post(route('app.posts.store'));

    $response->assertRedirect();
});

test('analytics page and post metrics endpoint no longer exist', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    $postPlatform = PostPlatform::factory()->published()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $this->actingAs($this->user)->get('/analytics')->assertNotFound();
    $this->actingAs($this->user)
        ->getJson("/posts/{$post->id}/platforms/{$postPlatform->id}/metrics")
        ->assertNotFound();
});
