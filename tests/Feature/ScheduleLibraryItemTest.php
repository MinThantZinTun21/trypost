<?php

declare(strict_types=1);

use App\Enums\Post\CreatedVia;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\UserWorkspace\Role;
use App\Models\LibraryItem;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->facebook = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    $this->tiktok = SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id]);
    $this->youtube = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
});

function scheduledVideoItem(): LibraryItem
{
    $video = Media::factory()->assets()->for(test()->workspace, 'mediable')->video()->create(['meta' => ['duration' => 30]]);

    return LibraryItem::factory()->withMedia([$video])->create([
        'workspace_id' => test()->workspace->id,
        'facebook_text' => 'Facebook words',
        'tiktok_caption' => 'TikTok words #food',
        'youtube_title' => 'YouTube title',
        'youtube_description' => 'YouTube description',
        'youtube_tags' => ['ramen', 'quick dinner'],
    ]);
}

test('scheduling a video item creates a Draft with every platform text and opens the editor', function () {
    $item = scheduledVideoItem();

    $response = $this->actingAs($this->user)
        ->post(route('app.library.schedule', $item), [
            'social_account_ids' => [$this->facebook->id, $this->tiktok->id, $this->youtube->id],
        ]);

    $post = Post::query()->sole();
    $response->assertRedirect(route('app.posts.edit', $post));

    expect($post->status)->toBe(PostStatus::Draft)
        ->and($post->library_item_id)->toBe($item->id)
        ->and($post->created_via)->toBe(CreatedVia::Web)
        ->and($post->content)->toBe('Facebook words')
        ->and($post->media)->toEqual($item->media);

    $platforms = $post->postPlatforms()->get()->keyBy('social_account_id');

    expect($platforms->every(fn ($platform) => $platform->enabled))->toBeTrue()
        ->and($platforms[$this->facebook->id]->content_type)->toBe(ContentType::FacebookReel)
        ->and($platforms[$this->tiktok->id]->content_type)->toBe(ContentType::TikTokVideo)
        ->and($platforms[$this->tiktok->id]->meta)->toMatchArray(['caption' => 'TikTok words #food'])
        ->and($platforms[$this->youtube->id]->content_type)->toBe(ContentType::YouTubeShort)
        ->and($platforms[$this->youtube->id]->meta)->toMatchArray([
            'title' => 'YouTube title',
            'description' => 'YouTube description',
            'tags' => ['ramen', 'quick dinner'],
        ]);
});

test('scheduling enables only the chosen Social accounts', function () {
    $item = scheduledVideoItem();

    $this->actingAs($this->user)
        ->post(route('app.library.schedule', $item), ['social_account_ids' => [$this->youtube->id]])
        ->assertSessionHasNoErrors();

    $enabled = Post::query()->sole()->postPlatforms()->where('enabled', true)->pluck('social_account_id')->all();

    expect($enabled)->toBe([$this->youtube->id]);
});

test('scheduling an image item makes a Facebook Post and a TikTok Photo described by the caption', function () {
    $image = Media::factory()->assets()->for($this->workspace, 'mediable')->create();
    $item = LibraryItem::factory()->withMedia([$image])->create([
        'workspace_id' => $this->workspace->id,
        'tiktok_caption' => 'Photo words',
    ]);

    $this->actingAs($this->user)
        ->post(route('app.library.schedule', $item), ['social_account_ids' => [$this->facebook->id, $this->tiktok->id]])
        ->assertSessionHasNoErrors();

    $platforms = Post::query()->sole()->postPlatforms()->get()->keyBy('social_account_id');

    expect($platforms[$this->facebook->id]->content_type)->toBe(ContentType::FacebookPost)
        ->and($platforms[$this->tiktok->id]->content_type)->toBe(ContentType::TikTokPhoto)
        ->and($platforms[$this->tiktok->id]->meta)->toMatchArray(['description' => 'Photo words'])
        ->and($platforms[$this->tiktok->id]->meta)->not->toHaveKey('caption');
});

test('scheduling needs at least one of the workspace\'s Social accounts', function (Closure $ids, string $field) {
    $item = scheduledVideoItem();

    $this->actingAs($this->user)
        ->post(route('app.library.schedule', $item), ['social_account_ids' => $ids()])
        ->assertSessionHasErrors($field);

    expect(Post::query()->count())->toBe(0);
})->with([
    'none' => [fn () => [], 'social_account_ids'],
    'another workspace' => [fn () => [SocialAccount::factory()->facebook()->create()->id], 'social_account_ids.0'],
]);

test('scheduling another workspace item is not found', function () {
    $foreign = LibraryItem::factory()->create();

    $this->actingAs($this->user)
        ->post(route('app.library.schedule', $foreign), ['social_account_ids' => [$this->facebook->id]])
        ->assertNotFound();

    expect(Post::query()->count())->toBe(0);
});

test('the library counts the Posts made from each item', function () {
    $item = scheduledVideoItem();

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($this->user)
            ->post(route('app.library.schedule', $item), ['social_account_ids' => [$this->youtube->id]]);
    }

    $this->actingAs($this->user)
        ->get(route('app.library.index'))
        ->assertInertia(fn ($page) => $page
            ->where('items.data.0.posts_count', 2)
            ->has('socialAccounts', 3)
        );
});

test('deleting a library item keeps the Posts made from it', function () {
    $item = scheduledVideoItem();
    $this->actingAs($this->user)
        ->post(route('app.library.schedule', $item), ['social_account_ids' => [$this->youtube->id]]);

    $this->actingAs($this->user)->delete(route('app.library.destroy', $item));

    expect(Post::query()->sole()->library_item_id)->toBeNull();
});
