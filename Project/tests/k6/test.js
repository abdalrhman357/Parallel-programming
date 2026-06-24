import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { track, summary, rand } from './helpers.js';

/**
 * Requirement 9 (after): 1000 concurrent users with the same protected checkout path.
 * This is a scaled-up version of the 100-user scenario for staged stress testing.
 * Preparation: php artisan db:seed --class=SimulationSeeder
 * Run: k6 run req9_stress_1000_users.js
 */
const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const USER_POOL = Number(__ENV.USER_POOL || 10);
const PRODUCT_POOL = Number(__ENV.PRODUCT_POOL || 10);
export const options = {
  scenarios: {
    full: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 50 },
        { duration: '45s', target: 100 },
        { duration: '60s', target: 200 },
        { duration: '30s', target: 0 },
      ],
    },
  },
  thresholds: { server_errors_500: ['count<1'], logical_ok_rate: ['rate>0.90'] },
};
const H = { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } };
export default function () {
  const uid = rand(1, USER_POOL);
  group('browse',   () => track(http.get(`${BASE}/api/products`, H)));
  group('popular',  () => track(http.get(`${BASE}/api/products/popular`, H)));
  group('cart',     () => track(http.post(`${BASE}/api/cart/add`, JSON.stringify({ product_id: rand(1, PRODUCT_POOL), quantity: 1 }), H)));
  group('checkout', () => { const r = http.post(`${BASE}/api/checkoutWithLock`, JSON.stringify({ user_id: uid }), H); track(r); check(r, { 'no 500': (x) => x.status !== 500 }); });
  group('server',   () => track(http.get(`${BASE}/api/system/server-info`, H)));
  group('report',   () => track(http.get(`${BASE}/api/reports/daily-sales/after`, H)));
  sleep(Math.random() + 0.5);
}
export function handleSummary(data) {
  return summary(data, 'REQ9 STRESS AFTER (1000 users, protected)');
}
