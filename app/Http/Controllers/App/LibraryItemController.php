<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\LibraryItem\CreateLibraryItem;
use App\Actions\LibraryItem\DeleteLibraryItem;
use App\Actions\LibraryItem\UpdateLibraryItem;
use App\Enums\Post\CreatedVia;
use App\Http\Requests\App\LibraryItem\StoreLibraryItemRequest;
use App\Http\Requests\App\LibraryItem\UpdateLibraryItemRequest;
use App\Http\Resources\App\LibraryItemResource;
use App\Models\LibraryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryItemController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageLibrary', $workspace);

        return Inertia::render('library/Index', [
            'items' => Inertia::scroll(fn () => LibraryItemResource::collection(
                $workspace->libraryItems()->newestFirst()->paginate((int) config('app.pagination.default'))
            )),
        ]);
    }

    public function store(StoreLibraryItemRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageLibrary', $workspace);

        CreateLibraryItem::execute($workspace, $request->validated(), CreatedVia::Web);

        return back();
    }

    public function update(UpdateLibraryItemRequest $request, LibraryItem $libraryItem): RedirectResponse
    {
        $this->authorize('update', $libraryItem);

        UpdateLibraryItem::execute($libraryItem, $request->validated());

        return back();
    }

    public function destroy(LibraryItem $libraryItem): RedirectResponse
    {
        $this->authorize('delete', $libraryItem);

        DeleteLibraryItem::execute($libraryItem);

        session()->flash('flash.banner', __('library.flash.deleted'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.library.index');
    }
}
