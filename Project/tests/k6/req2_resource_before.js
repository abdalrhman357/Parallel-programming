import http from 'k6/http';
import { check } from 'k6';
import { track, summary, rand } from './helpers.js';

/**
 * المتطلب 2 (قبل): بدون إدارة موارد — لا Rate Limit ولا حد تزامن.
 * 100 مستخدم على /api/checkout. المتوقع: ارتفاع زمن الاستجابة وظهور أخطاء تحت الضغط.
 * تشغيل: k6 run req2_resource_before.js
 */
export const options = {
  scenarios: { load: { executor: 'ramping-vus', startVUs: 0, stages: [
    { duration: '5s', target: 50 }, { duration: '10s', target: 100 },
    { duration: '10s', target: 100 }, { duration: '5s', target: 0 },
  ] } },
};
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/checkout';
export default function () {
  const res = http.post(URL, JSON.stringify({ user_id: rand(1, 10) }),
    { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } });
  track(res);
  check(res, { 'بلا حد - يقبل كل شيء': (r) => r.status !== 429 && r.status !== 503 });
}
export function handleSummary(data) {
  return summary(data, 'REQ2 RESOURCE BEFORE (no protection)');
}
