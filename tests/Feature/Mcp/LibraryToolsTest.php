<?php

declare(strict_types=1);

use App\Enums\Post\CreatedVia;
use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\CreateLibraryItem;
use App\Mcp\Tools\DeleteLibraryItem;
use App\Mcp\Tools\GetLibraryItem;
use App\Mcp\Tools\ListLibraryItems;
use App\Mcp\Tools\ScheduleLibraryItem;
use App\Models\LibraryItem;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->facebook = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    $this->tiktok = SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id]);
    $this->youtube = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
});

function libraryToolAsset(string $state = 'image'): Media
{
    $factory = Media::factory()->assets()->for(test()->workspace, 'mediable');

    return $state === 'video'
        ? $factory->video()->create(['meta' => ['duration' => 30]])
        : $factory->create();
}

function libraryToolVideoItem(array $attributes = []): LibraryItem
{
    return LibraryItem::factory()->withMedia([libraryToolAsset('video')])->create([
        'workspace_id' => test()->workspace->id,
        'facebook_text' => 'Facebook words',
        'tiktok_caption' => 'TikTok words #food',
        'youtube_title' => 'YouTube title',
        'youtube_description' => 'YouTube description',
        'youtube_tags' => ['ramen', 'quick dinner'],
        ...$attributes,
    ]);
}

// create_library_item
test('create_library_item saves every platform text and the media from the assistant', function () {
    $video = libraryToolAsset('video');

    SchedulerServer::actingAs($this->owner)
        ->tool(CreateLibraryItem::class, [
            'name' => 'Ramen in five minutes',
            'media_ids' => [$video->id],
            'facebook_text' => 'Dinner in five.',
            'tiktok_caption' => 'POV: ramen #food',
            'youtube_title' => 'Ramen in five minutes',
            'youtube_description' => 'Fast ramen.',
            'youtube_tags' => ['ramen', '#Ramen', 'quick dinner'],
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('library_item.id', LibraryItem::query()->sole()->id)
            ->where('library_item.name', 'Ramen in five minutes')
            ->where('library_item.media.0.id', $video->id)
            ->where('library_item.media.0.type', 'video')
            ->where('library_item.facebook_text', 'Dinner in five.')
            ->where('library_item.tiktok_caption', 'POV: ramen #food')
            ->where('library_item.youtube_title', 'Ramen in five minutes')
            ->where('library_item.youtube_description', 'Fast ramen.')
            ->where('library_item.youtube_tags', ['ramen', 'quick dinner'])
            ->where('library_item.posts_count', 0)
            ->where('library_item.created_via', 'mcp')
            ->etc());

    expect(LibraryItem::query()->sole()->created_via)->toBe(CreatedVia::Mcp);
});

test('create_library_item rejects what the web form rejects', function (Closure $arguments, string $field) {
    SchedulerServer::actingAs($this->owner)
        ->tool(CreateLibraryItem::class, $arguments())
        ->assertHasErrors([__($field)]);

    expect(LibraryItem::query()->count())->toBe(0);
})->with([
    'no name' => [fn () => ['facebook_text' => 'Words'], 'Name'],
    'tiktok caption too long' => [fn () => ['name' => 'A', 'tiktok_caption' => str_repeat('a', 2201)], 'caption'],
    'youtube title with angle brackets' => [fn () => ['name' => 'A', 'youtube_title' => 'a < b'], 'YouTube title'],
    'youtube tags too long' => [fn () => ['name' => 'A', 'youtube_tags' => [str_repeat('a', 250), str_repeat('b', 250)]], 'tags'],
    'unknown media' => [fn () => ['name' => 'A', 'media_ids' => ['9f0c0d6e-0000-4000-8000-000000000000']], 'mcp.post.media_not_found'],
    'a video and an image' => [fn () => ['name' => 'A', 'media_ids' => [libraryToolAsset('video')->id, libraryToolAsset()->id]], 'library.validation.media_mix'],
]);

// list_library_items and get_library_item
test('list_library_items returns the latest items with how often each was used', function () {
    $older = libraryToolVideoItem(['created_at' => now()->subDay()]);
    $newer = libraryToolVideoItem();
    Post::factory()->create(['workspace_id' => $this->workspace->id, 'library_item_id' => $older->id]);
    LibraryItem::factory()->create();

    SchedulerServer::actingAs($this->owner)
        ->tool(ListLibraryItems::class, [])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('library_items', 2)
            ->where('library_items.0.id', $newer->id)
            ->where('library_items.1.id', $older->id)
            ->where('library_items.1.posts_count', 1)
            ->etc());
});

test('list_library_items honours count', function () {
    LibraryItem::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    SchedulerServer::actingAs($this->owner)
        ->tool(ListLibraryItems::class, ['count' => 2])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->has('library_items', 2));
});

test('get_library_item returns one item and hides other workspaces', function () {
    $item = libraryToolVideoItem();

    SchedulerServer::actingAs($this->owner)
        ->tool(GetLibraryItem::class, ['id' => $item->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('library_item.id', $item->id)
            ->where('library_item.youtube_tags', ['ramen', 'quick dinner'])
            ->etc());

    SchedulerServer::actingAs($this->owner)
        ->tool(GetLibraryItem::class, ['id' => LibraryItem::factory()->create()->id])
        ->assertHasErrors([__('mcp.library_item.not_found')]);
});

// schedule_library_item
test('schedule_library_item schedules the item with each platform\'s text and links the Post', function () {
    $item = libraryToolVideoItem();
    $at = now()->addDay()->startOfMinute();

    SchedulerServer::actingAs($this->owner)
        ->tool(ScheduleLibraryItem::class, [
            'id' => $item->id,
            'mode' => 'scheduled',
            'scheduled_at' => $at->toIso8601String(),
            'social_accounts' => [
                ['id' => $this->facebook->id],
                ['id' => $this->tiktok->id, 'privacy_level' => 'SELF_ONLY'],
                ['id' => $this->youtube->id],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('post.id', Post::query()->sole()->id)
            ->where('post.status', 'scheduled')
            ->etc());

    $post = Post::query()->sole();
    $platforms = $post->postPlatforms()->enabled()->get()->keyBy('social_account_id');

    expect($post->status)->toBe(Status::Scheduled)
        ->and($post->library_item_id)->toBe($item->id)
        ->and($post->created_via)->toBe(CreatedVia::Mcp)
        ->and($post->content)->toBe('Facebook words')
        ->and(collect($post->media)->pluck('id')->all())->toBe(collect($item->media)->pluck('id')->all())
        ->and($platforms[$this->facebook->id]->content_type)->toBe(ContentType::FacebookReel)
        ->and($platforms[$this->tiktok->id]->meta)->toMatchArray(['caption' => 'TikTok words #food', 'privacy_level' => 'SELF_ONLY'])
        ->and($platforms[$this->youtube->id]->meta)->toMatchArray([
            'title' => 'YouTube title',
            'description' => 'YouTube description',
            'tags' => ['ramen', 'quick dinner'],
        ]);
});

test('schedule_library_item returns create_post\'s errors and creates nothing', function (Closure $arguments, string $error) {
    $item = libraryToolVideoItem();

    SchedulerServer::actingAs($this->owner)
        ->tool(ScheduleLibraryItem::class, ['id' => $item->id, ...$arguments()])
        ->assertHasErrors([__($error)]);

    expect(Post::query()->count())->toBe(0);
})->with([
    'TikTok without privacy' => [fn () => ['mode' => 'draft', 'social_accounts' => [['id' => test()->tiktok->id]]], 'privacy_level'],
    'unknown account' => [fn () => ['mode' => 'draft', 'social_accounts' => [['id' => SocialAccount::factory()->facebook()->create()->id]]], 'mcp.post.account_not_found'],
    'scheduled without a time' => [fn () => ['mode' => 'scheduled', 'social_accounts' => [['id' => test()->youtube->id]]], 'scheduled at'],
    'no accounts' => [fn () => ['mode' => 'draft', 'social_accounts' => []], 'social accounts'],
]);

test('schedule_library_item hides another workspace item', function () {
    SchedulerServer::actingAs($this->owner)
        ->tool(ScheduleLibraryItem::class, [
            'id' => LibraryItem::factory()->create()->id,
            'mode' => 'draft',
            'social_accounts' => [['id' => $this->youtube->id]],
        ])
        ->assertHasErrors([__('mcp.library_item.not_found')]);

    expect(Post::query()->count())->toBe(0);
});

// delete_library_item
test('delete_library_item deletes the item and keeps its posts and media', function () {
    $item = libraryToolVideoItem();
    $mediaId = data_get($item->media, '0.id');
    $post = Post::factory()->draft()->create([
        'workspace_id' => $this->workspace->id,
        'library_item_id' => $item->id,
    ]);

    SchedulerServer::actingAs($this->owner)
        ->tool(DeleteLibraryItem::class, ['id' => $item->id])
        ->assertOk()
        ->assertStructuredContent(['deleted' => true, 'id' => $item->id, 'posts_kept' => 1]);

    $this->assertModelMissing($item);
    expect($post->fresh()->library_item_id)->toBeNull()
        ->and(Media::query()->whereKey($mediaId)->exists())->toBeTrue();
});

test('delete_library_item hides another workspace item', function () {
    $other = LibraryItem::factory()->create();

    SchedulerServer::actingAs($this->owner)
        ->tool(DeleteLibraryItem::class, ['id' => $other->id])
        ->assertHasErrors([__('mcp.library_item.not_found')]);

    $this->assertModelExists($other);
});
