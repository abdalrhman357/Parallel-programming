import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';
import { track, summary } from './helpers.js';

/**
 * المتطلب 5 (بعد): موازِن أحمال (Nginx) على المنفذ 8080 يوزّع على 3 خوادم.
 * المتوقع: توزيع الطلبات تقريباً بالتساوي بين app_instance المختلفة.
 * شغّل عدة instances بقيم APP_INSTANCE مختلفة خلف Nginx.
 * تشغيل: BASE_URL=http://localhost:8080 k6 run req5_load_after.js
 */
export const options = {
  vus: 15, duration: '30s',
  thresholds: { http_req_failed: ['rate<0.01'], http_req_duration: ['p(95)<1000'] },
};
const instanceHits = new Counter('app_instance_hits');
const URL = (__ENV.BASE_URL || 'http://localhost:8080') + '/api/system/server-info';
export default function () {
  const res = http.get(URL, { headers: { 'Accept': 'application/json' } });
  track(res);
  const inst = res.json('app_instance') || 'unknown';
  instanceHits.add(1, { app_instance: inst });
  check(res, {
    'status 200': (r) => r.status === 200,
    'يوجد app_instance': (r) => (r.json('app_instance') || '').length > 0,
  });
}
export function handleSummary(data) {
  return summary(data, 'REQ5 LOAD AFTER (load balanced)',
    ['ℹ️ راجع app_instance_hits لكل instance لرؤية التوزيع']);
}
