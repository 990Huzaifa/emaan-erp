<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticateWithAdmin
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if (empty($token)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tokenable = $accessToken->tokenable;

        if ($tokenable instanceof \App\Models\User || $tokenable instanceof \App\Models\Admin) {
            // Authenticate the user
            Auth::login($tokenable);

            // Optionally, set the user resolver
            $request->setUserResolver(function () use ($tokenable) {
                return $tokenable;
            });

            return $next($request);
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
