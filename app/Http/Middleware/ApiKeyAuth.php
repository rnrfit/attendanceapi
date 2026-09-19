<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyAuth
{
    /**
     * Reject any request that doesn't carry a matching X-API-Key header.
     * The mobile app must send: X-API-Key: <value of API_KEY in .env>
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response)  $next
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $expected = config('services.api_key');
        $provided = $request->header('X-API-Key');

        if (empty($expected) || !is_string($provided) || !hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing or invalid API key.',
            ], 401);
        }

        return $next($request);
    }
}
