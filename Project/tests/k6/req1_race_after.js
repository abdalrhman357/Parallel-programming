import http from 'k6/http';
import { check } from 'k6';
import { track, summary } from './helpers.js';

/**
 * المتطلب 1 (بعد): القفل الموزّع يمنع الـ Race Condition.
 * نفس الضغط لكن عبر /api/checkoutWithLock.
 * المتوقع: لا يُباع أكثر من المخزون (stock لا ينزل تحت الصفر) = سلامة بيانات محفوظة.
 *
 * تشغيل:  k6 run req1_race_after.js
 */
export const options = { vus: 100, iterations: 100 };

const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/checkoutWithLock';

export default function () {
    const res = http.post(URL, JSON.stringify({ user_id: 1 }), {
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    });
    track(res);
    check(res, { 'no 500': (r) => r.status !== 500 });
}

export function handleSummary(data) {
    return summary(data, 'REQ1 RACE AFTER (distributed lock)', [
        '✅ تحقق يدوياً: SELECT stock_quantity FROM products  → لا تنزل تحت 0',
    ]);
}
