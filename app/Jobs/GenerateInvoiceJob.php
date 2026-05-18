<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ============================================================
 * GenerateInvoiceJob - مهمة إصدار الفاتورة
 * ============================================================
 */
class GenerateInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * عدد المحاولات عند الفشل
     */
    public int $tries = 3;

    /**
     * وقت الانتظار بين المحاولات
     */
    public int $backoff = 5;

    /**
     * أقصى وقت للتنفيذ
     */
    public int $timeout = 30;

    /**
     * بيانات الطلب
     */
    public function __construct(
        public int $orderId,
        public int $userId,
        public float $totalPrice,
        public string $orderStatus,
    ) {}

    /**
     * تنفيذ المهمة
     */
    public function handle(): void
    {
        // تسجيل بداية المعالجة
        Log::channel('daily')->info(
            'GenerateInvoiceJob: بدء إصدار الفاتورة',
            [
                'order_id'   => $this->orderId,
                'user_id'    => $this->userId,
                'total'      => $this->totalPrice,
                'queue_time' => now()->toDateTimeString(),
            ]
        );

        // بناء بيانات الفاتورة
        $invoiceData = $this->buildInvoice();

        // محاكاة معالجة ثقيلة
        sleep(2);

        // تسجيل النجاح
        Log::channel('daily')->info(
            'GenerateInvoiceJob: تم إصدار الفاتورة بنجاح',
            [
                'order_id'       => $this->orderId,
                'invoice_number' => $invoiceData['invoice_number'],
                'issued_at'      => $invoiceData['issued_at'],
            ]
        );
    }

    /**
     * إنشاء بيانات الفاتورة
     */
    private function buildInvoice(): array
    {
        return [

            'invoice_number' => 'INV-' .
                str_pad($this->orderId, 6, '0', STR_PAD_LEFT),

            'order_id'    => $this->orderId,

            'user_id'     => $this->userId,

            'total_price' => $this->totalPrice,

            'status'      => $this->orderStatus,

            'issued_at'   => now()->toDateTimeString(),

            'due_date'    => now()
                ->addDays(30)
                ->toDateTimeString(),
        ];
    }

    /**
     * عند الفشل النهائي
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('daily')->error(
            'GenerateInvoiceJob: فشل إصدار الفاتورة نهائياً',
            [
                'order_id' => $this->orderId,
                'error'    => $exception->getMessage(),
            ]
        );
    }
}