import http from 'k6/http';
import { check, sleep } from 'k6';
// 1. استيراد الدوال المركزية من ملف المساعدة
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
  const res = http.get(`${BASE_URL}/api/reports/daily-sales/after`, {
    headers: { 'Accept': 'application/json' },
  });

  // 2. تتبع مقاييس الـ Middleware تلقائياً وحساب النجاح والفشل
  trackServerMetrics(res);

  // الفحوصات الخاصة بك كما هي
  check(res, {
    'status is 200': (r) => r.status === 200,
    'has chunk size': (r) => Number(r.json('chunk_size')) > 0,
  });

  sleep(1);
}

// 3. طباعة التقرير النهائي الموحد
export function handleSummary(data) {
  return generateCustomSummary(data, "DAILY SALES REPORT (AFTER CHUNK) SUMMARY");
}
