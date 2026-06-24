import http from 'k6/http';
import { check } from 'k6';
import { track, summary, rand } from './helpers.js';

/**
 * المتطلب 2 (بعد): مع RateLimit (10/دقيقة لكل IP) + Concurrency (5 متزامنة كحد أقصى).
 * نفس الضغط على /api/checkoutWithLock. المتوقع: لا أخطاء 500، رفض منضبط (429/503)، استجابة سريعة للمقبول.
 * تشغيل: k6 run req2_resource_after.js
 */
export const options = {
  scenarios: { load: { executor: 'ramping-vus', startVUs: 0, stages: [
    { duration: '5s', target: 50 }, { duration: '10s', target: 100 },
    { duration: '10s', target: 100 }, { duration: '5s', target: 0 },
  ] } },
  thresholds: { server_errors_500: ['count<1'] },
};
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/checkoutWithLock';
export default function () {
  const res = http.post(URL, JSON.stringify({ user_id: rand(1, 10) }),
    { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } });
  track(res);
  check(res, { 'منضبط (200/400/429/503)': (r) => [200,201,400,429,503].includes(r.status) });
}
export function handleSummary(data) {
  return summary(data, 'REQ2 RESOURCE AFTER (rate limit + concurrency)');
}
