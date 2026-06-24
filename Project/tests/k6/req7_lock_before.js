import http from 'k6/http';
import { check } from 'k6';
import { track, summary, rand } from './helpers.js';

/**
 * المتطلب 7 (قبل): التحكم بالتزامن بلا قفل موزّع — /api/checkout.
 * 50 مستخدماً يقفلون نفس المنتجات منطقياً دون تنسيق.
 * المتوقع: تحديثات ضائعة (lost updates) على المخزون.
 * تشغيل: k6 run req7_lock_before.js
 */
export const options = { vus: 50, iterations: 100 };
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/checkout';
export default function () {
  const res = http.post(URL, JSON.stringify({ user_id: rand(1, 10) }),
    { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } });
  track(res);
  check(res, { 'no 500': (r) => r.status !== 500 });
}
export function handleSummary(data) {
  return summary(data, 'REQ7 LOCK BEFORE (no distributed lock)',
    ['⚠️ احتمال lost updates على المخزون']);
}
