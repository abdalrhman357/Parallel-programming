import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
// 1. استيراد الدوال المركزية
import { trackServerMetrics, generateCustomSummary } from './metrics-helper.js';

export const options = {
  vus: 5,
  duration: '30s',
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1000'],
  },
};

// الابقاء على عداد الـ Load Balancer الخاص بك لمعرفة أي سيرفر استقبل الطلب
const instanceHits = new Counter('app_instance_hits');
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';

export default function () {
  const res = http.get(`${BASE_URL}/api/system/server-info`, {
    headers: { 'Accept': 'application/json' },
  });

  // 2. تتبع مقاييس الـ Middleware تلقائياً للـ RAM والـ CPU ووقت التنفيذ
  trackServerMetrics(res);

  check(res, {
    'status is 200': (r) => r.status === 200,
    'has app_instance': (r) => (r.json('app_instance') || '').length > 0,
  });

  // منطق الـ Load Balancer الخاص بك
  const appInstance = res.json('app_instance') || 'unknown';
  instanceHits.add(1, { app_instance: appInstance });
//   console.log(`app_instance=${appInstance}`);

  sleep(0.5);
}

// 3. طباعة التقرير النهائي الموحد الذي سيعرض أداء السيرفرات بالتفصيل
export function handleSummary(data) {
  return generateCustomSummary(data, "SERVER INFO & LOAD BALANCER SUMMARY");
}
