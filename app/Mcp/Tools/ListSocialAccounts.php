<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\PostPlatform\ContentType;
use App\Models\SocialAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_social_accounts')]
#[Description('Lists the Owner\'s connected Social accounts on Facebook Pages, TikTok and YouTube with the Content types each can post. Only post to accounts whose status is "connected".')]
#[IsReadOnly]
class ListSocialAccounts extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $accounts = $request->user()->resolveCurrentWorkspace()
            ?->socialAccounts()
            ->orderBy('platform')
            ->orderBy('display_name')
            ->get()
            ->map(fn (SocialAccount $account): array => [
                'id' => $account->id,
                'platform' => $account->platform->value,
                'display_name' => $account->display_name,
                'username' => $account->username,
                'status' => $account->status->value,
                'content_types' => array_values(array_map(
                    fn (ContentType $type): string => $type->value,
                    ContentType::forPlatform($account->platform),
                )),
            ])
            ->values()
            ->all() ?? [];

        return Response::structured(['social_accounts' => $accounts]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
