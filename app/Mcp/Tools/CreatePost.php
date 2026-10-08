<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Post\CreatePost as CreatePostAction;
use App\Actions\Post\UpdatePost;
use App\Dto\MediaItem;
use App\Enums\Post\CreatedVia;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\TikTok\PrivacyLevel;
use App\Http\Requests\App\Post\UpdatePostRequest;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidationValidator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_post')]
#[Description('Creates a Post for one or more Social accounts from list_social_accounts. mode "draft" saves it, "scheduled" publishes it at scheduled_at (ISO 8601 with an offset, in the future), "now" publishes it right away. Content types default to Reel, TikTok Video and YouTube Short for a video, and Facebook Post and TikTok Photo for images. Every TikTok account needs a privacy_level; until the TikTok app passes its audit, only SELF_ONLY ("Only me") works.')]
class CreatePost extends Tool
{
    private const MODE_STATUSES = [
        'draft' => PostStatus::Draft,
        'scheduled' => PostStatus::Scheduled,
        'now' => PostStatus::Publishing,
    ];

    /**
     * The per-account text an Assistant may set; ContentType::textFields() says
     * which of them a Content type publishes.
     */
    private const TEXT_FIELDS = ['title', 'description', 'caption'];

    /**
     * The TikTok settings an Assistant may set, copied onto the Social account's meta.
     */
    private const TIKTOK_SETTINGS = ['privacy_level', 'allow_comments', 'allow_duet', 'allow_stitch', 'is_aigc'];

    /**
     * Creates the Post through the composer's create and update actions, and
     * checks it with the composer's update rules, so an Assistant gets the
     * same errors the composer would show. A rejected Post is rolled back.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        $workspace = $user->resolveCurrentWorkspace();
        $accounts = $workspace->socialAccounts()->active()->get()->keyBy('id');
        $assets = $workspace->getMedia('assets')->get()->keyBy('id');

        $validated = $this->validateInput($request->all(), $accounts, $assets);

        $mediaItems = collect(data_get($validated, 'media_ids', []))
            ->map(fn (string $id): MediaItem => MediaItem::fromMedia($assets->get($id)));
        $this->assertTextFieldsPublish($validated, $accounts, $mediaItems);

        $status = self::MODE_STATUSES[data_get($validated, 'mode')];
        $scheduledAt = data_get($validated, 'scheduled_at');

        $post = DB::transaction(function () use ($workspace, $user, $validated, $accounts, $mediaItems, $status, $scheduledAt): Post {
            $post = CreatePostAction::execute($workspace, $user, [
                'content' => data_get($validated, 'content') ?? '',
                'created_via' => CreatedVia::Mcp,
            ]);

            $postPlatformIds = $post->postPlatforms()->pluck('id', 'social_account_id');

            $payload = [
                'status' => $status->value,
                'content' => data_get($validated, 'content') ?? '',
                'media' => $mediaItems->map(fn (MediaItem $item): array => $item->toArray())->all(),
                'scheduled_at' => $scheduledAt ? Carbon::parse($scheduledAt)->utc()->toIso8601String() : null,
                'platforms' => collect(data_get($validated, 'social_accounts'))
                    ->map(fn (array $entry): array => $this->platformPayload(
                        $entry,
                        $accounts->get(data_get($entry, 'id')),
                        (string) $postPlatformIds->get(data_get($entry, 'id')),
                        $mediaItems,
                    ))
                    ->all(),
            ];

            $this->composerValidator($post, $payload)->validate();

            return data_get(UpdatePost::execute($workspace, $post, $payload), 'post');
        });

        $post->refresh();

        return Response::structured([
            'post' => [
                'id' => $post->id,
                'status' => $post->status->value,
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                'goes_out' => match ($post->status) {
                    PostStatus::Publishing => __('mcp.post.goes_out_now'),
                    PostStatus::Scheduled => __('mcp.post.goes_out_at', ['time' => $post->scheduled_at->toIso8601String()]),
                    default => __('mcp.post.goes_out_never'),
                },
            ],
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()->description('The Post\'s text. It is the Facebook Post text, and the TikTok caption, Reel description and YouTube title when those are not given.'),
            'media_ids' => $schema->array()->items($schema->string())->description('Media ids from complete_media_upload, in order. One video, or up to the Platform\'s image limit.'),
            'mode' => $schema->string()->enum(array_keys(self::MODE_STATUSES))->description('draft saves it, scheduled publishes it at scheduled_at, now publishes it right away.')->required(),
            'scheduled_at' => $schema->string()->description('When to publish, as ISO 8601 with an offset, e.g. 2026-10-09T18:00:00+07:00. Required for scheduled.'),
            'social_accounts' => $schema->array()->items($schema->object([
                'id' => $schema->string()->description('The Social account\'s id from list_social_accounts.')->required(),
                'content_type' => $schema->string()->enum(array_column(ContentType::cases(), 'value'))->description('One of the account\'s content_types. Defaults from the media.'),
                'title' => $schema->string()->description('YouTube Short title (max 100), Facebook Reel title, or TikTok Photo title (max 90). Not used by other Content types.'),
                'description' => $schema->string()->description('YouTube Short description (max 5000 bytes, defaults to the content), Facebook Reel description, or TikTok Photo description (max 4000). Not used by other Content types.'),
                'caption' => $schema->string()->description('TikTok Video caption (max 2200). Defaults to the content. Not used by other Content types.'),
                'privacy_level' => $schema->string()->enum(PrivacyLevel::values())->description('TikTok only, required. Only SELF_ONLY works until the TikTok app is audited.'),
                'allow_comments' => $schema->boolean()->description('TikTok only.'),
                'allow_duet' => $schema->boolean()->description('TikTok Video only.'),
                'allow_stitch' => $schema->boolean()->description('TikTok Video only.'),
                'is_aigc' => $schema->boolean()->description('TikTok only: label the post as AI-generated.'),
            ]))->description('Where to post, one entry per Social account.')->required(),
        ];
    }

    /**
     * The tool's own input checks: what the composer gets from its UI (known
     * accounts and assets, an offset on the schedule time, a privacy choice for
     * every TikTok account) rather than from its update rules. The text and
     * TikTok settings are only collected here; PostPlatformMetaRules checks
     * them through the composer's rules.
     *
     * @param  array<string, mixed>  $input
     * @param  Collection<string, SocialAccount>  $accounts
     * @param  Collection<string, Media>  $assets
     * @return array<string, mixed>
     */
    private function validateInput(array $input, Collection $accounts, Collection $assets): array
    {
        $validator = Validator::make($input, [
            'content' => ['nullable', 'string'],
            'media_ids' => ['sometimes', 'array'],
            'media_ids.*' => ['string', 'distinct', Rule::in($assets->keys()->all())],
            'mode' => ['required', 'string', Rule::in(array_keys(self::MODE_STATUSES))],
            'scheduled_at' => ['required_if:mode,scheduled', 'prohibited_if:mode,now', 'nullable', 'string', 'regex:/(Z|[+-]\d{2}:?\d{2})$/i', 'date', 'after:now'],
            'social_accounts' => ['required', 'array', 'min:1'],
            'social_accounts.*.id' => ['required', 'string', 'distinct', Rule::in($accounts->keys()->all())],
            'social_accounts.*.content_type' => ['sometimes', 'nullable', 'string', Rule::enum(ContentType::class)],
            ...collect([...self::TEXT_FIELDS, ...self::TIKTOK_SETTINGS])
                ->mapWithKeys(fn (string $key): array => ["social_accounts.*.{$key}" => ['sometimes']])
                ->all(),
        ], [
            'media_ids.*.in' => __('mcp.post.media_not_found'),
            'social_accounts.*.id.in' => __('mcp.post.account_not_found'),
            'scheduled_at.regex' => __('mcp.post.scheduled_at_offset'),
        ]);

        $validator->after(function (ValidationValidator $validator) use ($accounts): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ((array) data_get($validator->getData(), 'social_accounts', []) as $index => $entry) {
                $account = $accounts->get(data_get($entry, 'id'));
                $contentType = ContentType::tryFrom((string) data_get($entry, 'content_type'));

                if ($contentType !== null && $contentType->platform() !== $account->platform) {
                    $validator->errors()->add("social_accounts.{$index}.content_type", __('mcp.post.content_type_platform', [
                        'type' => $contentType->value,
                        'account' => $account->accountDisplayName(),
                    ]));
                }

                if ($account->platform === Platform::TikTok && blank(data_get($entry, 'privacy_level'))) {
                    $validator->errors()->add("social_accounts.{$index}.privacy_level", __('mcp.post.tiktok_privacy_required', [
                        'account' => $account->accountDisplayName(),
                    ]));
                }
            }
        });

        return $validator->validate();
    }

    /**
     * One `platforms[]` entry of the composer's update payload.
     *
     * @param  array<string, mixed>  $entry
     * @param  Collection<int, MediaItem>  $mediaItems
     * @return array{id: string, content_type: string, meta: array<string, mixed>}
     */
    private function platformPayload(array $entry, SocialAccount $account, string $postPlatformId, Collection $mediaItems): array
    {
        $contentType = $this->contentType($entry, $account, $mediaItems);

        $settings = [
            ...$contentType->textFields(),
            ...($account->platform === Platform::TikTok ? self::TIKTOK_SETTINGS : []),
        ];

        return [
            'id' => $postPlatformId,
            'content_type' => $contentType->value,
            'meta' => collect($settings)
                ->mapWithKeys(fn (string $key): array => [$key => data_get($entry, $key)])
                ->reject(fn (mixed $value): bool => $value === null)
                ->all(),
        ];
    }

    /**
     * Rejects a Title, Description or caption the Social account's Content
     * type never publishes, rather than storing text that silently goes nowhere.
     *
     * @param  array<string, mixed>  $validated
     * @param  Collection<string, SocialAccount>  $accounts
     * @param  Collection<int, MediaItem>  $mediaItems
     *
     * @throws ValidationException
     */
    private function assertTextFieldsPublish(array $validated, Collection $accounts, Collection $mediaItems): void
    {
        $errors = [];

        foreach ((array) data_get($validated, 'social_accounts', []) as $index => $entry) {
            $contentType = $this->contentType($entry, $accounts->get(data_get($entry, 'id')), $mediaItems);

            foreach (self::TEXT_FIELDS as $field) {
                if (filled(data_get($entry, $field)) && ! in_array($field, $contentType->textFields(), true)) {
                    $errors["social_accounts.{$index}.{$field}"] = __('mcp.post.text_field_unused', [
                        'field' => $field,
                        'type' => $contentType->value,
                    ]);
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The given Content type, or the default for the account's Platform and the media.
     *
     * @param  array<string, mixed>  $entry
     * @param  Collection<int, MediaItem>  $mediaItems
     */
    private function contentType(array $entry, SocialAccount $account, Collection $mediaItems): ContentType
    {
        return ContentType::tryFrom((string) data_get($entry, 'content_type'))
            ?? ContentType::defaultForMedia($account->platform, $mediaItems);
    }

    /**
     * A validator built from UpdatePostRequest's rules, messages and after-hook,
     * bound to the new Post the way the composer's PUT route binds it.
     *
     * @param  array<string, mixed>  $payload
     */
    private function composerValidator(Post $post, array $payload): ValidationValidator
    {
        $formRequest = UpdatePostRequest::create("/{$post->id}", 'PUT', $payload);
        $route = (new Route('PUT', '{post}', []))->bind($formRequest);
        $route->setParameter('post', $post);
        $formRequest->setRouteResolver(fn (): Route => $route);

        $validator = Validator::make($formRequest->all(), $formRequest->rules(), $formRequest->messages(), $formRequest->attributes());
        $formRequest->withValidator($validator);

        return $validator;
    }
}
