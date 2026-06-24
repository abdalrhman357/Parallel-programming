import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { track, summary, rand } from './helpers.js';

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8080';
export const options = {
  scenarios: {
    full: {
      executor: 'ramping-vus', startVUs: 0, stages: [
        { duration: '10s', target: 50 }, { duration: '10s', target: 100 },
        { duration: '30s', target: 100 }, { duration: '10s', target: 0 },
      ]
    }
  },
  thresholds: { server_errors_500: ['count<1'], logical_ok_rate: ['rate>0.90'] },
};
const H = { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } };
export default function () {
  const uid = rand(1, 10);
  group('browse', () => track(http.get(`${BASE}/api/products`, H)));
  group('cart', () => track(http.post(`${BASE}/api/cart/add`, JSON.stringify({ product_id: rand(1, 10), quantity: 1 }), H)));
  group('checkout', () => { const r = http.post(`${BASE}/api/checkoutWithLock`, JSON.stringify({ user_id: uid }), H); track(r); check(r, { 'no 500': (x) => x.status !== 500 }); });
  group('server', () => track(http.get(`${BASE}/api/system/server-info`, H)));
  group('report', () => track(http.get(`${BASE}/api/reports/daily-sales/_after`, H)));
  sleep(Math.random() + 0.5);
}
export function handleSummary(data) {
  return summary(data, 'REQ9 STRESS AFTER (100 users, protected)');
}
