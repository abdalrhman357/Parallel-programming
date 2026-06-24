import http from 'k6/http';
import { check } from 'k6';
import { track, summary, rand } from './helpers.js';

/**
 * المتطلب 3 (بعد): الفاتورة والإشعار تُدفع للطابور (/api/queue-demo/async).
 * المستخدم لا ينتظرها. المتوقع: زمن استجابة منخفض جداً + background_tasks: queued.
 * شغّل العامل في نافذة أخرى: php artisan queue:work
 * تشغيل: k6 run req3_queue_after.js
 */
export const options = { vus: 10, duration: '25s' };
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/queue-demo/async';
export default function () {
  const res = http.post(URL, JSON.stringify({ user_id: rand(1, 10) }),
    { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } });
  track(res);
  let body = {}; try { body = JSON.parse(res.body); } catch (e) {}
  check(res, {
    'no 500': (r) => r.status !== 500,
    'مهام بالخلفية': () => body.background_tasks && body.background_tasks.invoice === 'queued',
  });
}
export function handleSummary(data) {
  return summary(data, 'REQ3 QUEUE AFTER (async jobs)',
    ['ℹ️ المستخدم يرجع فوراً؛ المهام (3s) تُنفَّذ لاحقاً في نافذة queue:work']);
}