<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasWorkspace
{
    /**
     * Ensure the user has a current workspace, redirecting to workspace
     * creation otherwise. Required in both SaaS and self-hosted modes — every
     * authenticated app action operates on the current workspace.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->currentWorkspace) {
            return redirect()->route('app.workspaces.create');
        }

        return $next($request);
    }
}
