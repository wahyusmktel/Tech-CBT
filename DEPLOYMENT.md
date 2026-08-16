# Teknoplek CBT - Deployment Production

## Kebutuhan Server

- PHP 8.3+ beserta ekstensi Laravel, MySQL, Redis, GD, ZIP, XML, dan Mbstring.
- MySQL 8+.
- Redis untuk cache, session, queue, rate limiter, dan distributed lock.
- Nginx atau reverse proxy HTTPS.
- Node.js hanya diperlukan pada proses build frontend.
- Supervisor atau process manager sejenis untuk queue worker.

## Environment Production

Salin `.env.example` menjadi `.env`, lalu gunakan nilai production. Minimum konfigurasi:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.cbt.example.sch.id
FRONTEND_URL=https://cbt.example.sch.id
APP_LOCALE=id
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teknoplek_cbt
DB_USERNAME=teknoplek_cbt
DB_PASSWORD=use-a-secret-manager

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
CACHE_PREFIX=teknoplek-cbt-production-
REDIS_HOST=127.0.0.1
REDIS_CACHE_DB=1
SANCTUM_TOKEN_EXPIRATION=720

SUPER_ADMIN_EMAIL=admin@example.sch.id
SUPER_ADMIN_USERNAME=superadmin
SUPER_ADMIN_PASSWORD=use-a-long-random-password
```

Jangan commit `.env`, kredensial database, password Super Admin, atau `APP_KEY`. Gunakan secret manager dari platform deployment bila tersedia.

## Urutan Deployment Backend

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
php artisan queue:restart
```

`key:generate` hanya dijalankan pada instalasi pertama. Pada deployment berikutnya, pertahankan `APP_KEY` yang sama agar data terenkripsi tetap dapat dibaca.

Queue worker production:

```bash
php artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=120 --max-time=3600
```

Jalankan scheduler setiap menit:

```cron
* * * * * cd /var/www/teknoplek-cbt/cbt-api && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler membersihkan token Sanctum kedaluwarsa setiap hari.

## Build Frontend

```bash
npm ci
npm run lint
npm run build
```

Atur `VITE_API_URL` ke URL API production sebelum build. Sajikan folder `cbt-client/dist` melalui CDN atau Nginx dengan fallback SPA ke `index.html`.

## Health Check

- Liveness Laravel: `GET /up`
- Readiness database dan cache: `GET /api/v1/health/ready`

Load balancer hanya boleh mengirim trafik setelah readiness mengembalikan HTTP 200. Respons HTTP 503 berarti instance belum siap.

## Checklist Sebelum Go-Live

1. HTTPS aktif dan redirect HTTP ke HTTPS.
2. `APP_DEBUG=false` dan halaman error tidak membocorkan stack trace.
3. Redis aktif untuk cache, queue, session, rate limiter, dan lock.
4. Backup MySQL otomatis serta uji restore selesai.
5. Supervisor queue worker dan cron scheduler aktif.
6. Endpoint health dipantau.
7. `php artisan test`, `composer audit`, `npm run lint`, dan `npm run build` lulus.
8. Uji login Kurikulum, Pengawas, Siswa, serta Super Admin.
9. Uji autosave, reconnect, submit bersamaan, PDF, dan Excel.
10. Jalankan load test bertahap di staging sebelum trafik production.

Skenario k6 tersedia di folder `load-tests`. Mulai dari 100 VU, pantau error rate, p95/p99, CPU PHP-FPM, koneksi MySQL, Redis latency, dan antrean worker sebelum menaikkan target hingga 500–1000 VU.

## Strategi Rollback

- Simpan release sebelumnya dan backup database sebelum migrasi.
- Rollback aplikasi ke release sebelumnya terlebih dahulu.
- Jalankan `php artisan migrate:rollback --step=1 --force` hanya jika migration tersebut memang kompatibel untuk dibalik dan backup sudah tersedia.
- Jangan mengganti `APP_KEY` ketika rollback.
