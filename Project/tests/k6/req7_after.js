import http from 'k6/http';
import { check } from 'k6';
import { track, summary } from './helpers.js';

/**
 * Thundering Herd Solution Test (After Fix)
 * Route: /reports/daily-sales/after
 * Expected: Server protected, fast responses (either via Cache 200 or safe 423 rejection), 0 server errors.
 * Run: k6 run req_thundering_after.js
 */
export const options = {
    scenarios: {
        thundering_herd_scenario: {
            executor: 'per-vu-iterations',
            vus: 10,              // 100 مستخدم وهمي يعملون معاً بنفس اللحظة
            iterations: 100,       // كل مستخدم يرسل 100 طلب متتالي خلف بعضهم
            maxDuration: '10m',
        },
    },
    thresholds: { server_errors_500: ['count<1'] }
};
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/reports/daily-sales/_after';

export default function () {
  const res = http.get(URL, {
    headers: { 'Accept': 'application/json' }
  });

  track(res);

  check(res, {
    'Protected (200 Cache or 423 Locked)': (r) => r.status === 200 || r.status === 423,
    'No 500 server errors': (r) => r.status !== 500
  });
}

export function handleSummary(data) {
  return summary(data, 'THUNDERING HERD - AFTER LOCK (Redis distributed lock)',
    ['✅ Distributed lock successfully serialized execution and protected CPU via cache/423 throttling']);
}
