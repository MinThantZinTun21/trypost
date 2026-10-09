<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Insights\ListTopPosts;
use App\Actions\Insights\SummarizePageInsights;
use App\Enums\Insights\Range;
use App\Enums\SocialAccount\Platform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_page_insights')]
#[Description('Gets Page insights for one of the Owner\'s Facebook Page Social accounts: followers, new follows, unfollows, views, reach, engagements and video views over the last 7, 28 or 90 complete days, each with the previous period of the same length, plus the top Posts in that range by views. Reads what the app stored from Facebook (daily); it never asks Facebook. Reach is a sum of daily reach. TikTok and YouTube have no Insights.')]
#[IsReadOnly]
class GetPageInsights extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'social_account_id' => ['nullable', 'string', 'uuid'],
            'days' => ['nullable', 'integer', Rule::in(Range::values())],
        ]);

        $accounts = $request->user()->resolveCurrentWorkspace()
            ->socialAccounts()
            ->where('platform', Platform::Facebook)
            ->orderBy('created_at')
            ->orderBy('id');

        $accountId = data_get($validated, 'social_account_id');
        $account = $accountId === null ? $accounts->first() : $accounts->find($accountId);

        if ($account === null) {
            return Response::error(__($accountId === null ? 'mcp.insights.no_facebook_page' : 'mcp.insights.account_not_found'));
        }

        $summary = SummarizePageInsights::execute($account, Range::tryFrom((int) data_get($validated, 'days')) ?? Range::DEFAULT);

        return Response::structured([
            'social_account_id' => $account->id,
            'display_name' => $account->display_label,
            'read_at' => $account->insights_read_at?->toIso8601String(),
            'last_error' => $account->insights_error,
            'days' => data_get($summary, 'range'),
            'from' => data_get($summary, 'from'),
            'to' => data_get($summary, 'to'),
            'metrics' => data_get($summary, 'metrics'),
            'top_posts' => ListTopPosts::execute($account, data_get($summary, 'from'), data_get($summary, 'to')),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'social_account_id' => $schema->string()->description('Optional: a Facebook Page Social account id from list_social_accounts. Leave it out for the first Facebook Page.'),
            'days' => $schema->integer()->enum(Range::values())->default(Range::DEFAULT->value)->description('How many complete days to cover: 7, 28 or 90. Defaults to 28.'),
        ];
    }
}
