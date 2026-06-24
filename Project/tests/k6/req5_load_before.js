import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';
import { track, summary } from './helpers.js';

/**
 * المتطلب 5 (قبل): خادم واحد فقط (بلا موازِن أحمال) على المنفذ 8000.
 * المتوقع: كل الطلبات تصيب نفس الـ instance = نقطة فشل وحيدة.
 * تشغيل: k6 run req5_load_before.js
 */
export const options = { vus: 10, duration: '20s' };
const instanceHits = new Counter('app_instance_hits');
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8001') + '/api/system/server-info';
export default function () {
  const res = http.get(URL, { headers: { 'Accept': 'application/json' } });
  track(res);
  const inst = res.json('app_instance') || 'single-node';
  instanceHits.add(1, { app_instance: inst });
  check(res, { 'status 200': (r) => r.status === 200 });
}
export function handleSummary(data) {
  return summary(data, 'REQ5 LOAD BEFORE (single node)',
    ['ℹ️ كل الإصابات على instance واحد = لا توزيع']);
}
