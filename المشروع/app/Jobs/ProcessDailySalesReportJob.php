<?php

namespace App\Jobs;

use App\Models\DailySalesReport;
use App\Services\Reports\DailySalesReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDailySalesReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $chunkSize = 1000)
    {
    }

    public function handle(DailySalesReportService $service): void
    {
        $report = $service->generateWithBatch($this->chunkSize);

        DailySalesReport::create([
            'report_date' => today(),
            'total_orders' => $report['total_processed_orders'],
            'total_sales' => $report['total_sales'],
            'total_items' => $report['total_items'],
            'processing_mode' => 'job_chunked',
            'execution_time_ms' => $report['execution_time_ms'],
            'memory_peak_mb' => $report['memory_peak_mb'],
        ]);
    }
}
