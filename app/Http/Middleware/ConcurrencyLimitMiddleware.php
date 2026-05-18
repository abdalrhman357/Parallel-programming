<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * الطبقة الثانية من التحكم في الموارد:
 * Semaphore - تحديد عدد العمليات الداخلة للـ checkout في نفس اللحظة.
 *
 * الفرق بين هذا وبين RateLimiter:
 * - RateLimiter  → يتحكم في عدد الطلبات خلال فترة زمنية (مثل 10 طلب/دقيقة)
 * - Semaphore    → يتحكم في عدد الطلبات المعالَجة في نفس اللحظة (مثل 5 طلبات متزامنة فقط)
 *
 * مثال: لو وصل 100 طلب في نفس الثانية:
 * - السماح لـ 5 فقط بالدخول والمعالجة
 * - الباقون يحصلون على 503 فوراً بدلاً من تحميل السيرفر
 */
class ConcurrencyLimitMiddleware
{
    // الحد الأقصى للعمليات المتوازية في نفس اللحظة
    private const MAX_CONCURRENT = 5;

    // مفتاح العداد في الـ Cache
    private const COUNTER_KEY = 'concurrent_checkouts';

    // مدة الاحتفاظ بالعداد (ثوانٍ) - حماية من التسرب إذا مات الـ worker
    private const TTL = 30;

    public function handle(Request $request, Closure $next): Response
    {
        // قفل على العداد نفسه لضمان أن عملية القراءة والزيادة atomic
        // هذا يمنع race condition على العداد ذاته
        $counterLock = Cache::lock('concurrency_counter_lock', 3);

        if (! $counterLock->get()) {
            // لم نتمكن من الحصول على قفل العداد → النظام مشغول جداً
            return response()->json([
                'message' => 'System is busy, please try again.',
                'reason'  => 'Counter lock unavailable',
            ], 503);
        }

        try {
            // قراءة العداد الحالي
            $current = Cache::get(self::COUNTER_KEY, 0);

            if ($current >= self::MAX_CONCURRENT) {
                // وصلنا للحد الأقصى → ارفض الطلب فوراً
                $counterLock->release();

                return response()->json([
                    'message'    => 'Server is at full capacity. Please try again shortly.',
                    'concurrent' => $current,
                    'max'        => self::MAX_CONCURRENT,
                ], 503); // 503 = Service Unavailable
            }

            // زيادة العداد (دخل طلب جديد)
            Cache::put(self::COUNTER_KEY, $current + 1, self::TTL);

        } finally {
            // تحرير قفل العداد دائماً
            $counterLock->release();
        }

        // تنفيذ الطلب الفعلي
        try {
            $response = $next($request);
        } finally {
            // تخفيض العداد بعد انتهاء المعالجة (سواء نجح أو فشل)
            // نستخدم قفل مرة أخرى لضمان atomic decrement
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

        // إضافة headers توضح حالة التزامن الحالية
        $response->headers->set('X-Concurrent-Requests', Cache::get(self::COUNTER_KEY, 0));
        $response->headers->set('X-Max-Concurrent',      self::MAX_CONCURRENT);

        return $response;
    }
}