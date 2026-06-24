import http from 'k6/http';
import { check } from 'k6';
import { track, summary, rand } from './helpers.js';

/**
 * المتطلب 3 (قبل): معالجة متزامنة — الفاتورة والإشعار داخل الطلب (/api/queue-demo/sync).
 * المستخدم ينتظر ~3 ثوانٍ (2s فاتورة + 1s إشعار). المتوقع: زمن استجابة مرتفع جداً.
 * تشغيل: k6 run req3_queue_before.js
 */
export const options = { vus: 10, duration: '25s' };
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/queue-demo/sync';
export default function () {
  const res = http.post(URL, JSON.stringify({ user_id: rand(1, 10) }),
    { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } });
  track(res);
  check(res, { 'no 500': (r) => r.status !== 500 });
}
export function handleSummary(data) {
  return summary(data, 'REQ3 QUEUE BEFORE (synchronous)',
    ['ℹ️ المستخدم ينتظر ~3000ms لكل طلب (الفاتورة + الإشعار داخل الطلب)']);
}