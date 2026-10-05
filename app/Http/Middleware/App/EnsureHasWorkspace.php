<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use App\Actions\Workspace\CreateWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasWorkspace
{
    /**
     * Ensure the user has a current workspace. Every app action operates on
     * it, and the Owner never picks one: an unset current workspace falls
     * back to their single workspace, which is created if it went missing.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->resolveCurrentWorkspace()) {
            CreateWorkspace::execute($user, ['name' => "{$user->name}'s Workspace"]);
            $user->load('currentWorkspace');
        }

        return $next($request);
    }
}
