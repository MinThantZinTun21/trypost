<?php

declare(strict_types=1);

use App\Actions\Repurpose\CreateRepurpose;
use App\Actions\Repurpose\UpdateRepurpose;
use App\Enums\PostPlatform\ContentType;
use App\Enums\Repurpose\SourceFormat;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\Repurpose;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Repurpose\SourceIsFree;
use App\Support\Repurpose\SourceIsNotADestination;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->source = SocialAccount::factory()->for($this->workspace)->create(['platform' => Platform::Instagram]);
    $this->other = SocialAccount::factory()->for($this->workspace)->create(['platform' => Platform::Instagram]);
});

function selfDestination(SocialAccount $account): array
{
    return [
        'social_account_id' => $account->id,
        'content_type' => ContentType::InstagramReel->value,
        'meta' => [],
    ];
}

test('the source cannot be a destination of itself in the app', function () {
    $repurpose = Repurpose::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_social_account_id' => $this->source->id,
    ]);

    $payload = ['destinations' => [selfDestination($this->source)]];

    $this->actingAs($this->user)
        ->put(route('app.repurposes.update', $repurpose), $payload)
        ->assertSessionHasErrors(['destinations.0.social_account_id' => __('repurposes.errors.destination_is_source')]);

    expect($repurpose->fresh()->destinations)->toBe([]);
});

test('a source and format already watched is refused in the app', function () {
    Repurpose::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_social_account_id' => $this->other->id,
        'source_format' => SourceFormat::Reel,
    ]);

    $mine = Repurpose::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_social_account_id' => $this->source->id,
        'source_format' => SourceFormat::Reel,
    ]);

    $payload = ['source_social_account_id' => $this->other->id];

    $this->actingAs($this->user)
        ->put(route('app.repurposes.update', $mine), $payload)
        ->assertSessionHasErrors(['source_social_account_id' => __('repurposes.errors.source_already_used')]);

    expect($mine->fresh()->source_social_account_id)->toBe($this->source->id);
});

test('keeping the same source on an update is not read as a clash with itself', function () {
    $repurpose = Repurpose::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_social_account_id' => $this->source->id,
        'source_format' => SourceFormat::Reel,
    ]);

    $this->actingAs($this->user)
        ->put(route('app.repurposes.update', $repurpose), [
            'source_social_account_id' => $this->source->id,
            'source_format' => SourceFormat::Reel->value,
        ])
        ->assertSessionHasNoErrors();
});

test('a race past the validation still reads as a message, never as a constraint', function () {
    Repurpose::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_social_account_id' => $this->other->id,
        'source_format' => SourceFormat::Reel,
    ]);

    $mine = Repurpose::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_social_account_id' => $this->source->id,
        'source_format' => SourceFormat::Reel,
    ]);

    expect(fn () => UpdateRepurpose::execute($mine, ['source_social_account_id' => $this->other->id]))
        ->toThrow(ValidationException::class, __('repurposes.errors.source_already_used'));

    expect(fn () => CreateRepurpose::execute($this->workspace, $this->user, [
        'source_social_account_id' => $this->other->id,
        'source_format' => SourceFormat::Reel->value,
    ]))->toThrow(ValidationException::class, __('repurposes.errors.source_already_used'));
});

test('the helper itself refuses a source and format already watched', function () {
    Repurpose::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_social_account_id' => $this->other->id,
        'source_format' => SourceFormat::Reel,
    ]);

    expect(fn () => SourceIsFree::assert($this->workspace->id, $this->other->id, SourceFormat::Reel))
        ->toThrow(ValidationException::class);

    SourceIsFree::assert($this->workspace->id, $this->other->id, SourceFormat::Story);

    expect(true)->toBeTrue();
});

test('the helper lets a repurpose keep the pair it already holds', function () {
    $mine = Repurpose::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_social_account_id' => $this->other->id,
        'source_format' => SourceFormat::Reel,
    ]);

    SourceIsFree::assert($this->workspace->id, $this->other->id, SourceFormat::Reel, $mine->id);

    expect(true)->toBeTrue();
});

test('the helper itself refuses the source as its own destination', function () {
    $destinations = [
        ['social_account_id' => $this->other->id],
        ['social_account_id' => $this->source->id],
    ];

    expect(fn () => SourceIsNotADestination::assert($destinations, $this->source->id))
        ->toThrow(ValidationException::class);

    SourceIsNotADestination::assert(
        [['social_account_id' => $this->other->id]],
        $this->source->id,
    );

    expect(true)->toBeTrue();
});
