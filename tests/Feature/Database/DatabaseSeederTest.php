<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('seeding creates exactly one owner with one account and one workspace', function () {
    config()->set('app.owner', [
        'name' => 'Jane Owner',
        'email' => 'jane@example.com',
        'password' => 'owner-secret',
    ]);

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1)
        ->and(Account::count())->toBe(1)
        ->and(Workspace::count())->toBe(1);

    $owner = User::sole();

    expect($owner->name)->toBe('Jane Owner')
        ->and($owner->email)->toBe('jane@example.com')
        ->and(Hash::check('owner-secret', $owner->password))->toBeTrue()
        ->and($owner->email_verified_at)->not->toBeNull()
        ->and($owner->account->owner_id)->toBe($owner->id)
        ->and($owner->current_workspace_id)->toBe(Workspace::sole()->id);
});

test('seeding twice does not create a second owner', function () {
    config()->set('app.owner.password', 'owner-secret');

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1)
        ->and(Account::count())->toBe(1)
        ->and(Workspace::count())->toBe(1);
});

test('seeding without a configured password generates one', function () {
    config()->set('app.owner.password', null);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
        ->expectsOutputToContain('Generated password (shown once)')
        ->assertSuccessful();

    expect(User::sole()->password)->not->toBeEmpty();
});
