<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Actions\Post\ReschedulePost;
use App\Actions\Post\UnschedulePost;
use App\Actions\Post\UpdatePostTexts;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Mobile\ListPostsRequest;
use App\Http\Requests\App\Mobile\ReschedulePostRequest;
use App\Http\Requests\App\Mobile\UpdatePostTextsRequest;
use App\Http\Resources\Mobile\V1\PostResource;
use App\Models\Post;
use App\Support\PostStatusRules;
use Closure;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller
{
    public function index(ListPostsRequest $request): AnonymousResourceCollection
    {
        $query = $request->user()->resolveCurrentWorkspace()
            ->posts()
            ->with(self::enabledPlatforms());

        $posts = $request->tab()->apply($query->getQuery())
            ->paginate((int) config('app.pagination.default'))
            ->withQueryString();

        return PostResource::collection($posts);
    }

    public function show(Post $post): PostResource
    {
        $this->authorize('view', $post);

        return new PostResource($post->load(self::enabledPlatforms()));
    }

    public function update(UpdatePostTextsRequest $request, Post $post): PostResource|JsonResponse
    {
        return $this->editRefusal($post) ?? $this->show(UpdatePostTexts::execute($request->user()->resolveCurrentWorkspace(), $post, [
            ...$request->safe()->only('content'),
            'platforms' => $request->platformTexts(),
        ])->refresh());
    }

    public function reschedule(ReschedulePostRequest $request, Post $post): PostResource|JsonResponse
    {
        return $this->editRefusal($post)
            ?? $this->show(ReschedulePost::execute($request->user()->resolveCurrentWorkspace(), $post, $request->scheduledAt())->refresh());
    }

    public function unschedule(Request $request, Post $post): PostResource|JsonResponse
    {
        return $this->editRefusal($post)
            ?? $this->show(UnschedulePost::execute($request->user()->resolveCurrentWorkspace(), $post)->refresh());
    }

    /**
     * The enabled Post platforms in the order PostComposerValidator::storedPayload()
     * sends them, so a `platforms.N.*` validation key names the resource's Nth one.
     *
     * @return array<string, Closure>
     */
    private static function enabledPlatforms(): array
    {
        return ['postPlatforms' => fn (HasMany $query): HasMany => $query->enabled()->inComposerOrder()->with('socialAccount')];
    }

    /**
     * Authorizes a change, then refuses it with a 409 when the Post is
     * publishing or finished and can no longer change; null lets it through.
     */
    private function editRefusal(Post $post): ?JsonResponse
    {
        $this->authorize('update', $post);

        if (! PostStatusRules::blocksEditing($post)) {
            return null;
        }

        return response()->json(['message' => PostStatusRules::editBlockedMessage()], Response::HTTP_CONFLICT);
    }
}
