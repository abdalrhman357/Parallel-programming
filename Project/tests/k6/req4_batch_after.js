import http from 'k6/http';
import { check } from 'k6';
import { track, summary } from './helpers.js';

/**
 * المتطلب 4 (بعد): تقرير المبيعات بالتقطيع (chunkById 1000).
 * المتوقع: استهلاك ذاكرة أقل وثابت بغض النظر عن حجم البيانات.
 * تشغيل: k6 run req4_batch_after.js
 */
export const options = { vus: 20, duration: '30s', thresholds: { http_req_failed: ['rate<0.01'] } };
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/reports/daily-sales/after';
export default function () {
  const res = http.get(URL, { headers: { 'Accept': 'application/json' } });
  track(res);
  let body = {}; try { body = JSON.parse(res.body); } catch (e) {}
  check(res, {
    'status 200': (r) => r.status === 200,
    'يوجد chunk_size': () => Number(body.chunk_size) > 0,
  });
}
export function handleSummary(data) {
  return summary(data, 'REQ4 BATCH AFTER (chunked)');
}
