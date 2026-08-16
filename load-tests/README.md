# Load Test Staging

Skenario ini hanya boleh dijalankan pada environment staging yang menggunakan konfigurasi production-like: Nginx, beberapa PHP-FPM worker, MySQL, dan Redis. Jangan arahkan load test ke production tanpa change window dan persetujuan operasional.

## Readiness Ramp 1000 VU

Pada staging khusus load test, naikkan `HEALTH_RATE_LIMIT=100000`, jalankan `php artisan config:clear`, lalu kembalikan nilainya setelah pengujian. Production sebaiknya mempertahankan limit rendah dan membatasi readiness melalui jaringan load balancer.

```bash
k6 run -e BASE_URL=https://staging-api.example.sch.id load-tests/readiness.js
```

## Autosave Jawaban

Siapkan JSON berisi token unik untuk setiap siswa staging. Jangan memakai satu token untuk banyak virtual user karena lock attempt memang sengaja menserialkan request milik siswa yang sama.

```json
[
  {
    "token": "token-siswa-staging",
    "answers": [
      { "question_id": "uuid-soal", "choice_id": "uuid-pilihan" }
    ]
  }
]
```

Jalankan:

```bash
k6 run -e BASE_URL=https://staging-api.example.sch.id -e DATASET=./participants.json -e VUS=500 load-tests/student-answer-sync.js
```

File dataset memuat kredensial sensitif dan wajib berada di luar Git. Hapus token staging setelah pengujian.
