<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function update(User $user, Account $account): bool
    {
        return $user->id === $account->owner_id;
    }
}
