<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * الطبقة الأولى من التحكم في الموارد:
 * تحديد عدد الطلبات القادمة من كل مستخدم (أو IP) في فترة زمنية معينة.
 * الهدف: منع أي مستخدم من إغراق النظام بطلبات متكررة.
 */
class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // مفتاح فريد لكل مستخدم بناءً على IP أو id
        // نستخدم IP لأن الـ routes غير محمية بـ auth حالياً
        $key = 'checkout:' . $request->ip();

        // السماح بـ 10 طلبات كحد أقصى كل 60 ثانية
        $maxAttempts = 10;
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {

            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message'     => 'Too many requests. Slow down.',
                'retry_after' => $seconds . ' seconds',

                // معلومات إضافية تُثبت أن النظام يتحكم في الموارد
                'limit'       => $maxAttempts,
                'remaining'   => 0,
            ], 429); // 429 = Too Many Requests
        }

        // تسجيل هذا الطلب ضمن العداد
        RateLimiter::hit($key, $decaySeconds);

        $response = $next($request);

        // إضافة headers توضح الحالة الحالية للـ Rate Limit
        // مفيدة جداً في تقرير الـ Stress Testing
        $response->headers->set('X-RateLimit-Limit',     $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', RateLimiter::remaining($key, $maxAttempts));
        $response->headers->set('X-RateLimit-Reset',     RateLimiter::availableIn($key));

        return $response;
    }
}