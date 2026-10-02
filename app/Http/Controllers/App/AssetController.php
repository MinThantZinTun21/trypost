<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\Asset\StoreChunkedAssetRequest;
use App\Services\Media\ChunkedAssetReceiver;
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
}
