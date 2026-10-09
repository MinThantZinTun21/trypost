<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\ContentIdea\CreateContentIdea;
use App\Enums\ContentIdea\Status;
use App\Enums\Post\CreatedVia;
use App\Http\Requests\App\ContentIdea\StoreContentIdeaRequest;
use App\Http\Resources\App\ContentIdeaResource;
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
                $query->latest()->paginate(config('app.pagination.default'))
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
}
