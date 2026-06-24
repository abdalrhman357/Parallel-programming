# مجموعة اختبارات الأداء (قبل / بعد) — k6

تغطي هذه المجموعة المتطلبات العشرة، كل متطلب بملف «قبل» وملف «بعد» (عدا 10 = استدعاء واحد).

## التهيئة قبل التشغيل

```bash
# 1) إعدادات .env المطلوبة
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# 2) تهيئة قاعدة البيانات
php artisan migrate:fresh
php artisan db:seed --class=SimulationSeeder      # لاختبارات 100 مستخدم (req9, ومعظم الباقي)
php artisan benchmark:seed --reset                 # بيانات ضخمة لاختبار الدفعات (req4)

# 3) تشغيل الخادم + عامل الطابور (لـ req3)
php artisan serve            # طرفية 1
php artisan queue:work       # طرفية 2
```

> لاختبار Race Condition النقي (req1) استخدم بدلاً من SimulationSeeder:
> `php artisan db:seed --class=RaceConditionSeeder` (منتج واحد مخزون=10، مستخدم id=1).

## التشغيل

```bash
k6 run req1_race_before.js     # ثم
k6 run req1_race_after.js
# ... وهكذا لكل متطلب
k6 run req5_load_after.js  # يتطلب الموازِن: BASE_URL=http://localhost:8080 k6 run req5_load_after.js
```

أو دفعة واحدة:

```bash
bash run_all.sh
```

## ما الذي يُثبته كل اختبار

| المتطلب | قبل | بعد | المقياس الحاسم |
|--------|-----|-----|----------------|
| 1 Race Condition | oversell (مخزون سالب) | لا oversell | stock_quantity |
| 2 إدارة الموارد | أخطاء/بطء منفلت | لا 500، رفض منضبط | server_errors_500 |
| 3 الطوابير | زمن استجابة عالٍ | زمن أقل + queued | http_req_duration |
| 4 الدفعات | RAM عالية | RAM ثابتة منخفضة | server_memory_mb |
| 5 توزيع الحمل | instance واحد | توزيع على 3 | app_instance_hits |
| 6 الكاش | server time عالٍ (DB) | server time منخفض (HIT) | server_time_ms + X-Cache |
| 7 القفل الموزّع | lost updates | تسلسل صحيح، لا 500 | server_errors_500 |
| 8 ACID | كتابات جزئية | ذرّية كاملة | تحقق ثوابت DB |
| 9 ضغط 100 مستخدم | عدم استقرار | صمود كامل | logical_ok_rate |
| 10 القياس | — | تقرير قبل/بعد الكاش | improvement_percent |

كل ملف يطبع تقريراً ويحفظ `report_*.txt`. المقاييس المنتهية بـ server_* تأتي من الـ AOP (PerformanceMonitor).
