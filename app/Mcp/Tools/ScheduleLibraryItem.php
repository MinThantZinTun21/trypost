<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\PostPlatform\ContentType;
use App\Enums\TikTok\PrivacyLevel;
use App\Mcp\Tools\Concerns\HandlesLibraryItems;
use App\Models\LibraryItem;
use App\Models\SocialAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('schedule_library_item')]
#[Description('Makes a Post from a Library item for one or more Social accounts from list_social_accounts, with the item\'s media and each Platform\'s text: the Facebook text as the content, the TikTok caption, and the YouTube title, description and tags. mode "draft" saves it, "scheduled" publishes it at scheduled_at (ISO 8601 with an offset, in the future), "now" publishes it right away. It checks and creates the Post exactly like create_post. Every TikTok account needs a privacy_level; until the TikTok app passes its audit, only SELF_ONLY ("Only me") works.')]
class ScheduleLibraryItem extends Tool
{
    use HandlesLibraryItems;

    public function __construct(private readonly CreatePost $createPost) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string', 'uuid']]);

        $libraryItem = $this->findLibraryItem($request, (string) data_get($validated, 'id'));

        if ($libraryItem === null) {
            return Response::error(__('mcp.library_item.not_found'));
        }

        $accounts = $request->user()->resolveCurrentWorkspace()->socialAccounts()->active()->get()->keyBy('id');

        $post = $this->createPost->createPost($request->user(), [
            'content' => $libraryItem->facebook_text,
            'media_ids' => collect($libraryItem->media ?? [])->pluck('id')->all(),
            'mode' => $request->get('mode'),
            'scheduled_at' => $request->get('scheduled_at'),
            'social_accounts' => collect((array) $request->get('social_accounts', []))
                ->map(fn (mixed $entry): mixed => is_array($entry) ? $this->accountEntry($entry, $libraryItem, $accounts) : $entry)
                ->all(),
        ], $libraryItem);

        return Response::structured([
            'post' => CreatePost::postPayload($post),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The Library item\'s id, from list_library_items or create_library_item.')->required(),
            'mode' => $schema->string()->enum(array_keys(CreatePost::MODE_STATUSES))->description('draft saves it, scheduled publishes it at scheduled_at, now publishes it right away.')->required(),
            'scheduled_at' => $schema->string()->description('When to publish, as ISO 8601 with an offset, e.g. 2026-10-09T18:00:00+07:00. Required for scheduled.'),
            'social_accounts' => $schema->array()->items($schema->object([
                'id' => $schema->string()->description('The Social account\'s id from list_social_accounts.')->required(),
                'content_type' => $schema->string()->enum(array_column(ContentType::cases(), 'value'))->description('One of the account\'s content_types. Defaults from the item\'s media.'),
                'privacy_level' => $schema->string()->enum(PrivacyLevel::values())->description('TikTok only, required. Only SELF_ONLY works until the TikTok app is audited.'),
                'allow_comments' => $schema->boolean()->description('TikTok only.'),
                'allow_duet' => $schema->boolean()->description('TikTok Video only.'),
                'allow_stitch' => $schema->boolean()->description('TikTok Video only.'),
                'is_aigc' => $schema->boolean()->description('TikTok only: label the post as AI-generated.'),
            ]))->description('Where to post, one entry per Social account.')->required(),
        ];
    }

    /**
     * A create_post `social_accounts[]` entry: the Assistant's Content type and
     * TikTok settings, plus the item's texts for that Content type. An unknown
     * account passes through untouched so create_post reports it.
     *
     * @param  array<string, mixed>  $entry
     * @param  Collection<string, SocialAccount>  $accounts
     * @return array<string, mixed>
     */
    private function accountEntry(array $entry, LibraryItem $libraryItem, Collection $accounts): array
    {
        $account = $accounts->get(data_get($entry, 'id'));
        $settings = collect($entry)->only(['id', 'content_type', ...CreatePost::TIKTOK_SETTINGS])->all();

        if ($account === null) {
            return $settings;
        }

        $contentType = ContentType::tryFrom((string) data_get($entry, 'content_type'))
            ?? ContentType::defaultForMedia($account->platform, $libraryItem->mediaItems);

        return [
            ...$settings,
            'content_type' => $contentType->value,
            ...($contentType->platform() === $account->platform ? $libraryItem->textsFor($contentType) : []),
        ];
    }
}
