<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\Post\Status;
use App\Models\Post;
use App\Models\PostPlatform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_posts')]
#[Description('Lists the Owner\'s Posts, newest scheduled time first, one page per call. Filter by status: draft, scheduled or published (published includes partially published). Call get_post for one Post\'s per-account results.')]
#[IsReadOnly]
class ListPosts extends Tool
{
    private const array STATUSES = [Status::Draft, Status::Scheduled, Status::Published];

    private const int EXCERPT_LENGTH = 200;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(array_map(fn (Status $status): string => $status->value, self::STATUSES))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $request->user()->resolveCurrentWorkspace()
            ->posts()
            ->with(['postPlatforms' => fn ($query) => $query->enabled()->with('socialAccount')]);

        $query = match (Status::tryFrom((string) data_get($validated, 'status'))) {
            Status::Draft => $query->draft(),
            Status::Scheduled => $query->scheduled(),
            Status::Published => $query->published(),
            default => $query,
        };

        // PostgreSQL sorts nulls first on a descending order and MySQL last; undated drafts go last on both.
        $posts = $query->orderByRaw('scheduled_at is null')
            ->latest('scheduled_at')
            ->latest('created_at')
            ->paginate((int) config('app.pagination.default'), page: (int) data_get($validated, 'page', 1));

        return Response::structured([
            'posts' => $posts->getCollection()->map(fn (Post $post): array => [
                'id' => $post->id,
                'status' => $post->status->value,
                'content' => Str::limit(strip_tags((string) $post->content), self::EXCERPT_LENGTH),
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                'published_at' => $post->published_at?->toIso8601String(),
                'social_accounts' => $post->postPlatforms->map(fn (PostPlatform $postPlatform): array => [
                    'platform' => $postPlatform->platform->value,
                    'display_name' => $postPlatform->display_name,
                    'status' => $postPlatform->status->value,
                ])->values()->all(),
            ])->values()->all(),
            'page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'total' => $posts->total(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(array_map(fn (Status $status): string => $status->value, self::STATUSES))->description('Optional: only Posts with this status.'),
            'page' => $schema->integer()->description('Optional: the page to read, from 1.'),
        ];
    }
}
