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
        $this->authorize('update', $post);

        if (PostStatusRules::blocksEditing($post)) {
            return $this->finalized();
        }

        $post = UpdatePostTexts::execute($request->user()->resolveCurrentWorkspace(), $post, [
            ...$request->safe()->only('content'),
            'platforms' => $request->platformTexts(),
        ]);

        return $this->show($post->refresh());
    }

    public function reschedule(ReschedulePostRequest $request, Post $post): PostResource|JsonResponse
    {
        $this->authorize('update', $post);

        if (PostStatusRules::blocksEditing($post)) {
            return $this->finalized();
        }

        $post = ReschedulePost::execute($request->user()->resolveCurrentWorkspace(), $post, $request->scheduledAt());

        return $this->show($post->refresh());
    }

    public function unschedule(Request $request, Post $post): PostResource|JsonResponse
    {
        $this->authorize('update', $post);

        if (PostStatusRules::blocksEditing($post)) {
            return $this->finalized();
        }

        $post = UnschedulePost::execute($request->user()->resolveCurrentWorkspace(), $post);

        return $this->show($post->refresh());
    }

    /**
     * The enabled Post platforms in the order PostComposerValidator::storedPayload()
     * sends them, so a `platforms.N.*` validation key names the resource's Nth one.
     *
     * @return array<string, Closure>
     */
    private static function enabledPlatforms(): array
    {
        return ['postPlatforms' => fn ($query) => $query->enabled()->orderBy('created_at')->orderBy('id')->with('socialAccount')];
    }

    /**
     * A publishing or finished Post can no longer change.
     */
    private function finalized(): JsonResponse
    {
        return response()->json(['message' => PostStatusRules::editBlockedMessage()], Response::HTTP_CONFLICT);
    }
}
