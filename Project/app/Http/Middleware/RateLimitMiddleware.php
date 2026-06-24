<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'checkout:' . $request->ip();

        // المتطلب الثاني: القيم تُقرأ من .env عبر config/checkout.php
        $maxAttempts  = (int) config('checkout.throttle_max', 10);
        $decaySeconds = (int) config('checkout.throttle_decay', 1);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {

            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message'     => 'Too many requests. Slow down.',
                'retry_after' => $seconds . ' seconds',
                'limit'       => $maxAttempts,
                'remaining'   => 0,
            ], 429);
        }

        RateLimiter::hit($key, $decaySeconds);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit',     $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', RateLimiter::remaining($key, $maxAttempts));
        $response->headers->set('X-RateLimit-Reset',     RateLimiter::availableIn($key));

        return $response;
    }
}