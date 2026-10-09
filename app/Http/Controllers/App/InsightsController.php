<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Insights\RefreshPageInsights;
use App\Actions\Insights\SummarizePageInsights;
use App\Enums\Insights\Range;
use App\Enums\SocialAccount\Platform;
use App\Http\Requests\App\Insights\RefreshInsightsRequest;
use App\Http\Requests\App\Insights\ShowInsightsRequest;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InsightsController extends Controller
{
    public function index(ShowInsightsRequest $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('viewInsights', $workspace);

        $accounts = $workspace->socialAccounts()
            ->where('platform', Platform::Facebook)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $account = $accounts->firstWhere('id', $request->validated('account')) ?? $accounts->first();
        $range = $request->range();

        return Inertia::render('insights/Index', [
            'accounts' => $accounts->map(fn (SocialAccount $facebookAccount): array => [
                'id' => $facebookAccount->id,
                'name' => $facebookAccount->display_label,
                'avatar_url' => $facebookAccount->avatar_url,
            ])->values(),
            'account' => $account === null ? null : [
                'id' => $account->id,
                'name' => $account->display_label,
                'read_at' => $account->insights_read_at?->toIso8601String(),
                'error' => $account->insights_error,
                'has_snapshots' => $account->pageInsightSnapshots()->exists(),
                'refresh_pending' => $account->insightsRefreshPending(),
                'refresh_available_at' => $account->insightsRefreshAvailableAt()?->toIso8601String(),
            ],
            'range' => $range->value,
            'ranges' => Range::values(),
            'summary' => $account === null ? null : SummarizePageInsights::execute($account, $range),
        ]);
    }

    public function refresh(RefreshInsightsRequest $request, SocialAccount $socialAccount): RedirectResponse
    {
        RefreshPageInsights::execute($socialAccount);

        return back();
    }
}
