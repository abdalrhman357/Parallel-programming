import http from 'k6/http';
import { check } from 'k6';

/**
 * المتطلب 10: القياس وتحديد الاختناق.
 * يستدعي /api/products/benchmark الذي يقيس نفس الاستعلام قبل/بعد الكاش
 * ويُرجع المتوسط ونسبة التحسّن ويحدّد الاختناق.
 * تشغيل: k6 run req10_benchmark.js
 */
export const options = { vus: 1, iterations: 1 };
const URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000') + '/api/products/benchmark?iterations=100';
export default function () {
  const res = http.get(URL, { headers: { 'Accept': 'application/json' } });
  check(res, { 'status 200': (r) => r.status === 200 });
  console.log(res.body);
}
export function handleSummary(data) {
  const m = data.metrics;
  const report = `
================================================
  REQ10 BENCHMARK (Cache Before vs After)
================================================
  Check the console output above:
  - before_cache.avg_ms   (Database query = bottleneck)
  - after_cache.avg_ms    (Cache read)
  - improvement_percent   (Performance improvement)
================================================
`;
  return { stdout: report, 'report_req10_benchmark.txt': report };
}
