<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SocialAccountPolicy
{
    /**
     * Authorize access to a social account. Must live in the user's current
     * workspace; cross-workspace lookups deny as 404 so we don't leak
     * existence across tenants (same tenancy pattern as PostPolicy).
     */
    public function view(User $user, SocialAccount $account): bool|Response
    {
        if ($account->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return true;
    }

    /**
     * Only a Facebook Page has Insights (ADR 0004); anything else is a 404.
     * The Page's workspace decides who may see its Insights.
     */
    public function refreshInsights(User $user, SocialAccount $account): bool|Response
    {
        if ($account->platform !== Platform::Facebook) {
            return Response::denyAsNotFound();
        }

        $view = $this->view($user, $account);

        if ($view !== true) {
            return $view;
        }

        return $user->can('viewInsights', $account->workspace);
    }
}
