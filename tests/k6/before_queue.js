import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';

// ─── Metrics ──────────────────────────────────────────────────────────────────
const responseTime    = new Trend('response_time');
const successRate     = new Rate('success_rate');
const totalRequests   = new Counter('total_requests');

// ─── الإعداد: 10 مستخدمين متزامنين (نفس إعدادات بعد الـ Queue تماماً) ──────────
export const options = {
    scenarios: {
        sync_processing: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '5s',  target: 10 },
                { duration: '15s', target: 10 },
                { duration: '5s',  target: 0  },
            ],
        },
    },
    thresholds: {
        response_time: ['p(95)<60000'], // نتوقع بطئاً شديداً هنا
    },
};

// دالة توليد معرفات مستخدمين عشوائية لضمان ضرب سلال مختلفة
function getRandomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

// ─── الدالة الرئيسية ──────────────────────────────────────────────────────────
export default function () {

    // محاكاة طلب مستخدمين مختلفين لضمان عدم قفل نفس السلة
    const payload = JSON.stringify({
        user_id: getRandomInt(1, 10) 
    });

    const response = http.post(
        'http://127.0.0.1:8000/api/checkout',
        payload,
        { headers: { 'Content-Type': 'application/json' } }
    );

    totalRequests.add(1);
    responseTime.add(response.timings.duration);
    
    // الطلب ناجح إذا استجاب السيرفر بشكل طبيعي (سواء تم الشراء 200 أو رُفض منطقياً 400)
    const isSuccessful = response.status === 200 || response.status === 400;
    successRate.add(isSuccessful ? 1 : 0);

    check(response, {
        '✅ استجاب النظام (لم يسقط)': (r) => r.status !== 0,
        '✅ لا أخطاء 500 (Server Error)': (r) => r.status !== 500,
        '⚠️ المستخدم انتظر طويلاً عند النجاح': (r) => {
            // نتحقق من الانتظار فقط في حال كان الطلب ناجحاً وتمت المعالجة كاملة
            return r.status === 200 ? r.timings.duration > 3000 : true;
        },
    });

    if (Math.random() < 0.3) {
        console.log(
            `[BEFORE-QUEUE] Status: ${response.status} | ` +
            `Time: ${response.timings.duration.toFixed(0)}ms | ` +
            `⚠️ السيرفر محجوز بالكامل مع هذا الطلب`
        );
    }

    sleep(Math.random() * 1 + 0.5); // وقت انتظار عشوائي لمحاكاة السلوك البشري
}

// ─── التقرير النهائي ──────────────────────────────────────────────────────────
export function handleSummary(data) {

    const m = data.metrics;

    const totalReqs   = m.http_reqs?.values?.count                          || 0;
    const avgTime     = m.response_time?.values?.avg?.toFixed(0)           || 'N/A';
    const p95Time     = m.response_time?.values?.['p(95)']?.toFixed(0)     || 'N/A';
    const minTime     = m.response_time?.values?.min?.toFixed(0)            || 'N/A';
    const maxTime     = m.response_time?.values?.max?.toFixed(0)            || 'N/A';
    const success     = ((m.success_rate?.values?.rate || 0) * 100).toFixed(1);

    const report = `
╔══════════════════════════════════════════════════════════════════╗
║    ❌ قبل Queue - المعالجة المتزامنة (Synchronous)               ║
╠══════════════════════════════════════════════════════════════════╣
║  الـ Endpoint : /api/checkout                                    ║
║  طريقة المعالجة: الفاتورة والإشعار داخل الطلب مباشرة             ║
╠══════════════════════════════════════════════════════════════════╣
║  📊 إحصائيات الاستجابة                                           ║
║  ├─ إجمالي الطلبات   : ${String(totalReqs).padEnd(36)}║
║  ├─ أقل زمن استجابة  : ${String(minTime + ' ms').padEnd(36)}║
║  ├─ متوسط الاستجابة  : ${String(avgTime + ' ms').padEnd(36)}║
║  ├─ P95 الاستجابة    : ${String(p95Time + ' ms').padEnd(36)}║
║  └─ أقصى استجابة     : ${String(maxTime + ' ms').padEnd(36)}║
╠══════════════════════════════════════════════════════════════════╣
║  📈 جودة الأداء                                                  ║
║  └─ ✅ استجابة سليمة   : ${String(success + '% (200 أو 400)').padEnd(30)}║
╠══════════════════════════════════════════════════════════════════╣
║  🔴 المشكلة الحالية (قبل الـ Queue)                             ║
║  • كل مستخدم ينجح في الشراء يحتجز السيرفر لـ 3+ ثوانٍ كاملة.    ║
║  • المتوسط العام سيتأثر بشكل حاد بناءً على عدد الطلبات الناجحة.  ║
╚══════════════════════════════════════════════════════════════════╝
`;

    console.log(report);

    return {
        stdout: report,
        'report_BEFORE_QUEUE.txt': report,
    };
}