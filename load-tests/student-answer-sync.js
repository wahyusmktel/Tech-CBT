import http from 'k6/http'
import { check, sleep } from 'k6'
import { SharedArray } from 'k6/data'

const baseUrl = __ENV.BASE_URL
const datasetPath = __ENV.DATASET

if (!baseUrl || !datasetPath) {
  throw new Error('BASE_URL dan DATASET wajib diisi. Gunakan hanya data siswa staging.')
}

const participants = new SharedArray('participants', () => JSON.parse(open(datasetPath)))

export const options = {
  scenarios: {
    answer_sync: {
      executor: 'per-vu-iterations',
      vus: Math.min(participants.length, Number(__ENV.VUS ?? 500)),
      iterations: 20,
      maxDuration: '5m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<750', 'p(99)<1500'],
    checks: ['rate>0.99'],
  },
}

export default function () {
  const participant = participants[(__VU - 1) % participants.length]
  const answer = participant.answers[__ITER % participant.answers.length]
  const response = http.put(
    `${baseUrl}/api/v1/student/exam/answers/${answer.question_id}`,
    JSON.stringify({ question_choice_id: answer.choice_id }),
    {
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${participant.token}`,
      },
      tags: { endpoint: 'answer-sync' },
    },
  )

  check(response, {
    'answer saved': (result) => result.status === 200,
    'response acknowledged': (result) => result.json('message') === 'Jawaban tersimpan.',
  })
  sleep(1)
}
