#!/usr/bin/env bash
# تشغيل كل اختبارات قبل/بعد بالتسلسل وحفظ التقارير في مجلد results/
set -e
mkdir -p results
PAIRS=(req1_race req2_resource req3_queue req4_batch req6_cache req7_lock req8_acid req9_stress)
for p in "${PAIRS[@]}"; do
  echo "=================== $p (BEFORE) ==================="
  k6 run "${p}_before.js"  || true
  echo "=================== $p (AFTER) ===================="
  k6 run "${p}_after.js"   || true
done
echo "=================== req5 load (single vs balanced) ==================="
k6 run req5_load_before.js || true
BASE_URL=http://localhost:8080 k6 run req5_load_after.js || true
echo "=================== req10 benchmark ==================="
k6 run req10_benchmark.js || true
mv report_*.txt results/ 2>/dev/null || true
echo "تم. التقارير في results/"
