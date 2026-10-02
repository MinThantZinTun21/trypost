<?php

declare(strict_types=1);

namespace App\Actions\Workspace;

use App\Actions\User\ReassignCurrentWorkspace;
use App\Actions\User\SettleStrandedMember;
use App\Actions\User\StrandedSettlement;
use App\Models\Account;
use App\Models\Invite;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class DeleteWorkspace
{
    /**
     * Delete a workspace and settle stranded members (delete invitees who
     * lost their last membership on the account).
     *
     * The account row is locked so concurrent deletes on the same account
     * are serialized.
     */
    public static function execute(Workspace $workspace): void
    {
        $account = $workspace->account;
        $settlement = StrandedSettlement::none();

        DB::transaction(function () use ($workspace, $account, &$settlement): void {
            // Serialize deletes per account.
            if ($account?->id) {
                Account::query()->whereKey($account->id)->lockForUpdate()->first();
            }

            ReassignCurrentWorkspace::awayFromWorkspace(
                workspace: $workspace,
                attachOwnerFallback: true,
                account: $account,
            );

            self::pruneInvitesForWorkspace($workspace);

            // Capture paths inside the lock so uploads that raced into the
            // transaction are included in post-commit filesystem cleanup.
            $mediaPaths = PurgeWorkspace::execute($workspace);

            $settlement = new StrandedSettlement(mediaPaths: $mediaPaths);

            if ($account) {
                $settlement = $settlement->merge(
                    SettleStrandedMember::forAccountMembers(
                        $account,
                        exceptUserId: $account->owner_id,
                        onlyWithoutMemberships: true,
                    ),
                );
            }
        });

        $settlement->flush();
    }

    private static function pruneInvitesForWorkspace(Workspace $workspace): void
    {
        Invite::query()
            ->where('account_id', $workspace->account_id)
            ->whereNull('accepted_at')
            ->whereJsonContains('workspaces', $workspace->id)
            ->get()
            ->each(function (Invite $invite) use ($workspace): void {
                $remaining = collect(data_get($invite, 'workspaces', []))
                    ->reject(fn (mixed $id): bool => (string) $id === (string) $workspace->id)
                    ->values()
                    ->all();

                if ($remaining === []) {
                    $invite->delete();

                    return;
                }

                $invite->update(['workspaces' => $remaining]);
            });
    }
}
