<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\ContentIdea\ChangeContentIdeaStatus;
use App\Actions\ContentIdea\CreateContentIdea;
use App\Actions\ContentIdea\DeleteContentIdea;
use App\Actions\ContentIdea\UpdateContentIdea;
use App\Enums\ContentIdea\Status;
use App\Enums\Post\CreatedVia;
use App\Http\Requests\App\ContentIdea\StoreContentIdeaRequest;
use App\Http\Requests\App\ContentIdea\UpdateContentIdeaRequest;
use App\Http\Requests\App\ContentIdea\UpdateContentIdeaStatusRequest;
use App\Http\Resources\App\ContentIdeaResource;
use App\Models\ContentIdea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentIdeaController extends Controller
{
    public function index(Request $request, ?string $status = null): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('view', $workspace);

        $query = $workspace->contentIdeas();

        if ($status !== null) {
            $query->where('status', Status::from($status));
        }

        return Inertia::render('ideas/Index', [
            'ideas' => Inertia::scroll(fn () => ContentIdeaResource::collection(
                $query->newestFirst()->paginate(config('app.pagination.default'))
            )),
            'currentStatus' => $status,
        ]);
    }

    public function store(StoreContentIdeaRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageContentIdeas', $workspace);

        CreateContentIdea::execute($workspace, $request->validated(), CreatedVia::Web);

        return back();
    }

    public function show(ContentIdea $contentIdea): Response
    {
        $this->authorize('view', $contentIdea);

        return Inertia::render('ideas/Show', [
            'idea' => [
                ...(new ContentIdeaResource($contentIdea))->resolve(),
                'details' => $contentIdea->details,
                'details_html' => $contentIdea->detailsHtml(),
            ],
        ]);
    }

    public function update(UpdateContentIdeaRequest $request, ContentIdea $contentIdea): RedirectResponse
    {
        $this->authorize('update', $contentIdea);

        UpdateContentIdea::execute($contentIdea, $request->validated());

        return back();
    }

    public function updateStatus(UpdateContentIdeaStatusRequest $request, ContentIdea $contentIdea): RedirectResponse
    {
        $this->authorize('update', $contentIdea);

        ChangeContentIdeaStatus::execute($contentIdea, Status::from((string) $request->validated('status')));

        return back();
    }

    public function destroy(ContentIdea $contentIdea): RedirectResponse
    {
        $this->authorize('delete', $contentIdea);

        DeleteContentIdea::execute($contentIdea);

        session()->flash('flash.banner', __('ideas.flash.deleted'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.ideas.index');
    }
}
