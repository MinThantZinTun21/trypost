<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Names the Owner the Mobile token belongs to.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['name' => $request->user()->name]);
    }
}
