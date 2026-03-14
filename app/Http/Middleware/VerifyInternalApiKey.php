<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('x-internal-key');

        if (! $key || $key !== config('internal.api_key')) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid internal API key.');
        }

        return $next($request);
    }
}
