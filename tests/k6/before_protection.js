/**
 * ============================================================
 * اختبار قبل الحماية - بدون Rate Limit أو Concurrency Limit
 * ============================================================
 * k6 run before_protection.js
 * ============================================================
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Counter, Trend } from 'k6/metrics';

// ─── Metrics ──────────────────────────────────────────────────────────────────
const successfulRequests  = new Rate('successful_requests');
const serverErrorRequests = new Rate('server_error_requests');
const successResponseTime = new Trend('success_response_time');
const totalProcessed      = new Counter('total_processed');

// ─── الإعداد: 100 مستخدم متزامن ──────────────────────────────────────────────
export const options = {
    scenarios: {
        without_protection: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '5s',  target: 50  },
                { duration: '10s', target: 100 },
                { duration: '10s', target: 100 },
                { duration: '5s',  target: 0   },
            ],
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<5000'],
    },
};

// ─── الدالة الرئيسية ──────────────────────────────────────────────────────────
export default function () {

    const response = http.post(
        'http://127.0.0.1:8000/api/checkout',
        JSON.stringify({}),
        { headers: { 'Content-Type': 'application/json' } }
    );

    totalProcessed.add(1);

    const isSuccess     = response.status === 200 || response.status === 201;
    const isServerError = response.status === 500;

    successfulRequests.add(isSuccess ? 1 : 0);
    serverErrorRequests.add(isServerError ? 1 : 0);

    if (isSuccess) {
        successResponseTime.add(response.timings.duration);
    }

    check(response, {
        '❌ لا يوجد تحكم - النظام يستقبل كل شيء': (r) => r.status !== 429 && r.status !== 503,
        'النظام استجاب (لم يتوقف كلياً)'         : (r) => r.status !== 0,
        'خطأ 500 (انهيار جزئي)'                  : (r) => r.status === 500,
    });

    if (Math.random() < 0.1) {
        console.log(
            `[BEFORE] Status: ${response.status} | ` +
            `Time: ${response.timings.duration.toFixed(0)}ms`
        );
    }

    sleep(0.1);
}

// ─── التقرير النهائي ──────────────────────────────────────────────────────────
export function handleSummary(data) {

    const m = data.metrics;

    const totalReqs       = m.http_reqs?.values?.count || 0;
    const avgDuration     = m.http_req_duration?.values?.avg?.toFixed(2) || 'N/A';
    const p95Duration     = m.http_req_duration?.values?.['p(95)']?.toFixed(2) || 'N/A';
    const maxDuration     = m.http_req_duration?.values?.max?.toFixed(2) || 'N/A';
    const successRate     = ((m.successful_requests?.values?.rate || 0) * 100).toFixed(1);
    const serverErrorRate = ((m.server_error_requests?.values?.rate || 0) * 100).toFixed(1);
    const successAvgTime  = m.success_response_time?.values?.avg?.toFixed(2) || 'N/A';

    const report = `
╔══════════════════════════════════════════════════════════════════╗
║      ❌ النتيجة قبل الحماية - بدون Resource Management          ║
╠══════════════════════════════════════════════════════════════════╣
║  الـ Endpoint : /api/checkout (بدون أي Middleware)              ║
║  عدد المستخدمين المتزامنين: 100                                 ║
╠══════════════════════════════════════════════════════════════════╣
║  📊 إحصائيات عامة                                               ║
║  ├─ إجمالي الطلبات        : ${String(totalReqs).padEnd(33)}║
║  ├─ متوسط زمن الاستجابة   : ${String(avgDuration + ' ms').padEnd(33)}║
║  ├─ P95 زمن الاستجابة     : ${String(p95Duration + ' ms').padEnd(33)}║
║  └─ أقصى زمن استجابة      : ${String(maxDuration + ' ms').padEnd(33)}║
╠══════════════════════════════════════════════════════════════════╣
║  📈 توزيع الاستجابات                                            ║
║  ├─ ✅ ناجح (200/201)      : ${String(successRate + '%').padEnd(33)}║
║  └─ 💥 Server Error (500)  : ${String(serverErrorRate + '%').padEnd(33)}║
╠══════════════════════════════════════════════════════════════════╣
║  🔴 المشاكل الملاحظة                                            ║
║  • لا يوجد حد للطلبات المتزامنة                                 ║
║  • لا يوجد Rate Limiting                                        ║
║  • أخطاء 500 تعني انهيار جزئي تحت الضغط                        ║
║  • زمن الاستجابة يرتفع بشكل غير منضبط                          ║
║  • النظام يستهلك موارد غير محدودة                               ║
╚══════════════════════════════════════════════════════════════════╝
`;

    console.log(report);

    return {
        stdout: report,
        'report_BEFORE.txt': report,
    };
}