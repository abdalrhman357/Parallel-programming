<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Reports\DailySalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ThunderingHerdReportController extends Controller
{
    /**
     * الحالة (قبل الحل): قبل استخدام القفل الموزع والـ Batching
     * هنا إذا دخل 100 مستخدم بنفس اللحظة، سيقوم السيرفر بتوليد التقرير 100 مرة متزامنة
     * مما يسبب ضغطاً هائلاً (Race Condition على موارد النظام وانهيار السيرفر).
     */
    public function before(DailySalesReportService $service): JsonResponse
    {
        return response()->json($service->generateWithoutBatch());
    }
    public function after(DailySalesReportService $service): JsonResponse
    {

        $lockKey = 'lock:thundering-herd-report:' . date('Y-m-d');
        $lock = Cache::lock($lockKey, 300);
        if ($lock->get()) {
            try {
                $cacheKey = 'cached_daily_sales_report:' . date('Y-m-d');

                if (Cache::has($cacheKey)) {
                    return response()->json(Cache::get($cacheKey));
                }
                $reportData = $service->generateWithBatch(1000);


                Cache::put($cacheKey, $reportData, 3600);

                return response()->json($reportData);

            } finally {

                $lock->release();
            }
        }
        return response()->json([
            'status' => 'error',
            'message' => 'السيرفر مشغول حالياً بتجهيز هذا التقرير لمستخدم آخر. يرجى التحديث بعد قليل.'
        ], 423);
    }
}
