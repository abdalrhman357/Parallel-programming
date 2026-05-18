import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';

// ─── Metrics ──────────────────────────────────────────────────────────────────
const responseTime  = new Trend('response_time');
const successRate   = new Rate('success_rate');
const totalRequests = new Counter('total_requests');
const fastResponse  = new Rate('fast_response_under_1s');

export const options = {
    scenarios: {
        async_processing: {
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
        response_time:          ['p(95)<3000'],
        fast_response_under_1s: ['rate>0.5'],
    },
};

// دالة لتوليد بيانات عشوائية للاختبار
function getRandomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

export default function () {
    // محاكاة مستخدمين مختلفين (افترض أن لديك 10 مستخدمين جاهزين في قاعدة البيانات)
    const payload = JSON.stringify({
        user_id: getRandomInt(1, 10) 
    });

    const response = http.post(
        'http://127.0.0.1:8000/api/checkoutWithLock',
        payload,
        { headers: { 'Content-Type': 'application/json' } }
    );

    totalRequests.add(1);
    responseTime.add(response.timings.duration);
    
    // اعتبار الطلب ناجحاً إما إذا تم الشراء (200) أو تم رفضه منطقياً بشكل صحيح (400)
    // لأن الفشل بسبب نفاد الكمية يعتبر استجابة صحيحة من السيرفر وليس عطلاً
    const isSuccessful = response.status === 200 || response.status === 400;
    successRate.add(isSuccessful ? 1 : 0);
    
    fastResponse.add(response.timings.duration < 1000 ? 1 : 0);

    let body = {};
    try { body = JSON.parse(response.body); } catch (e) {}

    check(response, {
        '✅ استجاب النظام فوراً': (r) => r.status !== 0,
        '✅ لا أخطاء 500 (Deadlock/Server Error)': (r) => r.status !== 500,
        '✅ لا أخطاء 429 (Rate Limit)': (r) => r.status !== 429,
    });

    if (Math.random() < 0.3) {
        const tasks = body.background_tasks || {};
        console.log(
            `[AFTER-QUEUE] Status: ${response.status} | ` +
            `Time: ${response.timings.duration.toFixed(0)}ms | ` +
            `Message: ${body.message || 'N/A'}`
        );
    }

    // وقت انتظار عشوائي بين 0.5 و 1.5 ثانية لمحاكاة البشر
    sleep(Math.random() * 1 + 0.5);
}

export function handleSummary(data) {
    const m = data.metrics;
    const totalReqs  = m.http_reqs?.values?.count                          || 0;
    const avgTime    = m.response_time?.values?.avg?.toFixed(0)            || 'N/A';
    const p95Time    = m.response_time?.values?.['p(95)']?.toFixed(0)      || 'N/A';
    const minTime    = m.response_time?.values?.min?.toFixed(0)            || 'N/A';
    const maxTime    = m.response_time?.values?.max?.toFixed(0)            || 'N/A';
    const success    = ((m.success_rate?.values?.rate     || 0) * 100).toFixed(1);
    const fastRate   = ((m.fast_response_under_1s?.values?.rate || 0) * 100).toFixed(1);

    const report = `
╔══════════════════════════════════════════════════════════════════╗
║    ✅ بعد Queue - المعالجة غير المتزامنة (Asynchronous)          ║
╠══════════════════════════════════════════════════════════════════╣
║  📊 إحصائيات الاستجابة                                           ║
║  ├─ إجمالي الطلبات   : ${String(totalReqs).padEnd(36)}║
║  ├─ أقل زمن استجابة  : ${String(minTime + ' ms').padEnd(36)}║
║  ├─ متوسط الاستجابة  : ${String(avgTime + ' ms').padEnd(36)}║
║  ├─ P95 الاستجابة    : ${String(p95Time + ' ms').padEnd(36)}║
║  └─ أقصى استجابة     : ${String(maxTime + ' ms').padEnd(36)}║
╠══════════════════════════════════════════════════════════════════╣
║  📈 جودة الأداء                                                  ║
║  ├─ ✅ استجابة سليمة   : ${String(success + '% (200 أو 400)').padEnd(30)}║
║  └─ ⚡ أسرع من 1000ms  : ${String(fastRate + '%').padEnd(36)}║
╚══════════════════════════════════════════════════════════════════╝
`;
    console.log(report);
    return { stdout: report };
}