<?php

/*
|--------------------------------------------------------------------------
| إعدادات الدفع (الحماية وإدارة الموارد) — المتطلب الثاني
|--------------------------------------------------------------------------
| تُقرأ من ملف .env حتى تتحكم بالقيم دون تعديل الكود.
| ضعه في: config/checkout.php
*/

return [

    // الحد الأقصى لعمليات الدفع المتزامنة (Semaphore) — ConcurrencyLimitMiddleware
    'semaphore_max' => (int) env('CHECKOUT_SEMAPHORE_MAX_CONCURRENT', 2),

    // الحد الأقصى لعدد المحاولات (Rate Limit) — RateLimitMiddleware
    'throttle_max' => (int) env('CHECKOUT_THROTTLING_MAX_ATTEMPTS', 10),

    // مدة النافذة الزمنية بالثواني قبل إعادة العدّ
    'throttle_decay' => (int) env('CHECKOUT_THROTTLING_DECAY_SECONDS', 1),

];