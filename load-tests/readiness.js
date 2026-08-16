import http from 'k6/http'
import { check, sleep } from 'k6'

const baseUrl = __ENV.BASE_URL

if (!baseUrl) {
  throw new Error('BASE_URL wajib diisi dan harus menunjuk ke environment staging.')
}

export const options = {
  stages: [
    { duration: '30s', target: 100 },
    { duration: '1m', target: 500 },
    { duration: '1m', target: 1000 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    checks: ['rate>0.99'],
  },
}

export default function () {
  const response = http.get(`${baseUrl}/api/v1/health/ready`, {
    headers: { Accept: 'application/json' },
    tags: { endpoint: 'readiness' },
  })

  check(response, {
    'readiness HTTP 200': (result) => result.status === 200,
    'service is ready': (result) => result.json('status') === 'ready',
  })
  sleep(1)
}
