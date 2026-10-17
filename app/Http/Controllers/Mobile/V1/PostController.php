<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Mobile\ListPostsRequest;
use App\Http\Resources\Mobile\V1\PostResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function index(ListPostsRequest $request): AnonymousResourceCollection
    {
        $query = $request->user()->resolveCurrentWorkspace()
            ->posts()
            ->with(['postPlatforms' => fn ($query) => $query->enabled()->with('socialAccount')]);

        $posts = $request->tab()->apply($query->getQuery())
            ->paginate((int) config('app.pagination.default'))
            ->withQueryString();

        return PostResource::collection($posts);
    }
}
