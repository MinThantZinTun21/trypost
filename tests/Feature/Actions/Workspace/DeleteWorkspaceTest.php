<?php

declare(strict_types=1);

use App\Actions\Workspace\DeleteWorkspace;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;

test('delete workspace removes the only workspace of an account', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $user->id]);

    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    DeleteWorkspace::execute($workspace);

    expect(Workspace::find($workspace->id))->toBeNull()
        ->and($user->fresh()->current_workspace_id)->toBeNull();
});
