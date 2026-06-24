import http from 'k6/http';
import { check } from 'k6';
import { track, summary } from './helpers.js';

/**
 * المتطلب 6 (قبل): تصفّح المنتجات بدون كاش (?fresh=1) — يضرب قاعدة البيانات في كل طلب.
 * المتوقع: زمن تنفيذ على الخادم أعلى (راقب Avg Server Exec Time من الـ AOP).
 * تشغيل: k6 run req6_cache_before.js
 */
export const options = { vus: 30, duration: '20s' };
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/products?fresh=1';
export default function () {
  const res = http.get(URL, { headers: { 'Accept': 'application/json' } });
  track(res);
  check(res, {
    'status 200': (r) => r.status === 200,
    'تجاوز الكاش': (r) => (r.headers['X-Cache'] || r.headers['x-cache']) === 'BYPASS',
  });
}
export function handleSummary(data) {
  return summary(data, 'REQ6 CACHE BEFORE (DB every time)');
}
