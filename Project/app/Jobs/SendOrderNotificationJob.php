<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;


class SendOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public int $tries = 3;


    public int $backoff = 10;


    public int $timeout = 60;


    public function __construct(
        public int $orderId,
        public int $userId,
        public string $userName,
        public float $totalPrice,
    ) {}


    public function handle(): void
    {

        Log::channel('daily')->info(
            'SendOrderNotificationJob: بدء إرسال الإشعار',
            [
                'order_id' => $this->orderId,
                'user_id'  => $this->userId,
                'user'     => $this->userName,
            ]
        );


        $notification = $this->buildNotification();


        sleep(1);


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
