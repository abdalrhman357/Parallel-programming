import http from 'k6/http';
import { check } from 'k6';
import { track, summary, rand } from './helpers.js';

/**
 * المتطلب 7 (بعد): قفل موزّع عبر Redis (Cache::lock) خارج قاعدة البيانات + ترتيب أقفال ثابت.
 * /api/checkoutWithLock مع قفل لكل منتج بترتيب تصاعدي يمنع الـ deadlock.
 * المتوقع: لا تحديثات ضائعة، لا أخطاء 500، تسلسل صحيح للعمليات الحرجة.
 * تشغيل: k6 run req7_lock_after.js
 */
export const options = { vus: 50, iterations: 100, thresholds: { server_errors_500: ['count<1'] } };
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/checkoutWithLock';
export default function () {
  const res = http.post(URL, JSON.stringify({ user_id: rand(1, 10) }),
    { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } });
  track(res);
  check(res, { 'لا deadlock/500': (r) => r.status !== 500 });
}
export function handleSummary(data) {
  return summary(data, 'REQ7 LOCK AFTER (Redis distributed lock)',
    ['✅ لا أخطاء 500 = لا deadlock؛ القفل الموزّع يسلسل الوصول']);
}
