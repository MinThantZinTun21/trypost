<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Actions\LiveStream\StopLiveStream;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\V1\LiveStreamResource;
use App\Models\LiveStream;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Live streams (ADR 0017): the Mobile app lists them and Stops one; the
 * Assistant creates them.
 */
class LiveStreamController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $liveStreams = $request->user()->resolveCurrentWorkspace()
            ->liveStreams()
            ->with('video')
            ->latest()
            ->latest('id')
            ->paginate((int) config('app.pagination.default'));

        return LiveStreamResource::collection($liveStreams);
    }

    public function stop(LiveStream $liveStream): LiveStreamResource
    {
        $this->authorize('stop', $liveStream);

        return new LiveStreamResource(StopLiveStream::execute($liveStream)->load('video'));
    }
}
