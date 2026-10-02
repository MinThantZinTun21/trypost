<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Actions\Workspace\CreateWorkspace;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    /**
     * Create a user with their own Account and a first Workspace.
     *
     * @param  array{name: string, email: string, password?: string, email_verified_at?: \DateTimeInterface|null}  $data
     */
    public static function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $account = Account::create([
                'name' => data_get($data, 'name')."'s Account",
            ]);

            $user = User::create([
                'name' => data_get($data, 'name'),
                'email' => data_get($data, 'email'),
                'password' => data_get($data, 'password'),
                'email_verified_at' => data_get($data, 'email_verified_at'),
                'account_id' => $account->id,
            ]);

            $account->update(['owner_id' => $user->id]);

            CreateWorkspace::execute($user, ['name' => data_get($data, 'name')."'s Workspace"]);

            return $user;
        });
    }
}
