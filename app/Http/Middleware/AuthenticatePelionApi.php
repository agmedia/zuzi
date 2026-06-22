<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticatePelionApi
{
    public function handle(Request $request, Closure $next)
    {
        $token = (string) config('services.pelion.incoming_token');

        if ($token === '') {
            return response()->json([
                'message' => 'Pelion API token nije konfiguriran.',
            ], 503);
        }

        $providedToken = $request->bearerToken() ?: $request->header('X-API-KEY');

        if (! is_string($providedToken) || $providedToken === '' || ! hash_equals($token, $providedToken)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
