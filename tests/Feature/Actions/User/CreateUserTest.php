<?php

declare(strict_types=1);

use App\Actions\User\CreateUser;
use App\Models\Account;

test('CreateUser creates the owner a default workspace and sets it as current', function () {
    $user = CreateUser::execute([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    expect($user->name)->toBe('Jane Doe');
    expect($user->email)->toBe('jane@example.com');
    expect($user->account_id)->not->toBeNull();
    expect(Account::find($user->account_id))->not->toBeNull();

    $workspace = $user->workspaces()->first();
    expect($user->workspaces()->count())->toBe(1);
    expect($workspace->name)->toBe("Jane Doe's Workspace");
    expect($workspace->account_id)->toBe($user->account_id);
    expect($user->fresh()->current_workspace_id)->toBe($workspace->id);
});

test('CreateUser sets account owner_id to the new user', function () {
    $user = CreateUser::execute([
        'name' => 'Jane Doe',
        'email' => 'jane2@example.com',
        'password' => 'secret123',
    ]);

    expect($user->account->owner_id)->toBe($user->id);
});
