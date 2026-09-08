<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-KEY');
        $expectedApiKey = config('app.api_key');

        if (!$apiKey || !$expectedApiKey || $apiKey !== $expectedApiKey) {
            return response()->json([
                'message' => 'Unauthorized: Invalid or missing API Key'
            ], 401);
        }

        return $next($request);
    }
}
