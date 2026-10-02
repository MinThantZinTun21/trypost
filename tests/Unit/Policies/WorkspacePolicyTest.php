<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use App\Policies\WorkspacePolicy;

beforeEach(function () {
    $this->policy = new WorkspacePolicy;
});

test('the Owner can do everything in their workspace', function (string $ability) {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
    ]);

    expect($this->policy->{$ability}($owner, $workspace))->toBeTrue();
})->with(['view', 'manageAccounts', 'createPost']);

test('nobody can touch a workspace on another account', function (string $ability) {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
    ]);
    $stranger = User::factory()->create();

    expect($this->policy->{$ability}($stranger, $workspace))->toBeFalse();
})->with(['view', 'manageAccounts', 'createPost']);

test('the team abilities no longer exist', function (string $ability) {
    expect(method_exists(WorkspacePolicy::class, $ability))->toBeFalse();
})->with(['create', 'update', 'delete', 'manageTeam', 'inviteMember']);
