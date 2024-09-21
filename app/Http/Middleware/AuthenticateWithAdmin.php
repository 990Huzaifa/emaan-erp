<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\Admin;

class AuthenticateWithAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (empty($token)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        $tokenable = $accessToken->tokenable;

        if (!($tokenable instanceof Admin)) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        // Set the authenticated admin
        Auth::setUser($tokenable);

        return $next($request);
    }
}
