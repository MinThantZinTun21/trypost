<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use App\Enums\Auth\SocialAuthProvider;
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

        $currentWorkspace = $user?->currentWorkspace?->load('media');
        $account = $user?->account;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? AuthUserResource::make($user) : null,
                'currentWorkspace' => $currentWorkspace ? AuthWorkspaceResource::make($currentWorkspace, $user) : null,
                'workspaces' => $user
                    ? $user->workspaces()->with('media')->get()->map(fn ($ws) => AuthWorkspaceResource::summary($ws))
                    : [],
                'account' => $account ? AuthAccountResource::make($account) : null,
            ],
            'legal' => [
                'terms' => (string) config('trypost.legal.terms_url'),
                'privacy' => (string) config('trypost.legal.privacy_url'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => $request->session()->get('flash', []),
            'applicationUrl' => config('app.url'),
            'env' => config('app.env'),
            'locale' => app()->getLocale(),
            'googleAuthEnabled' => SocialAuthProvider::Google->isEnabled(),
            'githubAuthEnabled' => SocialAuthProvider::GitHub->isEnabled(),
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
