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
 * SendOrderNotificationJob - مهمة إرسال الإشعار
 * ============================================================
 */
class SendOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * عدد المحاولات عند الفشل
     */
    public int $tries = 3;

    /**
     * وقت الانتظار بين المحاولات
     */
    public int $backoff = 10;

    /**
     * أقصى وقت للتنفيذ
     */
    public int $timeout = 60;

    /**
     * بيانات الإشعار
     */
    public function __construct(
        public int $orderId,
        public int $userId,
        public string $userName,
        public float $totalPrice,
    ) {}

    /**
     * تنفيذ المهمة
     */
    public function handle(): void
    {
        // تسجيل بداية التنفيذ
        Log::channel('daily')->info(
            'SendOrderNotificationJob: بدء إرسال الإشعار',
            [
                'order_id' => $this->orderId,
                'user_id'  => $this->userId,
                'user'     => $this->userName,
            ]
        );

        // إنشاء بيانات الإشعار
        $notification = $this->buildNotification();

        // محاكاة تأخير الإرسال
        sleep(1);

        // تسجيل نجاح العملية
        Log::channel('daily')->info(
            'SendOrderNotificationJob: تم إرسال الإشعار بنجاح',
            [
                'order_id' => $this->orderId,
                'channel'  => $notification['channel'],
                'message'  => $notification['message'],
                'sent_at'  => now()->toDateTimeString(),
            ]
        );
    }

    /**
     * بناء بيانات الإشعار
     */
    private function buildNotification(): array
    {
        return [

            'channel' => 'email',

            'recipient' => $this->userName,

            'subject' => 'تأكيد طلبك رقم #' . $this->orderId,

            'message' =>
                "مرحباً {$this->userName}، " .
                "تم تأكيد طلبك رقم #{$this->orderId} " .
                "بقيمة {$this->totalPrice}.",

            'sent_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * عند الفشل النهائي
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('daily')->error(
            'SendOrderNotificationJob: فشل إرسال الإشعار نهائياً',
            [
                'order_id' => $this->orderId,
                'user_id'  => $this->userId,
                'error'    => $exception->getMessage(),
            ]
        );
    }
}