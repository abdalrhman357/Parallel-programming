import http from 'k6/http';
import { check, sleep } from 'k6';
// 1. استيراد الدوال المركزية
import { trackServerMetrics, generateCustomSummary } from './metrics-helper.js';

export const options = {
  vus: 20,
  duration: '30s',
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1500'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000';

export default function () {
  const res = http.get(`${BASE_URL}/api/reports/daily-sales/before`, {
    headers: { 'Accept': 'application/json' },
  });

  // 2. تتبع مقاييس الـ Middleware تلقائياً
  trackServerMetrics(res);

  // الفحوصات الخاصة بك
  check(res, {
    'status is 200': (r) => r.status === 200,
    'has processing mode': (r) => (r.json('processing_mode') || '').length > 0,
  });

  sleep(1);
}

// 3. طباعة التقرير النهائي الموحد
export function handleSummary(data) {
  return generateCustomSummary(data, "DAILY SALES REPORT (BEFORE CHUNK) SUMMARY");
}
