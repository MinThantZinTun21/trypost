<?php

declare(strict_types=1);

namespace App\Actions\Workspace;

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function execute(User $user, array $data): Workspace
    {
        $attributes = [
            'name' => data_get($data, 'name'),
        ];

        $workspace = DB::transaction(function () use ($user, $attributes): Workspace {
            $workspace = Workspace::create([
                ...$attributes,
                'account_id' => $user->account_id,
                'user_id' => $user->id,
            ]);

            // Creator becomes Admin of the workspace they made. The Account Owner
            // is resolved separately (via account.owner_id) and outranks this role.
            $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
            $user->switchWorkspace($workspace);

            return $workspace;
        });

        return $workspace;
    }
}
