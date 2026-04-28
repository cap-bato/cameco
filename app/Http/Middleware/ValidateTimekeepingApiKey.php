<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateTimekeepingApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('services.timekeeping_api_key');

        if (empty($configuredKey)) {
            return response()->json(['error' => 'API key not configured'], 500);
        }

        $provided = $request->bearerToken();

        if (empty($provided) || !hash_equals($configuredKey, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
