# Product Requirements Document (PRD) - SaaS Computer Based Test (CBT)

## 1. Project Overview
Aplikasi CBT berbasis web dengan arsitektur Multi-Tenant (SaaS). Aplikasi ini memungkinkan berbagai sekolah di seluruh Indonesia untuk mendaftar dan menyelenggarakan ujian secara mandiri. Sistem dirancang untuk menangani beban tinggi (500-1000 concurrent users per sekolah).

## 2. Tech Stack & Dependencies
- **Frontend:** React.js (Vite), Tailwind CSS, React Router DOM.
- **Backend:** Laravel (API Resource), PHPWord (Parser Docx), DOMPDF (Export PDF).
- **Database & Cache:** MySQL, Redis (untuk Cache & Queues).
- **UI/UX Dependencies:** 
  - `react-hot-toast` (Wajib untuk semua notifikasi sukses/gagal).
  - `sweetalert2` (Wajib untuk semua konfirmasi aksi seperti Delete, Generate, Validasi).
- **Design System:** Font "Inter" (MacOS style). Tema warna: Dominan Putih, kombinasi Merah (Primary) dan Abu-abu gradien (Secondary/Background).

## 3. Architecture & Multi-Tenancy
- **Database Design:** Terapkan pendekatan Single Database - Multi Tenant. Hampir seluruh tabel utama (users, siswa, soal, ujian) WAJIB memiliki kolom `school_id` untuk memisahkan data antar sekolah.

## 4. Roles & Permissions
1. **Super Admin:** Master pengelola aplikasi. Bisa melihat data seluruh sekolah terdaftar, memonitoring siswa/nilai/mapel lintas sekolah, dan melakukan reset password akun sekolah.
2. **Kurikulum (Admin Sekolah):** Dibuat saat pendaftaran sekolah. Mengakses pengaturan sekolah, bank soal, administrasi ujian, import siswa, dan mapping ruang.
3. **Pengawas:** Role khusus untuk memonitoring siswa selama ujian berlangsung. Akun digenerate otomatis saat pembuatan ruang ujian.
4. **Siswa:** Hanya mengakses halaman pengerjaan soal ujian.

## 5. Core Features & User Stories

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

## 6. Coding Conventions & Rules for AI
- Gunakan React Functional Components & Hooks.
- Gunakan Laravel Eloquent API Resources untuk format response standar (data, message, status).
- Terapkan validasi Form (Form Request di Laravel).
- Setiap aksi modifikasi data (Create, Update, Delete) di Frontend harus memicu `react-hot-toast` jika berhasil/gagal.
- Aksi destruktif (Delete) wajib menampilkan `sweetalert2` confirmation box sebelum dieksekusi.