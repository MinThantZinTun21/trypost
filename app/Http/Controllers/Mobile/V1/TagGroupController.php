<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Mobile\StoreTagGroupRequest;
use App\Http\Requests\App\Mobile\UpdateTagGroupRequest;
use App\Http\Resources\Mobile\V1\TagGroupResource;
use App\Models\TagGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tag groups and their Task tags (ADR 0016), managed from the Mobile app's
 * tag picker.
 */
class TagGroupController extends Controller
{
    /**
     * Every Tag group, wrapped in `data` like the paginated lists.
     */
    public function index(Request $request): JsonResponse
    {
        $tagGroups = $request->user()->resolveCurrentWorkspace()
            ->tagGroups()
            ->with('taskTags')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => TagGroupResource::collection($tagGroups)]);
    }

    public function store(StoreTagGroupRequest $request): JsonResponse
    {
        $tagGroup = $request->user()->resolveCurrentWorkspace()->tagGroups()->create($request->validated());

        return (new TagGroupResource($tagGroup->load('taskTags')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateTagGroupRequest $request, TagGroup $tagGroup): TagGroupResource
    {
        $this->authorize('manage', $tagGroup);

        $tagGroup->update($request->validated());

        return new TagGroupResource($tagGroup->load('taskTags'));
    }

    /**
     * Deletes the Tag group with its Task tags, which leave every Task.
     */
    public function destroy(TagGroup $tagGroup): Response
    {
        $this->authorize('manage', $tagGroup);

        $tagGroup->delete();

        return response()->noContent();
    }
}
