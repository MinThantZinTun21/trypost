<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\PostPolicy;
use Illuminate\Auth\Access\Response;

beforeEach(function () {
    $this->policy = new PostPolicy;

    $account = Account::factory()->create();
    $this->owner = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $this->owner->id]);
    $this->workspace = Workspace::factory()->create(['account_id' => $account->id, 'user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->owner->refresh();
});

test('the Owner can view, update, delete and duplicate their posts', function (string $ability) {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id]);

    expect($this->policy->{$ability}($this->owner, $post))->toBeTrue();
})->with(['view', 'update', 'delete', 'duplicate']);

test('a post from another workspace is denied as not found', function (string $ability) {
    $post = Post::factory()->create();

    $result = $this->policy->{$ability}($this->owner, $post);

    expect($result)->toBeInstanceOf(Response::class)
        ->and($result->status())->toBe(404);
})->with(['view', 'update', 'delete', 'duplicate']);
