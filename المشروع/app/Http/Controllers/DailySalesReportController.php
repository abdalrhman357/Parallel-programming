<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDailySalesReportJob;
use App\Services\Reports\DailySalesReportService;
use Illuminate\Http\JsonResponse;

class DailySalesReportController extends Controller
{
    public function before(DailySalesReportService $service): JsonResponse
    {
        return response()->json($service->generateWithoutBatch());
    }

    public function after(DailySalesReportService $service): JsonResponse
    {
        return response()->json($service->generateWithBatch(1000));
    }

    public function dispatch(): JsonResponse
    {
        ProcessDailySalesReportJob::dispatch(1000);

        return response()->json([
            'message' => 'Daily sales report job dispatched',
            'processing_mode' => 'job_chunked',
        ], 202);
    }
}