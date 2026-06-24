import http from 'k6/http';
import { check } from 'k6';
import { track, summary, rand } from './helpers.js';

/**
 * المتطلب 8 (قبل): بلا ترانزكشن ذرّي — /api/checkout يخصم الرصيد ثم يحدّث المخزون
 * في خطوات منفصلة. تحت الضغط قد تحدث كتابات جزئية (خصم رصيد بلا مخزون صحيح).
 * المتوقع: كسر ثوابت البيانات (انظر التحقق أدناه).
 * تشغيل: k6 run req8_acid_before.js
 * تحقّق بعد التشغيل (php artisan tinker):
 *   App\Models\Product::sum('stock_quantity'); // قد لا يطابق المخزون المتوقع
 */
export const options = { vus: 40, iterations: 80 };
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/checkout';
export default function () {
  const res = http.post(URL, JSON.stringify({ user_id: rand(1, 10) }),
    { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } });
  track(res);
  check(res, { 'no 500': (r) => r.status !== 500 });
}
export function handleSummary(data) {
  return summary(data, 'REQ8 ACID BEFORE (non-atomic)');
}
