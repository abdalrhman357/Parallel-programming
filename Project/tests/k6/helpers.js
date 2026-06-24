import { Counter, Trend, Rate } from 'k6/metrics';

/**
 * ملف مساعد مشترك لكل اختبارات قبل/بعد.
 * يقرأ مقاييس الـ AOP من ترويسات الاستجابة:
 *   X-Server-Time-Ms , X-Server-Memory-Mb , X-Server-CPU-Usage
 * ويعدّ رموز الحالة بوضوح (2xx مقبول / 429 و503 مرفوض منضبط / 500 انهيار).
 */

export const accepted     = new Counter('accepted_2xx');       // 200/201 مقبول
export const rejected429   = new Counter('rejected_429');       // تجاوز المعدل
export const rejected503   = new Counter('rejected_503');       // امتلاء السعة
export const businessFail  = new Counter('business_400');       // رفض منطقي (رصيد/مخزون)
export const serverErr500  = new Counter('server_errors_500');  // انهيار

export const logicalRate   = new Rate('logical_ok_rate');       // ردّ السيرفر بشكل صحيح
export const acceptRate     = new Rate('accept_rate');          // نسبة المقبول فعلاً

export const serverTime    = new Trend('server_time_ms');
export const serverMem     = new Trend('server_memory_mb');
export const serverCpu     = new Trend('server_cpu_usage');

export function track(res) {
    const t = parseFloat(res.headers['X-Server-Time-Ms']   || res.headers['x-server-time-ms']);
    const m = parseFloat(res.headers['X-Server-Memory-Mb'] || res.headers['x-server-memory-mb']);
    const c = parseFloat(res.headers['X-Server-Cpu-Usage'] || res.headers['x-server-cpu-usage']);
    if (!isNaN(t)) serverTime.add(t);
    if (!isNaN(m)) serverMem.add(m);
    if (!isNaN(c)) serverCpu.add(c);

    const s = res.status;
    if (s === 200 || s === 201) { accepted.add(1); acceptRate.add(true); }
    else { acceptRate.add(false); }

    if (s === 429) rejected429.add(1);
    if (s === 503) rejected503.add(1);
    if (s === 400) businessFail.add(1);
    if (s === 500) serverErr500.add(1);

    // "ناجح منطقياً": ردّ السيرفر بشكل صحيح (حتى لو رفض الطلب لأسباب عمل/حماية)
    logicalRate.add([200, 201, 400, 429, 503].includes(s));
}

export function rand(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

export function summary(data, title, extraLines = []) {
    const m = data.metrics;
    const num = (k, f = 'avg', d = 2) => (m[k] ? Number(m[k].values[f]).toFixed(d) : 'N/A');
    const cnt = (k) => (m[k] ? m[k].values.count : 0);

    const total    = cnt('accepted_2xx') + cnt('rejected_429') + cnt('rejected_503')
                   + cnt('business_400') + cnt('server_errors_500');
    const okRate   = ((m.logical_ok_rate?.values?.rate || 0) * 100).toFixed(1);
    const accRate  = ((m.accept_rate?.values?.rate || 0) * 100).toFixed(1);

    const lines = [
        `Total Requests        : ${total}`,
        `Accepted (200/201)    : ${cnt('accepted_2xx')}   (${accRate}%)`,
        `Rejected 429 (rate)   : ${cnt('rejected_429')}`,
        `Rejected 503 (full)   : ${cnt('rejected_503')}`,
        `Business reject (400) : ${cnt('business_400')}`,
        `Server Errors (500)   : ${cnt('server_errors_500')}`,
        `Logical OK Rate       : ${okRate}%`,
        `Avg Network Latency   : ${num('http_req_duration')} ms`,
        `P95 Network Latency   : ${num('http_req_duration', 'p(95)')} ms`,
        `--- AOP (server-side) ---`,
        `Avg Server Exec Time  : ${num('server_time_ms')} ms`,
        `Avg RAM Consumption   : ${num('server_memory_mb')} MB`,
        `Avg CPU Process Time  : ${num('server_cpu_usage', 'avg', 4)} s`,
        ...extraLines.map((fn) => (typeof fn === 'function' ? fn(m, { num, cnt }) : fn)),
    ];

    const body = lines.map((l) => '  ' + l).join('\n');
    const report = `
================================================
  ${title}
================================================
${body}
================================================
`;
    const safeName = title.replace(/[^a-zA-Z0-9]+/g, '_').toLowerCase();
    return { stdout: report, [`report_${safeName}.txt`]: report };
}
