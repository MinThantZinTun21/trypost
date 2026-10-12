<?php

declare(strict_types=1);

use App\Enums\Post\CreatedVia;
use App\Enums\UserWorkspace\Role;
use App\Models\LibraryItem;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

function libraryAsset(string $state = 'image', ?Workspace $workspace = null): Media
{
    $factory = Media::factory()->assets()->for($workspace ?? test()->workspace, 'mediable');

    return match ($state) {
        'video' => $factory->video()->create(['meta' => ['duration' => 30]]),
        'document' => $factory->document()->create(),
        default => $factory->create(),
    };
}

/**
 * @return array<string, mixed>
 */
function libraryPayload(array $overrides = []): array
{
    return [
        'name' => 'Ramen in five minutes',
        'facebook_text' => 'Dinner is ready in five minutes.',
        'tiktok_caption' => 'POV: ramen in 5 #food',
        'youtube_title' => 'Ramen in five minutes',
        'youtube_description' => 'The fastest ramen you will ever make.',
        'youtube_tags' => ['ramen', '#quick dinner', 'Ramen'],
        'media_ids' => [],
        ...$overrides,
    ];
}

// Index
test('the library requires authentication', function () {
    $this->get(route('app.library.index'))->assertRedirect(route('login'));
});

test('the library lists the workspace items newest first', function () {
    $video = libraryAsset('video');
    $older = LibraryItem::factory()->create(['workspace_id' => $this->workspace->id, 'created_at' => now()->subDay()]);
    $newer = LibraryItem::factory()->viaMcp()->withMedia([$video])->create(['workspace_id' => $this->workspace->id]);
    LibraryItem::factory()->create();

    $this->actingAs($this->user)
        ->get(route('app.library.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('library/Index')
            ->has('items.data', 2)
            ->where('items.data.0.id', $newer->id)
            ->where('items.data.0.name', $newer->name)
            ->where('items.data.0.created_via', CreatedVia::Mcp->value)
            ->where('items.data.0.media.0.id', $video->id)
            ->where('items.data.0.youtube_tags', $newer->youtube_tags)
            ->where('items.data.1.id', $older->id)
        );
});

test('the library paginates with the configured page size', function () {
    config(['app.pagination.default' => 2]);
    LibraryItem::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get(route('app.library.index'))
        ->assertInertia(fn ($page) => $page->has('items.data', 2));
});

// Store
test('storing a library item saves every platform text and the media from the web', function () {
    $video = libraryAsset('video');

    $this->actingAs($this->user)
        ->from(route('app.library.index'))
        ->post(route('app.library.store'), libraryPayload(['media_ids' => [$video->id]]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('app.library.index'));

    $item = LibraryItem::query()->sole();

    expect($item->workspace_id)->toBe($this->workspace->id)
        ->and($item->name)->toBe('Ramen in five minutes')
        ->and($item->facebook_text)->toBe('Dinner is ready in five minutes.')
        ->and($item->tiktok_caption)->toBe('POV: ramen in 5 #food')
        ->and($item->youtube_title)->toBe('Ramen in five minutes')
        ->and($item->youtube_description)->toBe('The fastest ramen you will ever make.')
        ->and($item->youtube_tags)->toBe(['ramen', 'quick dinner'])
        ->and($item->created_via)->toBe(CreatedVia::Web)
        ->and(collect($item->media)->pluck('id')->all())->toBe([$video->id])
        ->and($item->media[0]['path'])->toBe($video->path);
});

test('storing a library item keeps images in the order given', function () {
    $first = libraryAsset();
    $second = libraryAsset();

    $this->actingAs($this->user)
        ->post(route('app.library.store'), libraryPayload(['media_ids' => [$second->id, $first->id]]))
        ->assertSessionHasNoErrors();

    expect(collect(LibraryItem::query()->sole()->media)->pluck('id')->all())->toBe([$second->id, $first->id]);
});

test('only a name is required', function () {
    $this->actingAs($this->user)
        ->post(route('app.library.store'), ['name' => 'Just a name'])
        ->assertSessionHasNoErrors();

    $item = LibraryItem::query()->sole();

    expect($item->media)->toBe([])
        ->and($item->youtube_tags)->toBe([])
        ->and($item->facebook_text)->toBeNull();
});

test('storing a library item validates every field', function (Closure $payload, string $field) {
    $this->actingAs($this->user)
        ->post(route('app.library.store'), $payload())
        ->assertSessionHasErrors($field);

    expect(LibraryItem::query()->count())->toBe(0);
})->with([
    'missing name' => [fn () => libraryPayload(['name' => '']), 'name'],
    'name too long' => [fn () => libraryPayload(['name' => str_repeat('a', 256)]), 'name'],
    'facebook text too long' => [fn () => libraryPayload(['facebook_text' => str_repeat('a', 10001)]), 'facebook_text'],
    'tiktok caption too long' => [fn () => libraryPayload(['tiktok_caption' => str_repeat('a', 2201)]), 'tiktok_caption'],
    'youtube title too long' => [fn () => libraryPayload(['youtube_title' => str_repeat('a', 101)]), 'youtube_title'],
    'youtube title with angle brackets' => [fn () => libraryPayload(['youtube_title' => 'a < b']), 'youtube_title'],
    'youtube description too long' => [fn () => libraryPayload(['youtube_description' => str_repeat('a', 5001)]), 'youtube_description'],
    'youtube tags too long' => [fn () => libraryPayload(['youtube_tags' => [str_repeat('a', 250), str_repeat('b', 250)]]), 'youtube_tags'],
    'youtube tags not a list' => [fn () => libraryPayload(['youtube_tags' => 'ramen']), 'youtube_tags'],
    'media from another workspace' => [fn () => libraryPayload(['media_ids' => [libraryAsset('image', Workspace::factory()->create())->id]]), 'media_ids.0'],
    'two videos' => [fn () => libraryPayload(['media_ids' => [libraryAsset('video')->id, libraryAsset('video')->id]]), 'media_ids'],
    'a video and an image' => [fn () => libraryPayload(['media_ids' => [libraryAsset('video')->id, libraryAsset()->id]]), 'media_ids'],
    'a document' => [fn () => libraryPayload(['media_ids' => [libraryAsset('document')->id]]), 'media_ids'],
]);

test('storing a library item requires authentication', function () {
    $this->post(route('app.library.store'), libraryPayload())->assertRedirect(route('login'));

    expect(LibraryItem::query()->count())->toBe(0);
});

// Update
test('updating a library item replaces its texts and media', function () {
    $item = LibraryItem::factory()->viaMcp()->withMedia([libraryAsset('video')])->create(['workspace_id' => $this->workspace->id]);
    $image = libraryAsset();

    $this->actingAs($this->user)
        ->from(route('app.library.index'))
        ->put(route('app.library.update', $item), libraryPayload([
            'name' => 'Sharper name',
            'youtube_tags' => [],
            'tiktok_caption' => null,
            'media_ids' => [$image->id],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('app.library.index'));

    $item->refresh();

    expect($item->name)->toBe('Sharper name')
        ->and($item->tiktok_caption)->toBeNull()
        ->and($item->youtube_tags)->toBe([])
        ->and($item->created_via)->toBe(CreatedVia::Mcp)
        ->and(collect($item->media)->pluck('id')->all())->toBe([$image->id]);
});

test('updating another workspace item is not found', function () {
    $foreign = LibraryItem::factory()->create(['name' => 'Theirs']);

    $this->actingAs($this->user)
        ->put(route('app.library.update', $foreign), libraryPayload())
        ->assertNotFound();

    expect($foreign->fresh()->name)->toBe('Theirs');
});

// Destroy
test('deleting a library item keeps its media files', function () {
    Storage::fake();
    $video = libraryAsset('video');
    Storage::put($video->path, 'video');
    $item = LibraryItem::factory()->withMedia([$video])->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->delete(route('app.library.destroy', $item))
        ->assertRedirect(route('app.library.index'));

    expect(LibraryItem::query()->count())->toBe(0)
        ->and(Media::query()->whereKey($video->id)->exists())->toBeTrue();
    Storage::assertExists($video->path);
});

test('deleting another workspace item is not found', function () {
    $foreign = LibraryItem::factory()->create();

    $this->actingAs($this->user)
        ->delete(route('app.library.destroy', $foreign))
        ->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

// Policy
test('the policy hides another workspace item as not found', function (string $ability) {
    expect(Gate::forUser($this->user)->inspect($ability, LibraryItem::factory()->create())->status())->toBe(404);
})->with(['update', 'delete', 'schedule']);

test('the policy allows the owner on their own item', function (string $ability) {
    $item = LibraryItem::factory()->create(['workspace_id' => $this->workspace->id]);

    expect(Gate::forUser($this->user)->allows($ability, $item))->toBeTrue();
})->with(['update', 'delete', 'schedule']);
