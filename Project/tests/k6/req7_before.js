import http from 'k6/http';
import { check } from 'k6';
import { track, summary } from './helpers.js';

/**
 * Thundering Herd Problem Test (Before Fix)
 * Route: /reports/daily-sales/before
 * Expected: Heavy CPU/RAM usage, slow response times, and potential 500/504 errors due to concurrency.
 * Run: k6 run req_thundering_before.js
 */
export const options = {
  scenarios: {
    thundering_herd_scenario: {
      executor: 'per-vu-iterations',
      vus: 10,              // 100 مستخدم وهمي يعملون معاً بنفس اللحظة
      iterations: 10,       // كل مستخدم يرسل 100 طلب متتالي خلف بعضهم
      maxDuration: '10m',
    },
  },
  thresholds: { server_errors_500: ['count<1'] }
};
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/reports/daily-sales/_before';

export default function () {
  const res = http.get(URL, {
    headers: { 'Accept': 'application/json' }
  });

  track(res);

  check(res, {
    'Request successful (but likely slow)': (r) => r.status === 200
  });
}

export function handleSummary(data) {
  return summary(data, 'THUNDERING HERD - BEFORE LOCK',
    ['❌ Huge concurrent stampede: Server regenerates the heavy report for every single parallel request']);
}
