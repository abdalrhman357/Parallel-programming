import http from 'k6/http';
import { check } from 'k6';
import { track, summary, rand } from './helpers.js';

/**
 * المتطلب 8 (بعد): ترانزكشن ذرّي (DB::beginTransaction/commit/rollback) داخل القفل.
 * الدفع + خصم المخزون + إنشاء الطلب: تنجح كلها أو تُلغى كلها.
 * المتوقع: ثوابت البيانات محفوظة دائماً حتى تحت التزامن.
 * تشغيل: k6 run req8_acid_after.js
 * تحقّق بعد التشغيل (php artisan tinker):
 *   // لكل طلب paid يجب أن يوجد order_items مطابقة ومخزون مخصوم بنفس الكمية
 *   $sold = App\Models\OrderItem::sum('quantity');
 *   // المخزون المخصوم = (المخزون الابتدائي) - $sold  ← يجب أن يتطابق تماماً
 */
export const options = { vus: 40, iterations: 80, thresholds: { server_errors_500: ['count<1'] } };
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/checkoutWithLock';
export default function () {
  const res = http.post(URL, JSON.stringify({ user_id: rand(1, 10) }),
    { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } });
  track(res);
  let body = {}; try { body = JSON.parse(res.body); } catch (e) {}
  check(res, {
    'لا 500': (r) => r.status !== 500,
    'نجاح يعطي order_id': (r) => r.status !== 200 || (body.order_id > 0),
  });
}
export function handleSummary(data) {
  return summary(data, 'REQ8 ACID AFTER (atomic transaction)');
}
