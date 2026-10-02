<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Schema;

test('account is a plain record owned by its user', function () {
    $user = User::factory()->create();
    $account = $user->account;

    expect($account->owner->is($user))->toBeTrue()
        ->and($account->users->pluck('id')->all())->toContain($user->id);
});

test('account exposes its workspaces', function () {
    $account = Account::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);

    expect($account->workspaces->pluck('id')->all())->toBe([$workspace->id]);
});

test('account carries no billing columns', function () {
    expect(Schema::hasColumn('accounts', 'plan_id'))->toBeFalse()
        ->and(Schema::hasColumn('accounts', 'stripe_id'))->toBeFalse()
        ->and(Schema::hasColumn('accounts', 'trial_ends_at'))->toBeFalse()
        ->and(Schema::hasTable('plans'))->toBeFalse()
        ->and(Schema::hasTable('subscriptions'))->toBeFalse()
        ->and(Schema::hasTable('subscription_items'))->toBeFalse();
});
