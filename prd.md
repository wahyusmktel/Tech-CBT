# Product Requirements Document (PRD) - SaaS Computer Based Test (CBT)

## 1. Project Overview
Aplikasi CBT berbasis web dengan arsitektur Multi-Tenant (SaaS). Aplikasi ini memungkinkan berbagai sekolah di seluruh Indonesia untuk mendaftar dan menyelenggarakan ujian secara mandiri. Sistem dirancang untuk menangani beban tinggi (500-1000 concurrent users per sekolah) dan dilengkapi dengan standar keamanan mendalam (*deep security*).

## 2. Tech Stack & Dependencies
- **Frontend:** React.js (Vite), Tailwind CSS, React Router DOM, Axios.
- **Backend:** Laravel (API Resource), PHPWord (Parser Docx), DOMPDF (Export PDF).
- **Database & Cache:** MySQL, Redis (untuk Cache, Sessions, & Queues).
- **UI/UX Dependencies:** 
  - `react-hot-toast` (Wajib untuk semua notifikasi sukses/gagal).
  - `sweetalert2` (Wajib untuk semua konfirmasi aksi seperti Delete, Generate, Validasi).
- **Design System:** Font "Inter" (MacOS style). Tema warna: Dominan Putih, kombinasi Merah (Primary) dan Abu-abu gradien (Secondary/Background).

## 3. Architecture & Multi-Tenancy
- **Database Design (Multi-Tenant):** Terapkan pendekatan Single Database - Multi Tenant. Hampir seluruh tabel utama (users, siswa, soal, ujian) WAJIB memiliki kolom `school_id` untuk memisahkan data antar sekolah.
- **Primary Keys (UUID):** WAJIB menggunakan **UUID (Universally Unique Identifier)** tipe v4 untuk semua *primary key* di tabel database (bukan Auto-Increment/Integer ID). Ini krusial untuk mencegah *Insecure Direct Object Reference (IDOR)* dan *ID Enumeration*.

## 4. Roles & Permissions
1. **Super Admin:** Master pengelola aplikasi. Bisa melihat data seluruh sekolah terdaftar, memonitoring siswa/nilai/mapel lintas sekolah, dan melakukan reset password.
2. **Kurikulum (Admin Sekolah):** Dibuat saat pendaftaran sekolah. Mengakses pengaturan sekolah, bank soal, administrasi ujian, import siswa, dan mapping ruang.
3. **Pengawas:** Role khusus untuk memonitoring siswa selama ujian berlangsung. Akun digenerate otomatis saat pembuatan ruang ujian.
4. **Siswa:** Hanya mengakses halaman pengerjaan soal ujian.

## 5. Core Features & User Stories
*(Fitur sama seperti sebelumnya, dengan tambahan fokus keamanan asinkron)*

### A. Registrasi Sekolah (Public Area)
- Pengunjung publik dapat mendaftar sekolah baru.
- Field wajib: NPSN, Jenis Sekolah (Dropdown: SMP/MTs/Sederajat, SMA/SMK/Sederajat, SD/MI/Sederajat), Alamat, Email, Username, Password.
- Setelah sukses mendaftar, akun dengan role `Kurikulum` otomatis dibuat untuk sekolah tersebut.

### B. Pengaturan Sekolah (Modul Kurikulum)
- Form untuk melengkapi profil: Nama Sekolah, NPSN, Alamat, Nama Kepsek, No HP, Email.
- Fitur unggah **Kop Surat** (image). Gambar ini akan disematkan pada setiap dokumen cetak (Berita Acara, Laporan Nilai).

### C. Administrasi Siswa (Modul Kurikulum)
- **Import Data Siswa (Excel/CSV):**
  - Data berisi: NISN, Nama, Kelas.
  - **Logika Otomatisasi Kelas:** Jika kelas di file import belum ada di master kelas, sistem otomatis insert data kelas baru.
  - **Logika Anti Duplikasi (Upsert):** Jika NISN sudah ada, lakukan UPDATE data. Jika NISN belum ada, lakukan INSERT data.
  - Selalu kembalikan "Resume Hasil Import" (contoh: "Sukses insert 50 data, update 10 data").
- Fitur pengecekan data siswa per rombel/kelas (Filter & Data Table).

### D. Administrasi Ujian & Ruang (Modul Kurikulum)
- **Mapping Ruangan:** CRUD master ruangan (Ruang 1, Ruang 2, dst).
- **Mapping Siswa:** Memasukkan siswa ke dalam ruang ujian (mendukung multi-select/masal berdasarkan kelas).
- **Manajemen Ujian:**
  - CRUD Ujian.
  - **Generate Kredensial:** Tombol untuk membuat Username (opsi: gunakan NISN) dan Password (opsi: random angka/huruf/kombinasi, panjang karakter bisa diset).
  - **Lock Mechanism:** Jika kredensial sudah digenerate, tombol terkunci. Jika ditekan ulang, gunakan SweetAlert2 untuk warning: "Melakukan tindakan ini akan merubah seluruh username & password ujian ini".
  - **Generate Pengawas:** Saat ruang ujian dibuat untuk suatu jadwal, sistem otomatis membuatkan 1 akun Pengawas (username/password random) yang ditautkan ke ruang tersebut.
  - **Cetak Dokumen:** Cetak Daftar Hadir (PDF), Berita Acara (PDF), dan Kartu Ujian (PDF, menampilkan Foto, Username, Password). Kredensial wajib digenerate sebelum bisa mencetak kartu.

### E. Bank Soal & Validasi (Modul Kurikulum)
- CRUD Mata Pelajaran.
- **Import Soal (.docx):**
  - Parsing dokumen word dengan struktur ketat:
    `1. [Teks Soal]`
    `A. [Pilihan A]`
    `B. [Pilihan B]`
    `...`
    `ANS : [Kunci Jawaban]`
  - Simpan teks soal dan pilihan ke database terstruktur.
- **Preview & Validasi:**
  - Halaman untuk melakukan preview soal yang diunggah.
  - Tombol "Validasi Soal" (Gunakan SweetAlert2).
  - Jika tervalidasi, tambahkan *stamp* pada UI bank soal: *"Soal sudah divalidasi oleh {nama_user} pada {tanggal_dinamis}."*

### F. Pelaksanaan Ujian (Modul Siswa & Pengawas)
- **Siswa:** Halaman ujian SPA (Single Page Application). Anti reload. Kirim jawaban ke backend secara asinkron (background sync).
- **Pengawas:** Dashboard monitoring *real-time* melihat status login siswa, progress pengerjaan, dan status selesai pada ruang yang diawasinya.

### G. Reporting (Modul Kurikulum)
- **Hasil Ujian:** Unduh rekap nilai per mata pelajaran (PDF & Excel). Gunakan Kop Surat sekolah.
- **Analisis Butir Soal:** Unduh dokumen PDF berisi metrik analisis setiap soal (jumlah penjawab benar/salah). Gunakan Kop Surat sekolah.

## 6. Coding Conventions, Security, & Rules for AI
AI Agent WAJIB mematuhi aturan penulisan kode berikut tanpa terkecuali:

### A. In-Depth Security Measures (Keamanan Mendalam)
1. **Strict UUID Implementation:** Gunakan trait `HasUuids` pada setiap model Laravel. Jangan pernah mengekspos integer ID ke Frontend.
2. **Rate Limiting:** Implementasikan Laravel Rate Limiting secara ketat pada *endpoint* krusial:
   - Endpoint Login (maksimal 5 percobaan per menit).
   - Endpoint Submit Jawaban/Simpan Asinkron (cegah *DDoS* atau manipulasi *payload* terus-menerus).
3. **Mass Assignment Protection:** Selalu definisikan property `$fillable` secara eksplisit pada model Laravel. Jangan gunakan `$guarded = []`.
4. **Data Sanitization & Validation:** 
   - Backend WAJIB menggunakan Laravel `FormRequest` untuk memvalidasi *semua* input dari Frontend.
   - Frontend wajib melakukan filter input (mencegah XSS) sebelum merender string, terutama pada teks pertanyaan soal ujian.
5. **Authorization (Gate/Policy):** Setiap *endpoint* API (kecuali public) wajib mengecek apakah `school_id` dari user yang *login* cocok dengan `school_id` pada data yang sedang diakses (Mencegah tenant A mengakses data tenant B).

### B. Error Handling (Try-Catch Requirement)
1. **Backend (Laravel):** Semua *logic* yang berinteraksi dengan database, *file system*, atau proses eksternal di dalam Controller atau Service WAJIB dibungkus dengan blok `try-catch`. 
   - Gunakan `DB::beginTransaction()` dan `DB::rollBack()` di dalam blok `catch` untuk aksi yang mengubah banyak tabel (misal: saat Import Siswa atau Generate Ujian).
   - Kembalikan response JSON yang konsisten saat `catch` (status 500, message: "Terjadi kesalahan sistem", sembunyikan detail error dari *user* di production, log detail errornya).
2. **Frontend (React):** Semua panggilan API (menggunakan `axios` atau `fetch`) wajib berada di dalam fungsi `async/await` dengan blok `try-catch`. Tangkap *error* di blok `catch` dan tampilkan notifikasi menggunakan `react-hot-toast`.

### C. UI/UX Rules
- Gunakan React Functional Components & Hooks.
- Setiap aksi modifikasi data (Create, Update, Delete) di Frontend harus memicu `react-hot-toast` jika berhasil/gagal.
- Aksi destruktif (Delete) wajib menampilkan `sweetalert2` confirmation box sebelum dieksekusi.