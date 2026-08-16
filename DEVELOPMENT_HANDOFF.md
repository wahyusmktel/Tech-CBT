# Handoff Pengembangan Teknoplek CBT

> Dokumen ini menjadi penghubung pekerjaan antara Codex dan Antigravity. Jangan menghapus riwayat yang sudah ada. Setiap alat/agen yang melanjutkan proyek wajib membaca `PROJECT_INSTRUCTIONS.md` dan dokumen ini sampai selesai sebelum mengubah kode.

## 1. Snapshot

- Tanggal handoff: 16 Agustus 2026 (Asia/Jakarta)
- Workspace: `C:\Projects\Teknoplek-CBT`
- Backend: `C:\Projects\Teknoplek-CBT\cbt-api`
- Frontend: `C:\Projects\Teknoplek-CBT\cbt-client`
- Branch baseline: `main`
- Commit baseline: `120d939e319892b5fbac74e5837a9a0a949f6ab5`
- Pesan commit: `Enhance configuration and routing for improved API security and performance; add new exception handling and request validation for exam and school management`
- Kondisi saat handoff: working tree bersih.
- Sumber kebutuhan utama: `PROJECT_INSTRUCTIONS.md`
- Panduan deployment: `DEPLOYMENT.md`
- Skenario load test: `load-tests/README.md`

Jika kondisi Git saat mulai bekerja berbeda, jangan reset atau menimpa perubahan yang ada. Periksa dahulu dengan:

```powershell
cd C:\Projects\Teknoplek-CBT
git status --short
git log -5 --oneline
```

## 2. Arsitektur dan Stack

### Backend

- Laravel 13 / PHP 8.3
- Laravel Sanctum untuk token API
- MySQL sebagai database utama
- Redis disiapkan untuk cache/session/queue production; lingkungan lokal saat ini dapat menggunakan driver database
- PhpSpreadsheet untuk impor/ekspor Excel
- PHPWord untuk impor bank soal DOCX
- DOMPDF untuk dokumen dan laporan PDF

### Frontend

- React 19 + Vite
- Tailwind CSS
- Axios
- `react-hot-toast`
- SweetAlert2
- Skeleton loading telah diterapkan pada halaman yang memuat data, termasuk halaman yang dibuat pada tahap awal

### Prinsip arsitektur wajib

- Aplikasi multi-tenant menggunakan satu database dan pemisahan data berdasarkan `school_id`.
- Primary key domain menggunakan UUID dan model menggunakan trait Laravel `HasUuids`.
- Validasi request kompleks ditempatkan pada Form Request.
- Otorisasi menggunakan Policy dan middleware role; jangan hanya mengandalkan pembatasan di frontend.
- Operasi controller/service yang berpotensi gagal harus ditangani dengan `try-catch`, transaksi database bila perlu, logging internal, serta respons aman tanpa membocorkan stack trace.
- Setiap query tenant wajib dibatasi ke sekolah pengguna aktif.
- Secret, password, token, dan isi `.env` tidak boleh dimasukkan ke Git atau dokumen handoff.

### Catatan UUID yang perlu diputuskan

`PROJECT_INSTRUCTIONS.md` menyebut UUID v4 sekaligus mewajibkan trait `HasUuids`. Pada Laravel 13, implementasi bawaan `HasUuids` menghasilkan UUID v7. Kode saat ini mengikuti trait yang diwajibkan sehingga ID baru berbentuk UUID v7. Jangan mengganti strategi UUID secara diam-diam karena berdampak pada data dan kompatibilitas. Jika UUID v4 mutlak diperlukan, dokumentasikan keputusan dan migrasinya terlebih dahulu.

## 3. Progres Fitur yang Sudah Selesai

| Area | Status | Ringkasan |
|---|---|---|
| Registrasi dan autentikasi sekolah | Selesai | Registrasi sekolah, login/logout, profil sekolah, dan data kop surat. |
| Siswa dan kelas | Selesai | CRUD, filter/pencarian, kelas, skeleton loading, impor Excel/CSV, upsert, ringkasan hasil impor, dan template Excel dengan 5 baris data dummy. |
| Ruang ujian | Selesai | CRUD ruang, pemetaan siswa, akun pengawas otomatis, dan rotasi kredensial. |
| Mata pelajaran dan ujian | Selesai | CRUD, pengaturan ruang, kode akses, bank soal tervalidasi, serta generate/regenerate kredensial peserta. |
| Bank soal | Selesai | Impor DOCX dengan parser ketat, preview, validasi, pertanyaan dan pilihan jawaban. |
| Pelaksanaan ujian siswa | Selesai | Login terpisah, timer, peringatan reload/keluar, autosave async, restore jawaban, submit, scoring, auto-submit saat waktu habis, dan locking untuk konkurensi. |
| Monitoring pengawas | Selesai | Monitoring status peserta dengan polling 5 detik dan query bulk/eager-loading. |
| Laporan | Selesai | Preview nilai/analisis, Excel dua sheet, PDF hasil, dan PDF analisis dengan kop sekolah. |
| Dokumen ujian | Selesai | PDF daftar hadir, berita acara, dan kartu ujian; kartu memakai foto jika tersedia atau inisial sebagai fallback. |
| Super Admin | Selesai | Statistik lintas sekolah, detail sekolah, reset password admin kurikulum, revoke token, UUID audit log, dan rate limiting. |
| Hardening | Selesai | Security headers API, readiness endpoint, indeks query high-load, cache ringkasan, expiration/pruning token, dan konfigurasi CORS download. |
| Deployment/load-test assets | Selesai | Panduan deployment production dan skenario k6 untuk readiness serta sinkronisasi jawaban. |

## 4. Halaman Frontend Utama

- `/dashboard`
- `/settings/school`
- `/students`
- `/rooms`
- `/exams`
- `/question-banks`
- `/reports`
- `/student-login`
- `/student/exam`
- `/observer/monitoring`
- `/super-admin`

API berada di bawah prefix `/api/v1`. Kelompok endpoint utama mencakup autentikasi, curriculum CRUD, reporting/export, dokumen PDF, login dan sesi ujian siswa, monitoring pengawas, Super Admin, serta readiness check `/api/v1/health/ready`. Laravel liveness check tersedia pada `/up`.

## 5. Database dan Migrasi

Urutan kelompok migrasi domain:

- Initial: schools, users, dan tabel Laravel pendukung.
- `120000`: classrooms dan students.
- `130000`: rooms dan pemetaan siswa.
- `140000`: subjects, exams, dan credentials.
- `150000`: question banks, questions, dan choices.
- `160000`: attempts, answers, access code, dan relasi bank soal ujian.
- `170000`: `super_admin_audit_logs`.
- `180000`: composite indexes untuk query dengan beban tinggi.

Semua migrasi di atas sudah pernah berhasil diterapkan ke MySQL lokal pada baseline. Untuk lingkungan baru tetap jalankan migrasi dan verifikasi statusnya.

## 6. Konfigurasi Environment

Salin konfigurasi contoh, tetapi jangan menyalin secret dari mesin lain:

```powershell
cd C:\Projects\Teknoplek-CBT\cbt-api
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
```

Catatan penting:

- `.env.example` memuat komentar switch Redis untuk production. Lokal dapat tetap memakai cache/session/queue berbasis database; production diarahkan ke Redis sesuai `DEPLOYMENT.md`.
- `SANCTUM_TOKEN_EXPIRATION=720` tersedia sebagai baseline.
- `HEALTH_RATE_LIMIT=120` tersedia sebagai baseline.
- Cookie/session production harus secure ketika HTTPS aktif.
- Akun Super Admin tidak dibuat dengan password default. Isi variabel `SUPER_ADMIN_*` langsung pada environment tujuan, kemudian jalankan `php artisan db:seed --force`.
- Jangan menuliskan nilai `APP_KEY`, password database, kredensial Redis, atau password akun dalam dokumen ini.

## 7. Menjalankan Proyek

### Backend

```powershell
cd C:\Projects\Teknoplek-CBT\cbt-api
composer install
php artisan migrate
php artisan serve
```

Untuk worker/scheduler sesuai kebutuhan environment, ikuti `DEPLOYMENT.md`. Production harus memakai web server/PHP-FPM yang sesuai, bukan `php artisan serve`.

### Frontend

```powershell
cd C:\Projects\Teknoplek-CBT\cbt-client
npm install
npm run dev
```

Pastikan base URL API frontend mengarah ke backend yang aktif dan aturan CORS mengizinkan origin frontend.

## 8. Baseline Verifikasi Terakhir

Hasil verifikasi Codex sebelum handoff:

- Backend: 42 test lulus, 231 assertions.
- Laravel Pint: lulus.
- Frontend ESLint: lulus.
- Frontend production build: lulus (101 modules; bundle JS sekitar 456 KB pada saat pengujian).
- `composer audit`: tidak menemukan vulnerability.
- `npm audit`: tidak menemukan vulnerability.
- `php artisan optimize` berhasil untuk config, events, routes, dan views; cache kemudian dibersihkan untuk kembali ke development.
- Scheduler menampilkan Sanctum prune setiap hari pukul 02.00.
- QA visual telah dilakukan untuk laporan PDF, daftar hadir, berita acara, kartu ujian, serta workbook laporan Excel.
- Smoke test lokal readiness dengan 25 request bersamaan: 25/25 HTTP 200.

Perintah verifikasi minimum setelah perubahan:

```powershell
cd C:\Projects\Teknoplek-CBT\cbt-api
php artisan test
vendor\bin\pint --test
composer audit

cd C:\Projects\Teknoplek-CBT\cbt-client
npm run lint
npm run build
npm audit
```

Sesuaikan test tambahan dengan area yang diubah. Migrasi baru wajib diuji pada database disposable/staging sebelum production.

## 9. Batasan dan Pekerjaan Lanjutan

Core scope di instruksi proyek telah diimplementasikan. Prioritas lanjutan yang paling bernilai:

1. Siapkan environment staging yang menyerupai production: Nginx/Apache, PHP-FPM multi-worker, MySQL, Redis, HTTPS, queue worker, dan scheduler.
2. Jalankan load test nyata 500–1000 virtual users di staging menggunakan skrip k6 dalam `load-tests`. Jangan memakai server bawaan PHP sebagai dasar klaim kapasitas.
3. Lakukan UAT browser end-to-end untuk alur admin kurikulum, impor siswa, impor soal, generate kredensial, ujian siswa, monitoring, laporan, dan Super Admin.
4. Tambahkan CI/CD yang menjalankan backend tests, Pint, frontend lint/build, audit dependency, dan pemeriksaan migration.
5. Pertimbangkan pagination/sorting server-side dan optimasi bundle untuk data/sekolah besar.
6. Implementasikan upload/management foto siswa bila dibutuhkan. Kolom `photo_path` dan fallback inisial sudah tersedia, tetapi UI/API upload foto belum dibuat.
7. Putuskan kebijakan UUID v4 versus UUID v7 seperti catatan pada bagian arsitektur.
8. Tambahkan observability production: centralized logging, error monitoring, metrics, dan alert readiness.

Perilaku yang memang disengaja dan bukan bug:

- Browser tidak dapat benar-benar dilarang melakukan reload. Implementasi saat ini memberi peringatan dan memulihkan jawaban dari server.
- Monitoring pengawas bersifat near-real-time melalui polling 5 detik, bukan WebSocket.
- Load smoke lokal hanya membuktikan endpoint merespons; bukan bukti kapasitas production.

## 10. Aturan Kerja untuk Antigravity

Sebelum mengubah kode:

1. Baca `PROJECT_INSTRUCTIONS.md`, dokumen ini, dan `DEPLOYMENT.md` bila menyentuh deployment/config.
2. Jalankan `git status --short` dan pertahankan semua perubahan pengguna yang sudah ada.
3. Telusuri implementasi yang ada sebelum membuat abstraction, route, komponen, atau tabel baru agar tidak terjadi duplikasi.
4. Pertahankan multi-tenancy, UUID, Form Request, Policy, middleware role, `try-catch`, transaksi, dan pola respons API yang sudah ada.
5. Buat test untuk fitur atau bug fix dan jalankan verification minimum yang relevan.
6. Jangan menjalankan reset database, menghapus file, force push, atau operasi destruktif tanpa persetujuan eksplisit pengguna.
7. Commit pekerjaan dalam unit yang koheren. Jangan commit `.env`, secret, file sementara, hasil build, atau dependency directory.

## 11. Format Handoff Balik ke Codex

Antigravity diminta **meng-update dokumen ini** sebelum pekerjaan dikembalikan ke Codex. Tambahkan entri baru di bagian `Riwayat Lanjutan` di bawah; jangan menghapus snapshot dan riwayat sebelumnya. Setiap entri minimal berisi:

- tanggal dan alat/agen yang bekerja;
- tujuan pekerjaan;
- ringkasan implementasi;
- daftar file yang dibuat/diubah;
- route/API dan perubahan schema/migrasi;
- perubahan variable environment tanpa nilai secret;
- test/lint/build/audit yang dijalankan beserta hasilnya;
- migrasi sudah diterapkan atau belum, dan di environment mana;
- masalah terbuka, asumsi, serta keputusan teknis;
- branch, commit hash, dan kondisi `git status` terakhir;
- instruksi konkret untuk pekerjaan berikutnya.

Gunakan format berikut:

```markdown
### YYYY-MM-DD — Antigravity

**Tujuan:** ...

**Yang dikerjakan:**
- ...

**File berubah:**
- `path/to/file`

**API/database/env:**
- ...

**Verifikasi:**
- `command` -> hasil

**Git:**
- Branch: ...
- Commit: ...
- Status: bersih / daftar perubahan belum dikomit

**Masalah/keputusan:**
- ...

**Langkah berikutnya:**
- ...
```

## 12. Riwayat Lanjutan

### 2026-08-16 — Codex

**Tujuan:** Menyelesaikan scope utama CBT, reporting, dokumen ujian, Super Admin, hardening, deployment guide, dan persiapan load test.

**Yang dikerjakan:** Seluruh modul pada tabel progres telah diimplementasikan dan diverifikasi pada baseline.

**Git:**

- Branch: `main`
- Commit: `120d939e319892b5fbac74e5837a9a0a949f6ab5`
- Status sebelum dokumen handoff dibuat: bersih

**Langkah berikutnya:** Gunakan daftar prioritas pada bagian 9 dan catat seluruh pekerjaan Antigravity sebagai entri baru di bawah entri ini.
