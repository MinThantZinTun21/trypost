<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\User\Goal;
use App\Enums\User\Persona;
use App\Enums\User\ReferralSource;
use App\Http\Requests\App\Welcome\StoreWelcomeConnectRequest;
use App\Http\Requests\App\Welcome\StoreWelcomeGoalsRequest;
use App\Http\Requests\App\Welcome\StoreWelcomePersonaRequest;
use App\Http\Requests\App\Welcome\StoreWelcomeReferralSourceRequest;
use App\Http\Resources\App\SocialAccountResource;
use App\Http\Resources\App\WelcomeSummaryResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class WelcomeController extends Controller
{
    public function persona(Request $request): InertiaResponse|RedirectResponse
    {
        if ($redirect = $this->redirectIfUnavailable($request)) {
            return $redirect;
        }

        $user = $request->user();

        return Inertia::render('welcome/Persona', [
            'personas' => array_map(fn (Persona $persona): string => $persona->value, Persona::cases()),
            'selected' => $user->persona?->value,
            'welcome' => WelcomeSummaryResource::make($user),
        ]);
    }

    public function storePersona(StoreWelcomePersonaRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfUnavailable($request)) {
            return $redirect;
        }

        $user = $request->user();
        $persona = (string) $request->validated('persona');

        $user->update(['persona' => $persona]);

        return redirect()->route('app.welcome.goals');
    }

    public function goals(Request $request): InertiaResponse|RedirectResponse
    {
        if ($redirect = $this->redirectIfStepIncomplete($request)) {
            return $redirect;
        }

        $user = $request->user();

        return Inertia::render('welcome/Goals', [
            'goals' => array_map(fn (Goal $goal): string => $goal->value, Goal::cases()),
            'selected' => $user->goals ?? [],
            'welcome' => WelcomeSummaryResource::make($user),
        ]);
    }

    public function storeGoals(StoreWelcomeGoalsRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfStepIncomplete($request)) {
            return $redirect;
        }

        $user = $request->user();
        $goals = array_values($request->validated('goals'));

        $user->update(['goals' => $goals]);

        return redirect()->route('app.welcome.referral-source');
    }

    public function referralSource(Request $request): InertiaResponse|RedirectResponse
    {
        if ($redirect = $this->redirectIfStepIncomplete($request, requireGoals: true)) {
            return $redirect;
        }

        $user = $request->user();

        return Inertia::render('welcome/ReferralSource', [
            'sources' => array_map(fn (ReferralSource $source): string => $source->value, ReferralSource::cases()),
            'selected' => $user->referral_source?->value,
            'welcome' => WelcomeSummaryResource::make($user),
        ]);
    }

    public function storeReferralSource(StoreWelcomeReferralSourceRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfStepIncomplete($request, requireGoals: true)) {
            return $redirect;
        }

        $user = $request->user();
        $referralSource = (string) $request->validated('referral_source');

        $user->update(['referral_source' => $referralSource]);

        return redirect()->route('app.welcome.connect');
    }

    public function connect(Request $request): InertiaResponse|RedirectResponse
    {
        if ($redirect = $this->redirectIfStepIncomplete($request, requireGoals: true, requireReferral: true)) {
            return $redirect;
        }

        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace !== null, Response::HTTP_NOT_FOUND);

        return Inertia::render('welcome/Connect', [
            'platforms' => SocialPlatform::connectableOptions(),
            'accounts' => SocialAccountResource::collection(
                $workspace->socialAccounts()->orderBy('id')->get(),
            )->resolve(),
            'welcome' => WelcomeSummaryResource::make($user),
        ]);
    }

    public function storeConnect(StoreWelcomeConnectRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfStepIncomplete($request, requireGoals: true, requireReferral: true)) {
            return $redirect;
        }

        abort_unless($request->user()->currentWorkspace !== null, Response::HTTP_NOT_FOUND);

        return redirect()->route('app.calendar');
    }

    private function redirectIfStepIncomplete(
        Request $request,
        bool $requireGoals = false,
        bool $requireReferral = false,
    ): ?RedirectResponse {
        if ($redirect = $this->redirectIfUnavailable($request)) {
            return $redirect;
        }

        $user = $request->user();

        if (! $user->persona) {
            return redirect()->route('app.welcome.persona');
        }

        if ($requireGoals && ! Goal::containsCurrent($user->goals)) {
            return redirect()->route('app.welcome.goals');
        }

        if ($requireReferral && ! $user->referral_source) {
            return redirect()->route('app.welcome.referral-source');
        }

        return null;
    }

    /**
     * Every Owner has app access (there is no billing gate), so the onboarding
     * steps are never shown and always send the user to the calendar.
     */
    private function redirectIfUnavailable(Request $request): ?RedirectResponse
    {
        return redirect()->route('app.calendar');
    }
}
