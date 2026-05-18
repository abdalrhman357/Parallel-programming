import { Counter, Trend } from 'k6/metrics';

// تعريف العدادات والمقاييس بشكل مركزي
export const successCounter = new Counter('successful_requests');
export const failedCounter = new Counter('failed_requests');

export const serverTimeTrend = new Trend('server_time_ms');
export const serverMemoryTrend = new Trend('server_memory_mb');
export const serverCpuTrend = new Trend('server_cpu_usage');

// دالة مركزية لتفريغ الـ Headers وإضافتها للمقاييس
export function trackServerMetrics(res) {
    const sTime = parseFloat(res.headers['X-Server-Time-Ms'] || res.headers['x-server-time-ms']);
    const sMem = parseFloat(res.headers['X-Server-Memory-Mb'] || res.headers['x-server-memory-mb']);
    const sCpu = parseFloat(res.headers['X-Server-Cpu-Usage'] || res.headers['x-server-cpu-usage']);

    if (!isNaN(sTime)) serverTimeTrend.add(sTime);
    if (!isNaN(sMem)) serverMemoryTrend.add(sMem);
    if (!isNaN(sCpu)) serverCpuTrend.add(sCpu);

    if (res.status === 200) {
        successCounter.add(1);
    } else {
        failedCounter.add(1);
    }
}

// دالة توليد التقرير الموحدة
export function generateCustomSummary(data, testName = "PERFORMANCE TEST SUMMARY") {
    const success = data.metrics.successful_requests ? data.metrics.successful_requests.values.count : 0;
    const failed = data.metrics.failed_requests ? data.metrics.failed_requests.values.count : 0;
    const total = success + failed;
    const rate = total > 0 ? (success / total) * 100 : 0;

    const avgServerTime = data.metrics.server_time_ms ? data.metrics.server_time_ms.values.avg.toFixed(2) : '0.00';
    const avgServerMem = data.metrics.server_memory_mb ? data.metrics.server_memory_mb.values.avg.toFixed(2) : '0.00';
    const avgServerCpu = data.metrics.server_cpu_usage ? data.metrics.server_cpu_usage.values.avg.toFixed(4) : '0.0000';

    const report = `
    ================================================
    📊 ${testName}
    ================================================
    🚀 Total Requests Sent   : ${total}
    ✅ Successful (200 OK)   : ${success}
    ❌ Failed Requests       : ${failed}
    📈 Success Rate          : ${rate.toFixed(2)}%
    ================================================
    ⏱️ Avg Network Latency   : ${data.metrics.http_req_duration.values.avg.toFixed(2)} ms

    [AOP Performance Monitor Data]:
    ⏱️ Avg Server Execution  : ${avgServerTime} ms
    🧠 Avg RAM Consumption   : ${avgServerMem} MB
    ⚙️  Avg CPU Process Time : ${avgServerCpu} s
    ================================================
    `;

    return { 'stdout': report };
}
