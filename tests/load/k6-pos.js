// Load test for the busiest screens (step 15, pre-launch "100 simultaneous order-line operations").
// Run against a STAGING copy, never the live restaurant:
//   k6 run -e BASE=https://staging.example.com -e USER=waiter -e PASS=*** tests/load/k6-pos.js
// Pass the password through the environment; do not write it in this file.
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    scenarios: {
        waiters: { executor: 'constant-vus', vus: 100, duration: '2m' },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<800'],
    },
};

const BASE = __ENV.BASE;

export function setup() {
    if (!BASE || !__ENV.USER || !__ENV.PASS)
        throw new Error('Set BASE, USER and PASS');
}

export default function () {
    const jar = http.cookieJar();
    let res = http.get(`${BASE}/login`);
    const xsrf = decodeURIComponent(
        (jar.cookiesForURL(BASE)['XSRF-TOKEN'] || [''])[0],
    );
    res = http.post(
        `${BASE}/login`,
        { username: __ENV.USER, password: __ENV.PASS },
        { headers: { 'X-XSRF-TOKEN': xsrf } },
    );
    check(res, { 'signed in': (r) => r.status === 200 });
    for (let i = 0; i < 10; i++) {
        res = http.get(`${BASE}/pos`, {
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Partial-Data': 'tables,orders',
                'X-Inertia-Partial-Component': 'pos/Index',
            },
        });
        check(res, { 'pos ok': (r) => r.status === 200 || r.status === 409 });
        sleep(1);
    }
}
