<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\Asset\CompleteDirectAssetRequest;
use App\Http\Requests\App\Asset\StoreChunkedAssetRequest;
use App\Http\Requests\App\Asset\StoreDirectAssetRequest;
use App\Services\Media\ChunkedAssetReceiver;
use App\Services\Media\DirectAssetReceiver;
use Illuminate\Http\JsonResponse;

class AssetController extends Controller
{
    public function storeChunked(StoreChunkedAssetRequest $request, ChunkedAssetReceiver $receiver): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        return $receiver->receive(
            $workspace,
            $request->user(),
            $request->validated('file_name'),
            $request->getContent(),
            (int) $request->validated('range_start'),
            (int) $request->validated('range_end'),
            (int) $request->validated('total_size'),
            (string) $request->validated('upload_id'),
            $request->duration(),
        )->toResponse();
    }

    public function storeDirect(StoreDirectAssetRequest $request, DirectAssetReceiver $receiver): JsonResponse
    {
        $this->authorize('createPost', $request->user()->currentWorkspace);

        return response()->json($receiver->start(
            $request->user(),
            $request->validated('file_name'),
            (string) $request->validated('upload_id'),
        ));
    }

    public function completeDirect(CompleteDirectAssetRequest $request, DirectAssetReceiver $receiver): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        return $receiver->complete(
            $workspace,
            $request->user(),
            (string) $request->validated('upload_id'),
            $request->duration(),
        )->toResponse();
    }
}
