<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use App\Enums\PostPlatform\ContentType;
use App\Http\Resources\App\HandleInertiaRequests\AuthAccountResource;
use App\Http\Resources\App\HandleInertiaRequests\AuthUserResource;
use App\Http\Resources\App\HandleInertiaRequests\AuthWorkspaceResource;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $currentWorkspace = $user?->resolveCurrentWorkspace();
        $account = $user?->account;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? AuthUserResource::make($user) : null,
                'currentWorkspace' => $currentWorkspace ? AuthWorkspaceResource::make($currentWorkspace) : null,
                'account' => $account ? AuthAccountResource::make($account) : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => $request->session()->get('flash', []),
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * @return array<string, callable>
     */
    public function shareOnce(Request $request): array
    {
        return [
            ...parent::shareOnce($request),
            'contentTypeMediaRules' => fn (): array => ContentType::mediaRulesForFrontend(),
        ];
    }
}
