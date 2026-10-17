<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\V1\SocialAccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Social accounts the Mobile app filters its post list by, in the
 * calendar's order, disconnected ones included. Wrapped in `data` like the
 * paginated lists, since resources are unwrapped app-wide.
 */
class SocialAccountController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $accounts = $request->user()->resolveCurrentWorkspace()
            ->socialAccounts()
            ->orderBy('platform')
            ->orderBy('display_name')
            ->get();

        return response()->json(['data' => SocialAccountResource::collection($accounts)]);
    }
}
