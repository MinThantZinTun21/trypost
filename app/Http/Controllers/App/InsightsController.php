<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Insights\ListTopPosts;
use App\Actions\Insights\RefreshPageInsights;
use App\Actions\Insights\SummarizePageInsights;
use App\Enums\Insights\Range;
use App\Enums\SocialAccount\Status;
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

        $accounts = $workspace->socialAccounts()->facebookPages()->get();

        $account = $accounts->firstWhere('id', $request->validated('account')) ?? $accounts->first();
        $range = $request->range();
        $summary = $account === null ? null : SummarizePageInsights::execute($account, $range);

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
                'connected' => $account->status === Status::Connected,
                'has_snapshots' => $account->pageInsightSnapshots()->exists(),
                'refresh_pending' => $account->insightsRefreshPending(),
                'refresh_available_at' => $account->insightsRefreshAvailableAt()?->toIso8601String(),
            ],
            'range' => $range->value,
            'ranges' => Range::values(),
            'summary' => $summary,
            'topPosts' => $account === null ? [] : ListTopPosts::execute($account, data_get($summary, 'from'), data_get($summary, 'to')),
        ]);
    }

    public function refresh(RefreshInsightsRequest $request, SocialAccount $socialAccount): RedirectResponse
    {
        RefreshPageInsights::execute($socialAccount);

        return back();
    }
}
