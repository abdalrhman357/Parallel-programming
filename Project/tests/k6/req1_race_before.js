import http from 'k6/http';
import { check } from 'k6';
import { track, summary } from './helpers.js';

/**
 * المتطلب 1 (قبل): Race Condition بدون قفل.
 * 100 مستخدم افتراضي يشترون نفس المنتج في آنٍ واحد عبر /api/checkout.
 * المتوقع: بيع كمية أكبر من المخزون (stock يصبح سالباً) = فقدان سلامة البيانات.
 *
 * تهيئة:  php artisan db:seed --class=RaceConditionSeeder   (منتج واحد stock=10، مستخدم id=1)
 * تشغيل:  k6 run req1_race_before.js
 */
export const options = { vus: 100, iterations: 100 };

const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/checkout';

export default function () {
    const res = http.post(URL, JSON.stringify({ user_id: 1 }), {
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    });
    track(res);
    check(res, { 'no 500': (r) => r.status !== 500 });
}

export function handleSummary(data) {
    return summary(data, 'REQ1 RACE BEFORE (no lock)', [
        '⚠️ تحقق يدوياً: SELECT stock_quantity FROM products  → غالباً قيمة سالبة (oversell)',
    ]);
}
