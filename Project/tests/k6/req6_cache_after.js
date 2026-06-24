import http from 'k6/http';
import { check } from 'k6';
import { track, summary } from './helpers.js';

/**
 * المتطلب 6 (بعد): تصفّح المنتجات مع الكاش (Cache-Aside) — يُقرأ من Redis بعد أول طلب.
 * المتوقع: زمن تنفيذ أقل بوضوح + X-Cache: HIT لمعظم الطلبات.
 * تشغيل: k6 run req6_cache_after.js
 */
export const options = {
  vus: 30, duration: '20s',
  thresholds: { server_time_ms: ['avg<20'] }, // الكاش يجب أن يكون سريعاً
};
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/products';
export default function () {
  const res = http.get(URL, { headers: { 'Accept': 'application/json' } });
  track(res);
  check(res, {
    'status 200': (r) => r.status === 200,
    'إصابة كاش': (r) => ['HIT', 'MISS'].includes(r.headers['X-Cache'] || r.headers['x-cache']),
  });
}
export function handleSummary(data) {
  return summary(data, 'REQ6 CACHE AFTER (Redis cache)');
}
