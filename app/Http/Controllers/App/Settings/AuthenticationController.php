<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Http\Controllers\App\Controller;
use App\Http\Requests\App\Settings\AuthenticationPasswordRequest;
use App\Http\Requests\App\Settings\DestroyOtherSessionsRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticationController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile/Authentication', [
            'sessions' => $this->getSessions($request),
        ]);
    }

    public function updatePassword(AuthenticationPasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return back()->with('flash.success', __('settings.flash.password_updated'));
    }

    public function destroyOtherSessions(DestroyOtherSessionsRequest $request): RedirectResponse
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('flash.success', __('settings.authentication.sessions.flash_logged_out'));
    }

    /**
     * @return array<int, array{id: string, ip_address: string|null, user_agent: string|null, last_active: string, is_current: bool}>
     */
    private function getSessions(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return collect(
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->id)
                ->orderByDesc('last_activity')
                ->get()
        )->map(fn ($session) => [
            'id' => $session->id,
            'ip_address' => $session->ip_address,
            'user_agent' => $session->user_agent,
            'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            'is_current' => $session->id === $request->session()->getId(),
        ])->values()->all();
    }
}
