import http from 'k6/http';
import { trackServerMetrics, generateCustomSummary } from './metrics-helper.js';

export let options = {
    vus: 100,
    iterations: 100,
};

const BASE_URL = 'http://127.0.0.1:8000/api/checkoutWithLock';
// const BASE_URL = 'http://127.0.0.1:8000/api/checkout';

export default function () {
    const res = http.post(BASE_URL, null, {
        headers: { 'Accept': 'application/json' },
    });

    trackServerMetrics(res);
}

export function handleSummary(data) {
    return generateCustomSummary(data, "RACE CONDITION TEST SUMMARY");
}
