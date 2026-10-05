<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Actions\Auth\LogoutAndInvalidateSession;
use App\Actions\Workspace\PurgeWorkspace;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    /**
     * Permanently delete the authenticated user and, when they own the
     * account, the account with its workspace.
     */
    public static function execute(User $user, Request $request): void
    {
        $account = $user->account;
        $isOwner = $user->isAccountOwner();

        $settlement = DB::transaction(function () use ($user, $account, $isOwner): StrandedSettlement {
            $user->update(['current_workspace_id' => null]);

            $settlement = $isOwner && $account
                ? self::deleteOwnedAccount($user, $account)
                : StrandedSettlement::none();

            $user->workspaces()->detach();
            $user->update(['account_id' => null]);

            return $settlement->merge(new StrandedSettlement(
                mediaPaths: PurgeUserAccess::execute($user),
            ));
        });

        $settlement->flush();

        // Logout while the user row still exists — SessionGuard cycles the
        // remember token via save(), which fails if the user was already deleted.
        LogoutAndInvalidateSession::execute($request);
        $user->delete();
    }

    /**
     * Tear down the owned account and its workspaces. Must run inside a DB
     * transaction (locks the account row).
     */
    private static function deleteOwnedAccount(User $owner, Account $account): StrandedSettlement
    {
        $locked = Account::query()->whereKey($account->id)->lockForUpdate()->first();

        if (! $locked) {
            return StrandedSettlement::none();
        }

        $settlement = StrandedSettlement::none();

        Workspace::query()
            ->where('account_id', $account->id)
            ->get()
            ->each(function (Workspace $workspace) use (&$settlement): void {
                $settlement = $settlement->merge(new StrandedSettlement(
                    mediaPaths: PurgeWorkspace::execute($workspace),
                ));
            });

        $owner->update(['account_id' => null]);

        if (Account::query()->whereKey($account->id)->exists()) {
            $account->delete();
        }

        return $settlement;
    }
}
