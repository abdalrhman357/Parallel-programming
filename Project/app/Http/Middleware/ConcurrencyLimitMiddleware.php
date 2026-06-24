<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ConcurrencyLimitMiddleware
{
    private const COUNTER_KEY = 'concurrent_checkouts';

    private const TTL = 30;

    public function handle(Request $request, Closure $next): Response
    {
        // المتطلب الثاني: الحد الأقصى للتزامن يُقرأ من .env عبر config/checkout.php
        $maxConcurrent = (int) config('checkout.semaphore_max', 2);

        $counterLock = Cache::lock('concurrency_counter_lock', 3);

        if (! $counterLock->get()) {
            return response()->json([
                'message' => 'System is busy, please try again.',
                'reason'  => 'Counter lock unavailable',
            ], 503);
        }

        try {
            $current = Cache::get(self::COUNTER_KEY, 0);

            if ($current >= $maxConcurrent) {
                $counterLock->release();

                return response()->json([
                    'message'    => 'Server is at full capacity. Please try again shortly.',
                    'concurrent' => $current,
                    'max'        => $maxConcurrent,
                ], 503);
            }

            Cache::put(self::COUNTER_KEY, $current + 1, self::TTL);

        } finally {
            $counterLock->release();
        }

        try {
            $response = $next($request);
        } finally {

            $decrementLock = Cache::lock('concurrency_counter_lock', 3);

            if ($decrementLock->get()) {
                try {
                    $count = Cache::get(self::COUNTER_KEY, 0);
                    Cache::put(self::COUNTER_KEY, max(0, $count - 1), self::TTL);
                } finally {
                    $decrementLock->release();
                }
            }
        }

        $response->headers->set('X-Concurrent-Requests', Cache::get(self::COUNTER_KEY, 0));
        $response->headers->set('X-Max-Concurrent',      $maxConcurrent);

        return $response;
    }
}