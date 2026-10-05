# SRS - Anfalm
**Versi:** 4.0
**Target:** SMK/MAK (dengan fleksibilitas untuk SMA/SMP/SD)
**Biaya:** Zero Cost
**Techstack:** Laravel + MySQL

---

## 1. PENDAHULUAN & KONSEP DASAR

### 1.1 Latar Belakang
Aplikasi tryout ini dikembangkan untuk menjawab kebutuhan siswa SMK/MAK (khususnya jurusan RPL) dalam mempersiapkan **Tes Kemampuan Akademik (TKA)** yang resmi dari pemerintah. Dengan adanya pembatasan waktu dan syarat tertentu di platform tryout komersial, serta minimnya fitur pembahasan, aplikasi ini hadir sebagai solusi **gratis, fleksibel, dan komprehensif**.

### 1.2 Konsep Dasar IRT (Item Response Theory)
Sistem penilaian menggunakan **Model Logistik 3 Parameter (3PL)** dengan rumus:

```
P(θ) = c + (1 - c) / (1 + e^(-a(θ - b)))
```

**Keterangan:**
- **θ (Theta):** Kemampuan peserta (rentang -3 sampai +3)
- **a (Daya Beda/Diskriminasi):** 0.5 - 2.5
- **b (Tingkat Kesulitan):** -3 sampai +3
- **c (Tebakan/Guessing):** 0 - 0.35

**Skala Pelaporan (dihitung berdasarkan `tingkat` akun user):**
- **SD/SMP/Sederajat:** 0 – 100
- **SMA/SMK/Sederajat:** 200 – 800

**Rumus Konversi Theta ke Skala:**
```
SD/SMP:   50 + 10 × θ   (di-clamp 0–100)
SMA/SMK:  500 + 100 × θ (di-clamp 200–800)
```
> Rumus ini bersifat final dan konsisten di seluruh dokumen (§7.4 juga menggunakan rumus ini; ada inkonsistensi pada §7.4 versi lama yang telah dikoreksi di bawah).
>
> **Mengapa 500 dan bukan 450:** pada `450 + 100 × θ` nilai 700 sudah tercapai pada θ = 2.5, sehingga seperempat rentang theta teratas — termasuk seluruh siswa yang benar-benar menguasai materi — duduk di satu titik yang sama. Dengan `500 + 100 × θ` ujung skala 200 dan 800 justru tercapai tepat pada batas theta ±3, jadi tidak ada rentang yang terbuang percuma.

### 1.3 Target Pengguna
- **Admin:** Guru/pembuat soal
- **Peserta:** Siswa (SMK/MAK, SMA/MA, SMP/MTs, SD/MI). Pendaftaran baru otomatis ber-role **peserta**; data profil (sekolah, tingkat, jurusan) dapat diisi/diubah dari halaman profil.

---

## 2. TUJUAN SISTEM

1. Menyediakan **tryout daring** dengan sistem penilaian IRT yang sesuai regulasi pemerintah.
2. Menyediakan **mode latihan** fleksibel untuk penguasaan per kompetensi.
3. Melacak **kompetensi siswa** per KD/materi berdasarkan estimasi kemampuan (theta).
4. Memberikan **pembahasan soal** yang kaya konten (teks, gambar, ekspresi matematika).
5. Menyediakan **leaderboard** per tryout untuk memotivasi peserta.
6. **Zero cost** untuk semua pengguna.
7. Melindungi konten soal dari kecurangan dengan proteksi dasar.

---

## 3. FITUR-FITUR WAJIB (DENGAN ALUR LENGKAP)

### 3.1 Manajemen Mapel (CRUD)

**Deskripsi:** Admin dapat mengelola daftar mata pelajaran yang akan digunakan dalam sistem.

**Alur:**
1. Admin login → Dashboard Admin.
2. Pilih menu "Mapel".
3. Lihat daftar mapel yang sudah ada (dengan filter berdasarkan tingkat).
4. **Tambah mapel baru:**
   - Isi kode (unik), nama, tingkat (`SD|SMP|SMA|SMK|all`), jenis (`wajib|pilihan_umum|pilihan_kejuruan`).
   - Centang **Proyek Kreatif & Kewirausahaan (PKK)** jika mapel adalah pilihan kejuruan berbasis proyek kewirausahaan.
   - Klik "Simpan".
5. **Edit mapel:** Klik ikon edit → Ubah data → Simpan.
6. **Hapus mapel:** Konfirmasi hapus.
   - Jika mapel **belum dipakai** (tidak ada soal/paket/tryout) → **soft delete** (`deleted_at`) dan tersembunyi dari daftar normal.
   - Jika mapel **sudah dipakai** (punya soal, paket soal, atau percobaan tryout) → **diblokir**; sistem menampilkan peringatan: *"Mapel masih digunakan oleh X soal / Y paket tryout — hapus permanen tidak diperbolehkan."* Hanya soft delete yang diperbolehkan; data terus tersimpan namun tidak tampil di antarmuka normal.

**Validasi:**
- Kode mapel harus unik (saat create & edit).
- Jenis & tingkat wajib diisi; `is_pkk` hanya relevan untuk jenis `pilihan_umum|pilihan_kejuruan` (diabaikan jika `wajib`).
- Hapus permanen diblokir jika mapel sudah dipakai; hanya soft delete yang diperbolehkan.

---

### 3.2 Manajemen Kompetensi Dasar (CRUD)

**Deskripsi:** Admin mengelola matriks asesmen per mapel, yang menjadi acuan pembuatan soal dan tracking kompetensi.

**Alur:**
1. Admin → "Kompetensi Dasar".
2. Pilih mapel dari dropdown.
3. Lihat daftar KD yang sudah ada.
4. **Tambah KD baru:**
   - Isi kode kompetensi (contoh: 3.1), deskripsi, materi pokok, dan level kognitif (pengetahuan/pemahaman/penerapan/penalaran).
   - Isi **batasan** (opsional): materi spesifik yang diujikan dalam KD tersebut.
   - Klik "Simpan".
5. **Edit/Hapus KD** sesuai kebutuhan.

**Catatan Penting:**
- KD adalah **sumber kebenaran (single source of truth)** untuk pembuatan soal.
- Setiap soal harus terikat ke satu KD.
- Batasan digunakan untuk mempersempit cakupan materi dalam KD (contoh: "KD 3.1 tentang bilangan berpangkat, batasan: hanya pangkat bulat positif").
- **Soft delete + blokir hapus permanen** juga berlaku untuk KD: jika KD sudah dipakai soal atau data tracking, hapus permanen diblokir; hanya soft delete yang diperbolehkan.

---

### 3.3 Manajemen Soal (CRUD Manual)

**Deskripsi:** Admin dapat membuat soal secara manual, satu per satu, dengan WYSIWYG editor untuk konten kaya.

**Alur Input Manual:**
1. Admin → "Soal" → "Tambah Soal Baru".
2. Pilih mapel → Pilih KD (dari matriks yang sudah ada).
3. Pilih tipe soal: PG / PG Kompleks / PG Kategori.
4. **Isi pertanyaan dengan WYSIWYG Editor:**
   - Mendukung teks format (bold, italic, underline, list).
   - Mendukung **embed gambar** (upload atau URL).
   - Mendukung **ekspresi matematika** dengan KaTeX (contoh: `\frac{2}{3}`, `\sqrt{x^2 + y^2}`).
   - Admin menuliskan kode KaTeX di dalam editor, dan akan dirender di preview.
5. **Upload gambar** (opsional): gambar pendukung untuk soal (diagram, grafik, ilustrasi).
   - Gambar akan dikompres otomatis ke WebP (maks 500 KB) di browser (Canvas API) dan disimpan di storage lokal aplikasi (`public` disk Laravel).
6. **Isi opsi jawaban** (minimal 5, maksimal 8) dengan WYSIWYG Editor yang sama.
   - Untuk **PG**: tentukan tepat **1** opsi `is_benar: true`.
   - Untuk **PG Kompleks**: tentukan **minimal 2** opsi `is_benar: true` (soal dengan jumlah benar ≤ 1 ditolak — lihat edge 6.15).
   - Untuk **PG Kategori**: tidak menggunakan opsi jawaban, tetapi pernyataan-kategori (**minimal 3, maksimal 5**) dengan WYSIWYG Editor yang sama; admin mendefinisikan daftar kategori (`daftar_kategori` JSON) di tingkat soal, lalu per-pernyataan dikelompokkan ke salah satu kategori tersebut.
7. **Isi pembahasan** dengan WYSIWYG Editor (support teks, gambar, KaTeX).
8. **Isi parameter IRT (a, b, c):**
   - Admin bisa input manual (berdasarkan pengalaman).
   - Atau gunakan nilai default: `a=1.0, b=0.0, c=0.25`.
   - Selama ketiganya masih memakai nilai default, form menampilkan peringatan *"Parameter IRT masih default, disarankan untuk dikurasi"* (edge 6.1).
9. Klik "Simpan".

---

### 3.4 Generate Paket Soal dari AI

**Deskripsi:** Admin dapat membuat satu paket soal utuh (terdiri dari banyak soal) untuk satu mapel tertentu hanya dengan memilih mapel dan KD. AI akan menghasilkan semua soal dalam satu paket sekaligus.

**Alur:**
1. Admin → "Paket Soal" → "Generate Paket dari AI".
2. Pilih mapel (dari dropdown yang sudah terdaftar).
3. Pilih satu atau beberapa KD yang ingin dijadikan acuan (bisa pilih semua KD untuk satu mapel).
4. Masukkan parameter tambahan (opsional):
   - Jumlah soal total (maksimal 30 soal per generate).
   - Tingkat kesulitan yang diinginkan (mudah/sedang/sulit/campuran).
   - Referensi tambahan (upload PDF/gambar/teks) sebagai bahan acuan.
5. Klik "Generate Paket".
6. Sistem mengirimkan prompt ke **Google Gemini Flash** dengan struktur yang sudah ditentukan (lihat bagian 7.1 untuk detail prompt).
7. AI mengembalikan data semua soal dalam satu paket dalam format JSON (lihat bagian 7.2 untuk skema JSON).
8. **Admin wajib mengkurasi seluruh soal:**
   - Periksa setiap soal (pertanyaan, opsi, jawaban, pembahasan) yang sudah dirender dengan WYSIWYG + KaTeX.
   - Periksa dan sesuaikan parameter IRT (a,b,c) jika diperlukan.
   - Hapus soal yang tidak sesuai, tambahkan soal baru jika perlu.
   - Edit langsung di form kurasi.
9. Setelah semua soal dikurasi, klik "Simpan Paket".
10. Sistem menyimpan:
    - Semua soal ke tabel `soal` (dengan parameter IRT yang sudah dikurasi).
    - Membuat paket soal baru di tabel `paket_soal` yang berisi semua soal tersebut.
    - Admin bisa langsung menggunakan paket ini untuk tryout atau latihan.

**Keunggulan:**
- Jauh lebih praktis daripada generate satu per satu.
- Satu paket siap pakai untuk tryout/latihan.
- AI menghasilkan soal yang terstruktur dengan parameter IRT.
- Admin hanya perlu kurasi sekali untuk seluruh paket.

---

### 3.5 Manajemen Paket Soal (CRUD)

**Deskripsi:** Admin mengelompokkan soal-soal ke dalam paket yang nantinya digunakan untuk tryout.

**Alur:**
1. Admin → "Paket Soal" → "Buat Paket Baru".
2. Isi nama paket dan deskripsi.
3. Pilih soal-soal dari database (filter berdasarkan mapel/KD).
4. Tentukan jumlah soal per mapel (misal 20 soal/mapel).
5. Sistem menambahkan soal ke `detail_paket_soal`.
6. Klik "Simpan".

**Catatan:** Paket soal yang dihasilkan dari AI (fitur 3.4) otomatis masuk ke daftar paket soal.

---

### 3.6 Manajemen Paket Tryout (CRUD)

**Deskripsi:** Admin membuat paket tryout yang terdiri dari **3 mapel wajib + 2 mapel pilihan** (khusus SMK).

**Alur:**
1. Admin → "Paket Tryout" → "Buat Tryout Baru".
2. Isi metadata: Nama paket, tingkat (`SMK|SMA|SMP|SD`), **batas waktu pengerjaan (menit)** — wajib diisi; default 120 menit jika dikosongkan.
3. Pilih 3 mapel wajib (dari dropdown mapel yang berjenis `wajib`).
4. Pilih 2 mapel pilihan:
   - **Untuk SMK:** Minimal **salah satu** dari kedua mapel pilihan harus berjenis `pilihan_kejuruan` **atau** bertanda `is_pkk = true`. Keduanya boleh kejuruan sekaligus (jarang, tapi sah). Berlaku saat **create** maupun **edit**.
   - **Untuk SMA/Sederajat:** Bebas memilih 2 mapel pilihan apa saja (tidak wajib kejuruan/PKK).
5. Untuk setiap mapel:
   - Pilih paket soal yang sudah ada (dari fitur 3.4 atau 3.5).
   - Atau buat paket soal baru langsung dari halaman ini (manual atau generate AI).
6. Klik "Simpan".

**Validasi:**
- `batas_waktu_menit` wajib diisi (positif, default 120 jika dikosongkan).
- Mapel pilihan tidak boleh sama dengan mapel wajib, dan tidak boleh sama satu sama lain.
- Minimal 1 soal per mapel.
- **Untuk tingkat SMK:** minimal satu mapel pilihan ber-`jenis = pilihan_kejuruan` **atau** ber-`is_pkk = true`; keduanya tidak dipaksa berbeda (boleh sama-sama kejuruan).
- **Retake:** Satu peserta hanya boleh **satu percobaan** per paket tryout (`UNIQUE(user_id, paket_tryout_id)` pada tabel `hasil_tryout`). Admin dapat mereset percobaan pada kasus khusus (edge 6.16).

---

### 3.7 Mode Tryout (Peserta)

**Deskripsi:** Peserta mengerjakan tryout sesuai paket yang dipilih. Urutan soal **diacak** dan peserta **bisa navigasi antar soal** (tidak harus berurutan).

**Alur:**
1. Peserta login → Dashboard Siswa.
2. Pilih "Tryout" → Lihat daftar paket tryout yang tersedia.
3. Pilih satu paket → Klik "Mulai Tryout".
4. Sistem menampilkan 5 mapel (3 wajib + 2 pilihan) secara berurutan:
   - Untuk setiap mapel, peserta mengerjakan soal-soal di dalamnya.
   - **Navigasi:** Peserta bisa pindah ke soal sebelumnya/berikutnya menggunakan tombol navigasi.
   - **Status:** Soal yang sudah dijawab ditandai (misal: hijau = sudah, merah = belum).
   - **Konten Soal:** Pertanyaan, opsi jawaban, dan gambar dirender dengan WYSIWYG + KaTeX.
5. Peserta menavigasi soal di dalam mapel (bisa bolak-balik antar soal, tidak wajib berurutan). Soal yang sudah dijawab ditandai (hijau), belum (merah).
6. Setelah selesai semua soal di satu mapel → Lanjut ke mapel berikutnya. **Tidak bisa kembali** ke mapel sebelumnya.
7. Saat mencapai mapel terakhir atau tombol "Selesai", sistem menghitung skor dan menyimpan ke `hasil_tryout`.
8. Jika waktu pengerjaan habis (`batas_waktu_menit`), tryout **otomatis dikumpulkan** (auto-submit) — skor dihitung dari jawaban yang sudah ada; sisa soal tidak dijawab diabaikan dari estimasi.
9. Sistem menghitung per-item:
   - **PG** → 1 item (opsi yang dipilih; `is_benar` dijadikan response 1/0).
   - **PG Kompleks** → 1 item per opsi; hanya opsi yang dipilih dihitung; opsi lain diabaikan (edge 6.2).
   - **PG Kategori** → 1 item per pernyataan; jawaban kosong pada pernyataan tertentu diabaikan (edge 6.2).
10. Estimasi theta dihitung dari seluruh item dengan jawaban (`response != NULL`) menggunakan MLE (atau MLE + prior lemah bila item sedikit per KD).
11. Theta dikonversi ke skala pelaporan sesuai tingkat akun (§1.2) dan disimpan bersama jumlah benar/salah/total_soal, serta `standard_error`.
12. Peserta melihat hasil: Skor IRT (angka), jumlah benar/salah, **pembahasan per soal** (dirender dengan WYSIWYG + KaTeX), dan rekomendasi level kompetensi per KD (§7.5).

**Catatan:**
- Peserta tidak bisa kembali ke mapel sebelumnya setelah selesai.
- Semua paket tryout memiliki batas waktu wajib; tryout yang kehabisan waktu dikumpulkan secara otomatis.
- **Retake:** Percobaan ulang hanya dimungkinkan dengan reset oleh admin (§6.16).

---

### 3.8 Mode Latihan (Peserta)

**Deskripsi:** Peserta bisa berlatih dengan fleksibilitas tinggi: pilih mapel, jumlah soal, dan timer.

**Alur:**
1. Peserta → "Latihan".
2. Pilih mapel (dari dropdown).
3. Pilih jumlah soal (5, 10, 15, 20, atau custom, maksimal 30).
4. Pilih timer:
   - **Stopwatch:** Mencatat durasi pengerjaan (default).
   - **Countdown:** Atur batas waktu (misal: 10 menit).
5. Klik "Mulai Latihan".
6. Sistem mengambil soal acak dari database (filter mapel yang dipilih, dan jika ada filter KD tertentu).
7. Peserta mengerjakan soal:
   - Bisa navigasi antar soal.
   - Timer berjalan (stopwatch atau countdown).
   - Konten soal dirender dengan WYSIWYG + KaTeX.
8. Setelah selesai (atau waktu habis) → Klik "Selesai".
9. Sistem menampilkan:
   - Jumlah benar/salah.
   - Estimasi theta per KD (update ke `tracking_kompetensi`).
   - **Pembahasan per soal** (dirender dengan WYSIWYG + KaTeX).

---

### 3.9 Tracking Kompetensi (Siswa)

**Deskripsi:** Siswa dapat melihat kekuatan dan kelemahan mereka per KD dan per mapel berdasarkan data pengerjaan tryout & latihan.

**Alur:**
1. Peserta → "Analisis Kompetensi".
2. Pilih mapel (dari dropdown) → Lihat data per KD:
   - **Nama KD** (deskripsi saja — kode KD adalah identitas internal dan tidak ditampilkan ke peserta, sesuai laporan "stop info dump").
   - **Level Kompetensi:**
     - Mahir: theta ≥ 1.5
     - Menengah: 0.5 ≤ theta < 1.5
     - Dasar: -0.5 ≤ theta < 0.5
     - Perlu Bimbingan: -1.5 ≤ theta < -0.5
     - Belum Teridentifikasi: theta < -1.5
   - **Persentase Benar** (dari total soal yang dikerjakan di KD tersebut).
   - **Theta Estimasi** (angka dengan 3 desimal).
   - **Rekomendasi:** "Fokus latihan KD ini karena masih Perlu Bimbingan."
3. Tampilkan grafik:
   - **Radar chart:** Perbandingan theta per mapel.
   - **Bar chart:** Perbandingan level per KD dalam satu mapel.
4. Lihat riwayat nilai tryout dalam grafik garis (perkembangan theta dari waktu ke waktu).

---

### 3.10 Leaderboard (Per Tryout)

**Deskripsi:** Menampilkan peringkat peserta berdasarkan skor IRT dalam satu tryout tertentu.

**Alur:**
1. Peserta/Admin → "Tryout" → Pilih paket tryout.
2. Klik "Leaderboard".
3. Sistem menampilkan daftar peserta yang sudah menyelesaikan tryout tersebut, diurutkan dari skor IRT tertinggi ke terendah.
4. Kolom: Peringkat, Nama Peserta, Skor IRT, Jumlah Benar, Durasi.
5. Hanya menampilkan peserta yang sudah selesai (tidak termasuk yang sedang mengerjakan).

**Penanganan Skor Sama:**
- Jika skor IRT sama, peserta dengan durasi pengerjaan lebih cepat mendapatkan peringkat lebih tinggi.
- Jika durasi juga sama, peringkat berdasarkan waktu selesai (yang lebih dulu selesai lebih tinggi).

---

## 4. WYSIWYG EDITOR & KATEX

### 4.1 Deskripsi
Untuk mendukung konten yang kaya (teks format, gambar, dan ekspresi matematika), sistem menggunakan:
- **WYSIWYG Editor:** Untuk input konten (soal, opsi, pembahasan) oleh admin.
- **KaTeX:** Untuk rendering ekspresi matematika di sisi peserta dan admin (preview).

### 4.2 Editor yang Digunakan
**TipTap** (ringan, berbasis ProseMirror) dengan extension:
- Bold, Italic, Underline, Strike
- Ordered/Unordered List
- Blockquote
- Code block
- Image (upload dari lokal atau URL)
- **KaTeX extension** untuk input dan render matematika

**Alternatif:** Quill.js atau TinyMCE, namun TipTap lebih ringan dan mudah dikustomisasi.

### 4.3 Cara Penggunaan KaTeX di Editor
1. Admin mengetikkan ekspresi matematika di dalam editor dengan format:
   - Inline: `\( ... \)` (contoh: `\( \frac{2}{3} \)`)
   - Display: `\[ ... \]` (contoh: `\[ \int_0^1 x^2 dx \]`)
2. Editor akan menampilkan **preview real-time** dari ekspresi tersebut.
3. Saat disimpan, konten disimpan sebagai HTML dengan tag khusus untuk KaTeX.
4. Saat ditampilkan ke peserta, sistem akan merender menggunakan KaTeX.

### 4.4 Validasi Konten
- Setiap input dari WYSIWYG editor **disanitasi** untuk mencegah XSS (Cross-Site Scripting).
- Hanya tag HTML yang diizinkan (dari TipTap) yang akan dipertahankan.
- Ekspresi KaTeX divalidasi agar tidak mengandung kode berbahaya.

---

## 5. SKEMA DATABASE (MySQL FINAL)

Potret aktual tabel MySQL yang dibangun oleh migrasi Laravel di
`database/migrations/`. DDL di bawah dihasilkan dari basis data pengembangan
lewat `mariadb-dump --no-data`, sehingga mengikuti skema yang benar-benar
dijalankan — bukan sketsa yang berjalan sendiri dari implementasinya. Tabel
infrastruktur bawaan Laravel (`cache`, `cache_locks`, `sessions`, `jobs`,
`job_batches`, `failed_jobs`, `migrations`, `password_reset_tokens`) tidak
dicantumkan karena tidak menyimpan data domain.

```sql
CREATE TABLE users (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  nama_lengkap varchar(100) NOT NULL,
  email varchar(255) NOT NULL,
  email_verified_at timestamp NULL DEFAULT NULL,
  password varchar(255) NOT NULL,
  sekolah varchar(100) DEFAULT NULL,
  tingkat enum('SD','SMP','SMA','SMK') DEFAULT NULL,
  jurusan varchar(50) DEFAULT NULL,
  role enum('admin','peserta') NOT NULL DEFAULT 'peserta',
  session_token varchar(64) DEFAULT NULL,
  remember_token varchar(100) DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB;

CREATE TABLE mapel (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  kode varchar(20) NOT NULL,
  kode_unik varchar(20) GENERATED ALWAYS AS (if(deleted_at is null,kode,NULL)) STORED,
  nama varchar(100) NOT NULL,
  tingkat enum('SD','SMP','SMA','SMK','all') NOT NULL DEFAULT 'all',
  jenis enum('wajib','pilihan_umum','pilihan_kejuruan') NOT NULL,
  is_pkk tinyint(1) NOT NULL DEFAULT 0,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  deleted_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY mapel_kode_unik_unique (kode_unik)
) ENGINE=InnoDB;

CREATE TABLE kompetensi_dasar (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  mapel_id bigint(20) unsigned NOT NULL,
  kode_kompetensi varchar(50) NOT NULL,
  kode_unik varchar(50) GENERATED ALWAYS AS (if(deleted_at is null,kode_kompetensi,NULL)) STORED,
  deskripsi text NOT NULL,
  materi_pokok varchar(255) DEFAULT NULL,
  level_kognitif enum('pengetahuan_dan_pemahaman','penerapan','penalaran') NOT NULL,
  batasan text DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  deleted_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY kompetensi_dasar_mapel_id_kode_unik_unique (mapel_id,kode_unik),
  CONSTRAINT kompetensi_dasar_mapel_id_foreign FOREIGN KEY (mapel_id) REFERENCES mapel (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE soal (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  kompetensi_dasar_id bigint(20) unsigned NOT NULL,
  tipe_soal enum('pg','pg_kompleks','pg_kategori') NOT NULL,
  pertanyaan text NOT NULL,
  gambar_url varchar(255) DEFAULT NULL,
  pembahasan text DEFAULT NULL,
  daftar_kategori longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(daftar_kategori)),
  a_diskriminasi decimal(5,3) NOT NULL DEFAULT 1.000,
  b_kesulitan decimal(5,3) NOT NULL DEFAULT 0.000,
  c_tebakan decimal(5,3) NOT NULL DEFAULT 0.250,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  deleted_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY soal_created_by_foreign (created_by),
  KEY soal_kompetensi_dasar_id_index (kompetensi_dasar_id),
  CONSTRAINT soal_created_by_foreign FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT soal_kompetensi_dasar_id_foreign FOREIGN KEY (kompetensi_dasar_id) REFERENCES kompetensi_dasar (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE opsi_jawaban (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  soal_id bigint(20) unsigned NOT NULL,
  teks_opsi text NOT NULL,
  is_benar tinyint(1) NOT NULL DEFAULT 0,
  urutan int(11) DEFAULT NULL,
  a_diskriminasi decimal(5,3) DEFAULT NULL,
  b_kesulitan decimal(5,3) DEFAULT NULL,
  c_tebakan decimal(5,3) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY opsi_jawaban_soal_id_index (soal_id),
  CONSTRAINT opsi_jawaban_soal_id_foreign FOREIGN KEY (soal_id) REFERENCES soal (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pernyataan_kategori (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  soal_id bigint(20) unsigned NOT NULL,
  teks_pernyataan text NOT NULL,
  kategori_benar varchar(50) NOT NULL,
  a_diskriminasi decimal(5,3) DEFAULT NULL,
  b_kesulitan decimal(5,3) DEFAULT NULL,
  c_tebakan decimal(5,3) DEFAULT NULL,
  urutan int(11) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY pernyataan_kategori_soal_id_index (soal_id),
  CONSTRAINT pernyataan_kategori_soal_id_foreign FOREIGN KEY (soal_id) REFERENCES soal (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE paket_soal (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  nama_paket varchar(255) NOT NULL,
  deskripsi text DEFAULT NULL,
  mapel_id bigint(20) unsigned NOT NULL,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  deleted_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY paket_soal_created_by_foreign (created_by),
  KEY paket_soal_mapel_id_index (mapel_id),
  CONSTRAINT paket_soal_created_by_foreign FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT paket_soal_mapel_id_foreign FOREIGN KEY (mapel_id) REFERENCES mapel (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE detail_paket_soal (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  paket_soal_id bigint(20) unsigned NOT NULL,
  soal_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY detail_paket_soal_paket_soal_id_soal_id_unique (paket_soal_id,soal_id),
  KEY detail_paket_soal_soal_id_index (soal_id),
  CONSTRAINT detail_paket_soal_paket_soal_id_foreign FOREIGN KEY (paket_soal_id) REFERENCES paket_soal (id) ON DELETE CASCADE,
  CONSTRAINT detail_paket_soal_soal_id_foreign FOREIGN KEY (soal_id) REFERENCES soal (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE paket_tryout (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  nama_paket varchar(255) NOT NULL,
  deskripsi text DEFAULT NULL,
  tingkat enum('SD','SMP','SMA','SMK') NOT NULL DEFAULT 'SMK',
  batas_waktu_menit int(11) NOT NULL,
  mapel_wajib_1 bigint(20) unsigned DEFAULT NULL,
  mapel_wajib_2 bigint(20) unsigned DEFAULT NULL,
  mapel_wajib_3 bigint(20) unsigned DEFAULT NULL,
  mapel_pilihan_1 bigint(20) unsigned DEFAULT NULL,
  mapel_pilihan_2 bigint(20) unsigned DEFAULT NULL,
  paket_soal_wajib_1_id bigint(20) unsigned DEFAULT NULL,
  paket_soal_wajib_2_id bigint(20) unsigned DEFAULT NULL,
  paket_soal_wajib_3_id bigint(20) unsigned DEFAULT NULL,
  paket_soal_pilihan_1_id bigint(20) unsigned DEFAULT NULL,
  paket_soal_pilihan_2_id bigint(20) unsigned DEFAULT NULL,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  deleted_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY paket_tryout_mapel_wajib_1_foreign (mapel_wajib_1),
  KEY paket_tryout_mapel_wajib_2_foreign (mapel_wajib_2),
  KEY paket_tryout_mapel_wajib_3_foreign (mapel_wajib_3),
  KEY paket_tryout_mapel_pilihan_1_foreign (mapel_pilihan_1),
  KEY paket_tryout_mapel_pilihan_2_foreign (mapel_pilihan_2),
  KEY paket_tryout_paket_soal_wajib_1_id_foreign (paket_soal_wajib_1_id),
  KEY paket_tryout_paket_soal_wajib_2_id_foreign (paket_soal_wajib_2_id),
  KEY paket_tryout_paket_soal_wajib_3_id_foreign (paket_soal_wajib_3_id),
  KEY paket_tryout_paket_soal_pilihan_1_id_foreign (paket_soal_pilihan_1_id),
  KEY paket_tryout_paket_soal_pilihan_2_id_foreign (paket_soal_pilihan_2_id),
  KEY paket_tryout_created_by_foreign (created_by),
  CONSTRAINT paket_tryout_created_by_foreign FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT paket_tryout_mapel_pilihan_1_foreign FOREIGN KEY (mapel_pilihan_1) REFERENCES mapel (id) ON DELETE SET NULL,
  CONSTRAINT paket_tryout_mapel_pilihan_2_foreign FOREIGN KEY (mapel_pilihan_2) REFERENCES mapel (id) ON DELETE SET NULL,
  CONSTRAINT paket_tryout_mapel_wajib_1_foreign FOREIGN KEY (mapel_wajib_1) REFERENCES mapel (id) ON DELETE SET NULL,
  CONSTRAINT paket_tryout_mapel_wajib_2_foreign FOREIGN KEY (mapel_wajib_2) REFERENCES mapel (id) ON DELETE SET NULL,
  CONSTRAINT paket_tryout_mapel_wajib_3_foreign FOREIGN KEY (mapel_wajib_3) REFERENCES mapel (id) ON DELETE SET NULL,
  CONSTRAINT paket_tryout_paket_soal_pilihan_1_id_foreign FOREIGN KEY (paket_soal_pilihan_1_id) REFERENCES paket_soal (id) ON DELETE SET NULL,
  CONSTRAINT paket_tryout_paket_soal_pilihan_2_id_foreign FOREIGN KEY (paket_soal_pilihan_2_id) REFERENCES paket_soal (id) ON DELETE SET NULL,
  CONSTRAINT paket_tryout_paket_soal_wajib_1_id_foreign FOREIGN KEY (paket_soal_wajib_1_id) REFERENCES paket_soal (id) ON DELETE SET NULL,
  CONSTRAINT paket_tryout_paket_soal_wajib_2_id_foreign FOREIGN KEY (paket_soal_wajib_2_id) REFERENCES paket_soal (id) ON DELETE SET NULL,
  CONSTRAINT paket_tryout_paket_soal_wajib_3_id_foreign FOREIGN KEY (paket_soal_wajib_3_id) REFERENCES paket_soal (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE percobaan (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  jenis enum('tryout','latihan') NOT NULL,
  paket_tryout_id bigint(20) unsigned DEFAULT NULL,
  mapel_id bigint(20) unsigned DEFAULT NULL,
  jumlah_soal int(11) DEFAULT NULL,
  batas_waktu_menit int(11) DEFAULT NULL,
  status enum('berjalan','selesai','dibatalkan') NOT NULL DEFAULT 'berjalan',
  daftar_soal longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(daftar_soal)),
  posisi_soal int(11) NOT NULL DEFAULT 0,
  urutan_mapel int(11) NOT NULL DEFAULT 0,
  waktu_mulai timestamp NULL DEFAULT NULL,
  waktu_selesai timestamp NULL DEFAULT NULL,
  durasi_detik int(11) DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY percobaan_mapel_id_foreign (mapel_id),
  KEY percobaan_user_id_index (user_id),
  KEY percobaan_user_id_status_index (user_id,status),
  KEY percobaan_paket_tryout_id_index (paket_tryout_id),
  CONSTRAINT percobaan_mapel_id_foreign FOREIGN KEY (mapel_id) REFERENCES mapel (id) ON DELETE SET NULL,
  CONSTRAINT percobaan_paket_tryout_id_foreign FOREIGN KEY (paket_tryout_id) REFERENCES paket_tryout (id) ON DELETE SET NULL,
  CONSTRAINT percobaan_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE riwayat_pengerjaan (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  percobaan_id bigint(20) unsigned NOT NULL,
  soal_id bigint(20) unsigned NOT NULL,
  paket_tryout_id bigint(20) unsigned DEFAULT NULL,
  jawaban_user longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(jawaban_user)),
  is_benar tinyint(1) NOT NULL DEFAULT 0,
  mode enum('tryout','latihan') NOT NULL,
  waktu_mulai timestamp NULL DEFAULT NULL,
  waktu_selesai timestamp NULL DEFAULT NULL,
  durasi_detik int(11) DEFAULT NULL,
  theta_est_moment decimal(5,3) DEFAULT NULL,
  skor_irt decimal(5,3) DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY riwayat_pengerjaan_user_id_index (user_id),
  KEY riwayat_pengerjaan_percobaan_id_index (percobaan_id),
  KEY riwayat_pengerjaan_soal_id_index (soal_id),
  KEY riwayat_pengerjaan_paket_tryout_id_index (paket_tryout_id),
  CONSTRAINT riwayat_pengerjaan_paket_tryout_id_foreign FOREIGN KEY (paket_tryout_id) REFERENCES paket_tryout (id) ON DELETE SET NULL,
  CONSTRAINT riwayat_pengerjaan_percobaan_id_foreign FOREIGN KEY (percobaan_id) REFERENCES percobaan (id) ON DELETE CASCADE,
  CONSTRAINT riwayat_pengerjaan_soal_id_foreign FOREIGN KEY (soal_id) REFERENCES soal (id) ON DELETE CASCADE,
  CONSTRAINT riwayat_pengerjaan_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE hasil_tryout (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  paket_tryout_id bigint(20) unsigned NOT NULL,
  theta_final decimal(5,3) NOT NULL,
  standard_error decimal(5,3) DEFAULT NULL,
  skor_irt_total decimal(7,3) NOT NULL,
  skor_konversi int(11) DEFAULT NULL,
  jumlah_benar int(11) DEFAULT NULL,
  jumlah_salah int(11) DEFAULT NULL,
  total_soal int(11) DEFAULT NULL,
  durasi_total int(11) DEFAULT NULL,
  selesai_pada timestamp NOT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY hasil_tryout_user_id_paket_tryout_id_unique (user_id,paket_tryout_id),
  KEY hasil_tryout_user_id_index (user_id),
  KEY hasil_tryout_paket_tryout_id_index (paket_tryout_id),
  CONSTRAINT hasil_tryout_paket_tryout_id_foreign FOREIGN KEY (paket_tryout_id) REFERENCES paket_tryout (id) ON DELETE CASCADE,
  CONSTRAINT hasil_tryout_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tracking_kompetensi (
  user_id bigint(20) unsigned NOT NULL,
  kompetensi_dasar_id bigint(20) unsigned NOT NULL,
  total_soal_dikerjakan int(11) NOT NULL DEFAULT 0,
  total_benar int(11) NOT NULL DEFAULT 0,
  persentase_benar decimal(5,2) NOT NULL DEFAULT 0.00,
  theta_estimasi decimal(5,3) DEFAULT NULL,
  theta_se decimal(5,3) DEFAULT NULL,
  last_updated timestamp NULL DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (user_id,kompetensi_dasar_id),
  KEY tracking_kompetensi_kompetensi_dasar_id_index (kompetensi_dasar_id),
  CONSTRAINT tracking_kompetensi_kompetensi_dasar_id_foreign FOREIGN KEY (kompetensi_dasar_id) REFERENCES kompetensi_dasar (id) ON DELETE CASCADE,
  CONSTRAINT tracking_kompetensi_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tracking_mapel (
  user_id bigint(20) unsigned NOT NULL,
  mapel_id bigint(20) unsigned NOT NULL,
  theta_estimasi decimal(5,3) DEFAULT NULL,
  level_kompetensi varchar(30) DEFAULT NULL,
  total_tryout_diikuti int(11) NOT NULL DEFAULT 0,
  rata_rata_skor_irt decimal(5,3) DEFAULT NULL,
  last_updated timestamp NULL DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (user_id,mapel_id),
  KEY tracking_mapel_mapel_id_index (mapel_id),
  CONSTRAINT tracking_mapel_mapel_id_foreign FOREIGN KEY (mapel_id) REFERENCES mapel (id) ON DELETE CASCADE,
  CONSTRAINT tracking_mapel_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

### 5.1 Catatan Implementasi (Laravel + MySQL)
Implementasi aktual menggunakan **Laravel 13 + MySQL/MariaDB** dengan pendekatan TDD (Pest). Catatan di bawah melengkapi DDL di atas — hal-hal yang tidak terbaca dari struktur tabel saja:

- **Primary Key:** BIGINT auto-increment pada seluruh tabel domain.
- **Autentikasi:** Menggunakan Laravel auth (`auth:web`); tabel `users` memuat langsung profil dasar (`nama_lengkap`, `sekolah`, `tingkat`, `jurusan`, `role`, `session_token`).
- **Soft Delete + Blokir Hapus Permanen:** Kolom `deleted_at` pada `mapel`, `kompetensi_dasar`, `soal`, `paket_soal`, dan `paket_tryout`. Hapus permanen diblokir jika data sudah memiliki dependensi.
- **Mapel:** Menambahkan kolom `is_pkk` (boolean, default `false`) untuk identifikasi Proyek Kreatif & Kewirausahaan (digunakan dalam validasi aturan SMK §3.6).
- **Soal:** Kolom `daftar_kategori` (JSON) menyimpan daftar kategori yang tersedia untuk tipe `pg_kategori` di tingkat soal (bukan per-paket).
- **Parameter IRT per-opsi/pernyataan:** Kolom `a_diskriminasi`, `b_kesulitan`, `c_tebakan` pada `opsi_jawaban` dan `pernyataan_kategori` bersifat **nullable** — jika NULL, fallback ke parameter default (a=1.0, b=0.0, c=0.25) di tingkat soal (edge 6.1).
- **Paket Tryout:** Menambahkan `batas_waktu_menit` (wajib, default 120) dan `created_by` (admin yang membuat).
- **Sesi Pengerjaan:** Tabel `percobaan` mencatat status (berjalan/selesai/dibatalkan), posisi soal, dan urutan mapel untuk mendukung fitur resume (§6.4).
- **Riwayat Pengerjaan:** `jawaban_user` (JSON) menyimpan jawaban mentah; `is_benar` (boolean) menyimpan snapshot hasil skoring per-soal saat pengerjaan selesai — edit soal tidak mempengaruhi riwayat yang sudah ada.
- **Hasil Tryout:** `UNIQUE(user_id, paket_tryout_id)` menjamin **satu percobaan per user per paket tryout**; reset percobaan oleh admin = menghapus baris hasil lama (+ riwayat/percobaan terkait).
- **Tracking Kompetensi & Mapel:** Kolom `theta_estimasi` bersifat **nullable** — `NULL` berarti "belum teridentifikasi" (§7.5), bukan 0.
- **Session:** Menggunakan `SESSION_DRIVER=database` (tabel `sessions`); satu-sesi-per-akun diimplementasikan via kolom `users.session_token` + middleware kustom.

> Seluruh migrasi Laravel tersedia di `database/migrations/`. Jalankan `php artisan migrate:fresh` untuk membangun ulang skema dari awal.

### 5.2 Indeks untuk Pola Query Utama

Diverifikasi lewat `EXPLAIN` pada basis data pengembangan — seluruh pola query
utama memakai indeksnya, tanpa full scan:

| Pola query | Tipe akses | Indeks yang terpakai |
|---|---|---|
| Soal per KD | `ref` | `soal_kompetensi_dasar_id_index` |
| Riwayat per user | `ref` | `riwayat_pengerjaan_user_id_index` |
| Riwayat per percobaan | `ref` | `riwayat_pengerjaan_percobaan_id_index` |
| Hasil per user | `ref` | `hasil_tryout_user_id_paket_tryout_id_unique` |
| Tracking per KD | `ref` | PRIMARY `(user_id, kompetensi_dasar_id)` |
| Tracking per mapel | `ref` | PRIMARY `(user_id, mapel_id)` |
| Opsi per soal (eager load) | `range` | `opsi_jawaban_soal_id_index` |
| Detail per paket soal | `ref` | `detail_paket_soal_paket_soal_id_soal_id_unique` (index-only) |

`UNIQUE(user_id, paket_tryout_id)` pada `hasil_tryout` sekaligus berfungsi
sebagai indeks lookup per user karena `user_id` berada di urutan pertama — sama
seperti PRIMARY KEY komposit pada kedua tabel `tracking_*`.

Satu-satunya query yang masih `Using filesort` adalah peringkat leaderboard
(§3.10): `WHERE paket_tryout_id = ? ORDER BY skor_konversi DESC, durasi_total,
selesai_pada`. Indeks `hasil_tryout.paket_tryout_id` sudah dipakai untuk
menyaring, lalu MySQL mengurutkan hasilnya di memori. Karena jumlah baris per
paket tryout dibatasi oleh peserta yang menyelesaikan paket itu, filesort
tersebut dibiarkan — indeks komposit dengan arah campuran
(`skor_konversi DESC, durasi_total ASC, selesai_pada ASC`) hanya menambah biaya
tulis tanpa berarti pada skala ini.

---

## 6. PENANGANAN EDGE CASES

### 6.1 Soal Tanpa Parameter IRT
- **Kasus:** Admin lupa mengisi parameter IRT saat input manual, atau AI gagal menghasilkan parameter.
- **Penanganan:** Sistem memberikan nilai default: `a=1.0, b=0.0, c=0.25` dan menampilkan peringatan "Parameter IRT masih default, disarankan untuk dikurasi".

### 6.2 Peserta dengan Jawaban Kosong (Tidak Menjawab)
- **Kasus:** Peserta melewati soal tanpa memilih jawaban.
- **Penanganan:** Jawaban dianggap `null` → tidak dihitung dalam estimasi theta (soal tersebut diabaikan).

### 6.3 Estimasi Theta Gagal (MLE Tidak Konvergen)
- **Kasus:** Peserta menjawab semua soal benar atau semua soal salah, sehingga MLE tidak bisa mengestimasi theta (konvergensi gagal).
- **Penanganan:** Sistem memberi nilai theta ekstrem:
  - Semua benar → `theta = 3.0`
  - Semua salah → `theta = -3.0`

### 6.4 Tryout Belum Selesai (Keluar Tengah Jalan)
- **Kasus:** Peserta menutup browser atau keluar sebelum menyelesaikan tryout.
- **Penanganan:** 
  - Sistem menyimpan progres (jawaban yang sudah diisi) di `riwayat_pengerjaan` dengan `waktu_selesai = NULL`.
  - Saat peserta kembali, tawarkan "Lanjutkan Tryout" atau "Mulai Ulang".
  - Jika "Mulai Ulang" → reset semua jawaban dan mulai dari awal.

### 6.5 Dua Peserta Menggunakan Akun yang Sama
- **Kasus:** Satu akun digunakan oleh lebih dari satu orang (berbagi login).
- **Penanganan:** 
  - Batasi sesi aktif: hanya 1 sesi login per akun, diimplementasikan via kolom `users.session_token` + middleware `EnsureSingleSession` (bandingkan nilai token di session dengan nilai token di database; jika tidak cocok, paksa logout dan alihkan ke halaman login).
  - Jika login dari perangkat lain, sesi sebelumnya otomatis logout pada request berikutnya.

### 6.6 Upload Gambar Gagal atau Terlalu Besar
- **Kasus:** Admin upload gambar > 5 MB atau format tidak didukung.
- **Penanganan:**
  - Kompres otomatis ke WebP (maks 500 KB) di browser pakai Canvas API (`toBlob('image/webp')`).
  - Validasi ekstensi di sisi server: hanya JPG, PNG, WebP yang diizinkan.
  - Jika masih gagal, tampilkan pesan error dan minta upload ulang.

### 6.7 AI Generate Paket Soal Gagal (API Error/Timeout)
- **Kasus:** Google Gemini API down atau timeout saat generate paket soal.
- **Penanganan:**
  - Tampilkan pesan error ke admin.
  - Simpan prompt yang sudah dibuat → admin bisa coba lagi nanti.
  - Fallback: admin input manual (satu per satu) atau upload file.

### 6.8 Skor IRT di Luar Rentang Skala
- **Kasus:** Theta > 3 atau < -3 menghasilkan skor konversi di luar rentang 0-100 atau 200-800.
- **Penanganan:** Clamping (potong) ke batas minimum/maksimum skala.

### 6.9 Peserta Mengakses Leaderboard Sebelum Selesai
- **Kasus:** Peserta penasaran dengan peringkat sebelum menyelesaikan tryout.
- **Penanganan:** Leaderboard hanya menampilkan peserta yang sudah selesai. Peserta yang belum selesai tidak muncul.

### 6.10 Paket Tryout Kosong (Tidak Ada Soal)
- **Kasus:** Admin membuat paket tryout tapi lupa menambahkan soal.
- **Penanganan:** Validasi saat pembuatan: minimal 1 soal per mapel. Jika masih kosong, sistem menolak dan menampilkan peringatan.

### 6.11 AI Generate Paket dengan Jumlah Soal Terlalu Banyak
- **Kasus:** Admin meminta 50+ soal dalam satu generate, melebihi batas token AI.
- **Penanganan:** 
  - Batasi maksimal 30 soal per generate.
  - Jika admin meminta lebih, tampilkan peringatan dan sarankan generate bertahap.

### 6.12 Konten HTML Berbahaya dari WYSIWYG Editor (XSS)
- **Kasus:** Admin (atau peretas) memasukkan script berbahaya melalui WYSIWYG editor.
- **Penanganan:** Semua input dari WYSIWYG editor **disanitasi** di sisi server (misal menggunakan HTML Purifier atau DOMPurify via headless browser) sebelum disimpan. Hanya tag HTML yang diizinkan yang dipertahankan.

### 6.13 KaTeX Expression Gagal Dirender
- **Kasus:** Admin mengetikkan ekspresi KaTeX yang salah sintaks.
- **Penanganan:** 
  - Saat preview, jika KaTeX gagal dirender, tampilkan pesan error di editor.
  - Saat tampil ke peserta, jika gagal dirender, tampilkan teks mentah ekspresi tersebut sebagai fallback.

### 6.14 AI Mengembalikan JSON Tidak Valid
- **Kasus:** Respons dari Google Gemini API bukan JSON yang valid (misal: mengandung teks tambahan, JSON terpotong, atau struktur tidak sesuai skema).
- **Penanganan:**
  - Sistem mencoba parse JSON dari respons AI.
  - Jika gagal, coba ekstrak JSON dari dalam respons (AI kadang membungkus JSON dalam teks).
  - Jika tetap gagal, tampilkan pesan error: "Gagal memproses output AI. Silakan coba generate ulang."
  - Simpan raw response AI di log untuk debugging.
  - Admin bisa langsung klik "Generate Ulang" tanpa harus mengisi ulang form.

### 6.15 PG Kompleks dengan Jumlah Benar Kurang dari 2
- **Kasus:** Soal PG Kompleks yang hanya memiliki 1 (atau 0) jawaban benar.
- **Penanganan:**
  - Saat validasi, sistem menolak menyimpan soal PG Kompleks dengan jumlah benar < 2 dengan pesan: *"PG Kompleks harus memiliki minimal 2 jawaban benar."*
  - Admin harus mengoreksi (menambah jawaban benar atau mengubah tipe menjadi PG biasa) sebelum menyimpan.
  - Sistem juga menolak menyimpan paket soal yang berisi PG Kompleks dengan jumlah benar < 2.

### 6.16 Retake / Percobaan Ulang Tryout (Satu Percobaan)
- **Kasus:** Peserta ingin mengerjakan ulang paket tryout yang sama (misal karena gangguan teknis atau merasa tidak adil).
- **Penanganan:** Setiap paket tryout hanya mengizinkan **satu percobaan per akun** (`UNIQUE(user_id, paket_tryout_id)` pada `hasil_tryout`). Saat peserta mencoba memulai ulang paket yang sama, sistem menolak dengan pesan: *"Anda sudah menyelesaikan tryout ini. Silakan hubungi admin jika ada masalah teknis."* Admin dapat **menghapus hasil lama** (serta `percobaan` dan `riwayat_pengerjaan` terkait) untuk mengizinkan ulang sekali. Tidak ada retake otomatis.

---

## 7. PENJELASAN FITUR TERTENTU

### 7.1 Prompt AI untuk Generate Paket Soal

```text
Anda adalah asisten AI yang bertugas membuat soal tryout TKA (Tes Kemampuan Akademik) untuk SMK/MAK.

Tugas Anda:
Buatkan {jumlah_soal} soal untuk mapel {nama_mapel} dengan acuan Kompetensi Dasar (KD) berikut:
{daftar_KD_dan_deskripsinya}

Referensi tambahan (jika ada):
{isi_file_referensi}

Aturan pembuatan soal:
1. Setiap soal harus memiliki:
   - tipe_soal: "pg" (Pilihan Ganda), "pg_kompleks" (lebih dari 1 jawaban benar), atau "pg_kategori" (pernyataan dikategorikan ke kustom)
   - pertanyaan yang jelas dan tidak ambigu (bisa mengandung ekspresi matematika dalam format LaTeX)
   - opsi_jawaban: tepat 5 opsi (hanya untuk tipe "pg" dan "pg_kompleks")
   - pernyataan_kategori: minimal 3 pernyataan (hanya untuk tipe "pg_kategori", lihat aturan khusus di bawah)
   - jawaban benar yang jelas
   - pembahasan yang edukatif dan mudah dipahami (bisa mengandung ekspresi matematika)
   - kompetensi_dasar yang sesuai dengan salah satu KD yang diberikan

2. Aturan khusus per tipe soal:
   a. **PG (Pilihan Ganda):**
      - Hanya 1 opsi yang `is_benar: true`.
      - Tepat 5 opsi.

   b. **PG Kompleks:**
      - Minimal 2 opsi yang `is_benar: true` (boleh semua opsi benar).
      - Setiap opsi memiliki `parameter_irt` sendiri (a, b, c per opsi).
      - Field `parameter_irt` tingkat soal juga diperlukan sebagai ringkasan.

   c. **PG Kategori:**
      - Tidak menggunakan field `opsi_jawaban`. Gunakan field `pernyataan_kategori`.
      - Setiap pernyataan harus dikategorikan ke salah satu kategori yang didefinisikan admin.
      - Kategori bersifat **custom**: admin mendefinisikan pasangan kategori per soal
        (contoh: "Benar/Salah", "Setuju/Tidak Setuju", "Fakta/Opini", "Kuat/Lemah", dll).
      - Field `kategori_pg_kategori` di metadata berisi daftar kategori yang tersedia untuk seluruh paket.
        Setiap pernyataan harus menggunakan salah satu kategori dari daftar tersebut.
      - Minimal 3 pernyataan, maksimal 5 pernyataan.
      - Setiap pernyataan memiliki `parameter_irt` sendiri (a, b, c per pernyataan).

3. Untuk parameter IRT (a, b, c):
   - a (daya beda): 0.5 - 2.5 (semakin tinggi, semakin baik membedakan siswa pintar vs kurang pintar)
   - b (tingkat kesulitan): -3 sampai +3 (negatif = mudah, positif = sulit)
   - c (tebakan): 0 - 0.35 (probabilitas tebakan)
   - Estimasi parameter berdasarkan tingkat kesulitan yang diminta: {tingkat_kesulitan}

4. Distribusi tingkat kesulitan:
   - Jika {tingkat_kesulitan} adalah "campuran", distribusi soal harus:
     * 30% mudah (b < -0.5)
     * 40% sedang (-0.5 ≤ b ≤ 0.5)
     * 30% sulit (b > 0.5)
   - Jika {tingkat_kesulitan} adalah "mudah", semua soal harus b < 0.
   - Jika {tingkat_kesulitan} adalah "sedang", semua soal harus -1 ≤ b ≤ 1.
   - Jika {tingkat_kesulitan} adalah "sulit", semua soal harus b > 0.

5. Variasi soal:
   - Sebisa mungkin variasi tipe soal (PG, PG Kompleks, PG Kategori) sesuai dengan materi.
   - Untuk soal yang membutuhkan ekspresi matematika, gunakan format LaTeX (contoh: \( \frac{2}{3} \)).
   - Setiap soal harus unik (tidak ada pertanyaan yang sama atau hampir sama).

6. Output HARUS dalam format JSON yang valid dengan struktur di bawah ini.
   JANGAN tambahkan teks di luar JSON.
   JANGAN gunakan markdown code block.
```

> Naskah yang benar-benar dikirim ke model dibangun di `app/Domain/Ai/PromptBuilder.php`.
> Blok di atas adalah potret ilustratif — bila ada perbedaan angka atau aturan,
> `PromptBuilder` yang berlaku (dan sudah diuji).

### 7.2 Skema JSON Output AI

**Contoh 1 — Soal PG (Pilihan Ganda):**
```json
{
  "metadata": {
    "mapel": "Matematika",
    "jumlah_soal": 20,
    "tingkat_kesulitan": "campuran",
    "kategori_pg_kategori": ["Benar", "Salah"],
    "kd_yang_digunakan": [
      {
        "kode": "3.1",
        "deskripsi": "Menganalisis sifat-sifat bilangan berpangkat dan bentuk akar"
      }
    ],
    "generated_at": "2026-09-09T10:30:00Z"
  },
  "paket_soal": {
    "nama": "Paket Matematika - Bilangan & Persamaan",
    "deskripsi": "Paket soal Matematika yang mencakup KD 3.1 dan 3.2"
  },
  "daftar_soal": [
    {
      "id_soal_sementara": "S001",
      "tipe_soal": "pg",
      "pertanyaan": "Hasil dari \\( 2^3 \\times 2^5 \\) adalah ...",
      "gambar_url": null,
      "opsi_jawaban": [
        {
          "teks": "\\( 2^8 \\)",
          "is_benar": true,
          "urutan": 1
        },
        {
          "teks": "\\( 2^{15} \\)",
          "is_benar": false,
          "urutan": 2
        }
      ],
      "pembahasan": "Sifat perkalian bilangan berpangkat: \\( a^m \\times a^n = a^{m+n} \\), sehingga \\( 2^3 \\times 2^5 = 2^{3+5} = 2^8 \\).",
      "kompetensi_dasar": {
        "kode": "3.1",
        "deskripsi": "Menganalisis sifat-sifat bilangan berpangkat dan bentuk akar"
      },
      "parameter_irt": {
        "a_diskriminasi": 1.2,
        "b_kesulitan": -0.5,
        "c_tebakan": 0.20
      }
    }
  ]
}
```

**Contoh 2 — Soal PG Kompleks (lebih dari 1 jawaban benar):**
```json
{
  "id_soal_sementara": "S002",
  "tipe_soal": "pg_kompleks",
  "pertanyaan": "Perhatikan pernyataan berikut tentang sifat bilangan berpangkat. Pernyataan yang BENAR adalah ...",
  "gambar_url": null,
  "opsi_jawaban": [
    {
      "teks": "\\( a^0 = 1 \\) untuk setiap \\( a \\neq 0 \\)",
      "is_benar": true,
      "urutan": 1,
      "parameter_irt": {
        "a_diskriminasi": 1.5,
        "b_kesulitan": -0.8,
        "c_tebakan": 0.10
      }
    },
    {
      "teks": "\\( a^{-n} = \\frac{1}{a^n} \\) untuk \\( a \\neq 0 \\)",
      "is_benar": true,
      "urutan": 2,
      "parameter_irt": {
        "a_diskriminasi": 1.3,
        "b_kesulitan": -0.3,
        "c_tebakan": 0.15
      }
    },
    {
      "teks": "\\( (a^m)^n = a^{m+n} \\)",
      "is_benar": false,
      "urutan": 3,
      "parameter_irt": {
        "a_diskriminasi": 1.8,
        "b_kesulitan": 0.5,
        "c_tebakan": 0.20
      }
    },
    {
      "teks": "\\( a^m \\times a^n = a^{m \\times n} \\)",
      "is_benar": false,
      "urutan": 4,
      "parameter_irt": {
        "a_diskriminasi": 2.0,
        "b_kesulitan": 0.8,
        "c_tebakan": 0.15
      }
    }
  ],
  "pembahasan": "Pernyataan 1 benar: definisi pangkat 0. Pernyataan 2 benar: definisi pangkat negatif. Pernyataan 3 salah: seharusnya \\( (a^m)^n = a^{m \\times n} \\). Pernyataan 4 salah: seharusnya \\( a^m \\times a^n = a^{m+n} \\).",
  "kompetensi_dasar": {
    "kode": "3.1",
    "deskripsi": "Menganalisis sifat-sifat bilangan berpangkat dan bentuk akar"
  },
  "parameter_irt": {
    "a_diskriminasi": 1.5,
    "b_kesulitan": 0.1,
    "c_tebakan": 0.15
  }
}
```

**Contoh 3 — Soal PG Kategori (pernyataan dikategorikan):**
```json
{
  "id_soal_sementara": "S003",
  "tipe_soal": "pg_kategori",
  "pertanyaan": "Kategorikan setiap pernyataan berikut ke dalam kategori yang tepat:",
  "gambar_url": null,
  "pernyataan_kategori": [
    {
      "teks": "Photoshop adalah software untuk membuat animasi 3D",
      "kategori_benar": "Salah",
      "urutan": 1,
      "parameter_irt": {
        "a_diskriminasi": 1.0,
        "b_kesulitan": -0.4,
        "c_tebakan": 0.25
      }
    },
    {
      "teks": "HTML adalah bahasa markup, bukan bahasa pemrograman",
      "kategori_benar": "Benar",
      "urutan": 2,
      "parameter_irt": {
        "a_diskriminasi": 1.3,
        "b_kesulitan": 0.1,
        "c_tebakan": 0.20
      }
    },
    {
      "teks": "CSS hanya bisa digunakan untuk styling di browser",
      "kategori_benar": "Benar",
      "urutan": 3,
      "parameter_irt": {
        "a_diskriminasi": 0.9,
        "b_kesulitan": -0.2,
        "c_tebakan": 0.30
      }
    },
    {
      "teks": "JavaScript adalah bahasa pemrograman yang berjalan di server saja",
      "kategori_benar": "Salah",
      "urutan": 4,
      "parameter_irt": {
        "a_diskriminasi": 1.6,
        "b_kesulitan": 0.3,
        "c_tebakan": 0.15
      }
    }
  ],
  "pembahasan": "Photoshop adalah software untuk editing gambar, bukan animasi 3D (gunakan Blender/3ds Max). HTML memang bahasa markup. CSS memang digunakan untuk styling di browser. JavaScript berjalan di browser DAN server (Node.js).",
  "kompetensi_dasar": {
    "kode": "3.1",
    "deskripsi": "Memahami konsep dasar teknologi informasi"
  },
  "parameter_irt": {
    "a_diskriminasi": 1.2,
    "b_kesulitan": -0.05,
    "c_tebakan": 0.22
  }
}
```

**Keterangan field:**
- `metadata.kategori_pg_kategori`: Daftar kategori yang tersedia untuk tipe `pg_kategori` dalam paket ini. Setiap `pernyataan_kategori.kategori_benar` harus menggunakan salah satu nilai dari daftar ini.
- `opsi_jawaban[].parameter_irt`: Parameter IRT per opsi (wajib untuk `pg_kompleks`, opsional untuk `pg`).
- `pernyataan_kategori`: Field khusus untuk `pg_kategori`, menggantikan `opsi_jawaban`.
- `pernyataan_kategori[].parameter_irt`: Parameter IRT per pernyataan (wajib untuk `pg_kategori`).

### 7.3 Mengapa Parameter IRT untuk PG Kompleks & Kategori Dibedakan per Opsi/Pernyataan?
Karena dalam soal PG Kompleks atau Kategori, setiap opsi/pernyataan memiliki tingkat kesulitan dan daya beda yang berbeda. Misal, dalam satu soal PG Kompleks, opsi A mungkin sangat mudah diidentifikasi sebagai salah, sementara opsi C sulit dibedakan. Dengan memberikan parameter IRT per opsi, penilaian menjadi lebih akurat.

### 7.4 Bagaimana Cara Menghitung Skor Pelaporan dari Banyak Mapel?

Skor yang dilihat peserta — kolom `skor_konversi`, tampil sebagai **Skor IRT** pada leaderboard dan analisis — berasal dari **rata-rata theta dari seluruh mapel yang dikerjakan**, lalu dikonversi ke skala pelaporan. **Mapel yang tidak dijawab sama sekali diabaikan** dari perhitungan rata-rata theta.

**Rumus konversi (konsisten dengan §1.2):**
- SD/SMP: `50 + 10 × θ` → clamp 0–100
- SMA/SMK: `500 + 100 × θ` → clamp 200–800

**Contoh (SMA):**
- Theta per mapel: [1.2, 0.8, 0.5, 1.0, 0.3]
- Rata-rata theta: 0.76
- Skor: `500 + 100 × 0.76 = 576`

**Ini bukan kolom `skor_irt_total`.** Kolom itu menyimpan **jumlah proporsi jawaban benar** — praktisnya jumlah soal yang terjawab benar — dan ditampilkan di halaman hasil sebagai *Soal benar setara* (jenisnya berbeda dari skor konversi). Label lamanya "Skor IRT total" berbenturan dengan definisi di atas karena angkanya memang tidak pernah dikonversi ke skala pelaporan.

### 7.5 Bagaimana Cara Menentukan KD yang "Perlu Bimbingan"?
Berdasarkan tabel level kompetensi:
- **Mahir:** theta ≥ 1.5
- **Menengah:** 0.5 ≤ theta < 1.5
- **Dasar:** -0.5 ≤ theta < 0.5
- **Perlu Bimbingan:** -1.5 ≤ theta < -0.5
- **Belum Teridentifikasi:** theta < -1.5

### 7.6 Apakah Peserta Bisa Memilih Mapel Pilihan yang Sama?
Tidak. Sistem memastikan mapel pilihan 1 dan 2 tidak sama, dan tidak sama dengan mapel wajib.

### 7.7 Bagaimana Leaderboard Menangani Skor yang Sama?
Jika skor IRT sama, peserta dengan durasi pengerjaan lebih cepat mendapatkan peringkat lebih tinggi. Jika durasi juga sama, peringkat berdasarkan waktu selesai (yang lebih dulu selesai lebih tinggi).

### 7.8 Apa Perbedaan Paket Soal dan Paket Tryout?
- **Paket Soal:** Kumpulan soal untuk SATU mapel (misal: 20 soal Matematika).
- **Paket Tryout:** Kumpulan dari 5 paket soal (3 wajib + 2 pilihan) yang membentuk satu tryout utuh.

### 7.9 Bagaimana AI Menentukan Parameter IRT (a,b,c) Saat Generate Paket?
AI akan mengestimasi parameter berdasarkan:
- **Tingkat kesulitan yang diminta** (mudah → b negatif, sulit → b positif)
- **Tipe soal** (PG biasanya a lebih tinggi, PG kompleks a lebih rendah)
- **Materi** (soal penalaran biasanya a lebih tinggi daripada soal pengetahuan)
- **Referensi tambahan** yang diupload admin

**Catatan:** Estimasi AI adalah **prediksi awal**, admin wajib mengkurasi dan menyesuaikan.

### 7.10 Bagaimana Cara Proteksi Konten Soal dari Kecurangan?
Sistem menerapkan proteksi dasar:
- **CSS:** `user-select: none` untuk mencegah seleksi teks.
- **JavaScript:** Mencegah klik kanan (`contextmenu` event) dan shortcut keyboard (`Ctrl+C`, `Ctrl+A`, `Ctrl+U`).
- **Catatan:** Proteksi ini bersifat client-side (dasar) dan sesuai dengan batasan budget. Untuk proteksi lebih tinggi dapat dikembangkan di masa depan.

### 7.11 Bagaimana WYSIWYG Editor Menangani Konten Matematika?
1. Admin mengetikkan ekspresi matematika dengan format LaTeX:
   - Inline: `\( ... \)` → `\( \frac{2}{3} \)`
   - Display: `\[ ... \]` → `\[ \int_0^1 x^2 dx \]`
2. Editor menampilkan preview real-time menggunakan KaTeX.
3. Saat disimpan, konten disimpan sebagai HTML dengan tag khusus (contoh: `<span class="katex-inline">...</span>`).
4. Saat ditampilkan ke peserta, sistem merender ulang menggunakan KaTeX.

### 7.12 Bagaimana Cara Upload Gambar di WYSIWYG Editor?
1. Admin mengklik tombol "Sisipkan gambar" di bawah toolbar editor lalu memilih file.
2. Sebelum dikirim, file dikompres di browser lewat Canvas API ke WebP dengan anggaran 500 KB (fallback JPEG bila WebP tidak didukung) — lihat edge 6.6.
3. Komponen Livewire `Wysiwyg` memvalidasi bahwa file benar-benar gambar berformat JPG/PNG/WebP dan maksimal 500 KB, lalu menyimpannya ke disk publik pada direktori `gambar-soal/`.
4. URL hasil simpan disisipkan ke editor sebagai elemen `<img>` dan tampil sebagai preview.
5. Saat disimpan, gambar direferensikan melalui URL di konten HTML.

> Opsi **masukkan URL** tanpa unggah (langkah 2 pada rancangan awal) belum
> diimplementasikan; jalur unggah berkaslah yang berlaku sekarang.
