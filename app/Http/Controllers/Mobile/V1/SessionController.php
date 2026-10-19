<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Mobile\SignInRequest;
use App\Models\MobileToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionController extends Controller
{
    /**
     * Signs the phone in with the Owner's web email and password and hands
     * it a Mobile token of its own, shown this once (ADR 0015).
     */
    public function store(SignInRequest $request): JsonResponse
    {
        $owner = $request->owner();

        return response()->json([
            'token' => MobileToken::issueFor($owner),
            'name' => $owner->name,
        ], Response::HTTP_CREATED);
    }

    /**
     * Signs this phone out: its Mobile token stops working. Other phones stay
     * signed in.
     */
    public function destroy(Request $request): Response
    {
        MobileToken::findByPlainToken((string) $request->bearerToken())?->delete();

        return response()->noContent();
    }
}
