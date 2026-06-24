import http from 'k6/http';
import { check } from 'k6';
import { track, summary } from './helpers.js';

/**
 * المتطلب 4 (قبل): تقرير المبيعات بدون تقطيع (يحمّل كل الطلبات في الذاكرة دفعة واحدة).
 * المتوقع: استهلاك ذاكرة أعلى (راقب Avg RAM) على بيانات ضخمة.
 * تهيئة بيانات: php artisan benchmark:seed --reset
 * تشغيل: k6 run req4_batch_before.js
 */
export const options = { vus: 20, duration: '30s', thresholds: { http_req_failed: ['rate<0.01'] } };
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/reports/daily-sales/before';
export default function () {
  const res = http.get(URL, { headers: { 'Accept': 'application/json' } });
  track(res);
  check(res, { 'status 200': (r) => r.status === 200 });
}
export function handleSummary(data) {
  return summary(data, 'REQ4 BATCH BEFORE (no chunking)');
}
