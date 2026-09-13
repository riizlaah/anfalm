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

**Skala Pelaporan:**
- **SD/Sederajat:** 0 - 100 (T-Score)
- **SMA/SMK/Sederajat:** 200 - 700 (T-Score yang discale)

**Cara Konversi Theta ke Skala:**
```
Skala 0-100: 50 + 10 × θ (di-clamp ke 0-100)
Skala 200-700: 450 + 100 × θ (di-clamp ke 200-700)
```

### 1.3 Target Pengguna
- **Admin:** Guru/pembuat soal
- **Peserta:** Siswa (SMK/MAK, SMA/MA, SMP/MTs, SD/MI)

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
   - Isi kode (unik), nama, tingkat (SD/SMP/SMA/SMK), dan jenis (wajib/pilihan_umum/pilihan_kejuruan).
   - Klik "Simpan".
5. **Edit mapel:** Klik ikon edit → Ubah data → Simpan.
6. **Hapus mapel:** Konfirmasi → Hapus (cascade ke data terkait).

**Validasi:**
- Kode mapel harus unik.
- Jika mapel sudah memiliki soal, muncul peringatan sebelum dihapus.

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
   - Gambar akan dikompres otomatis ke WebP (maks 500 KB) dan disimpan di Supabase Storage.
6. **Isi opsi jawaban** (minimal 2, maksimal 5) dengan WYSIWYG Editor yang sama.
   - Untuk PG: tentukan 1 jawaban benar.
   - Untuk PG Kompleks: tentukan lebih dari 1 jawaban benar.
   - Untuk PG Kategori: tidak menggunakan opsi jawaban, tetapi menggunakan pernyataan-kategori.
7. **Isi pembahasan** dengan WYSIWYG Editor (support teks, gambar, KaTeX).
8. **Isi parameter IRT (a, b, c):**
   - Admin bisa input manual (berdasarkan pengalaman).
   - Atau gunakan nilai default: `a=1.0, b=0.0, c=0.25`.
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
2. Isi metadata: Nama paket, tingkat (SMK/SMA/dll).
3. Pilih 3 mapel wajib (dari dropdown mapel yang berjenis `wajib`).
4. Pilih 2 mapel pilihan:
   - **Untuk SMK:** Salah satu harus **Proyek Kreatif dan Kewirausahaan (PKK)** , satunya lagi bebas (bisa pilih mapel kejuruan atau lainnya).
   - **Untuk SMA/Sederajat:** Bebas memilih 2 mapel pilihan apa saja.
5. Untuk setiap mapel:
   - Pilih paket soal yang sudah ada (dari fitur 3.4 atau 3.5).
   - Atau buat paket soal baru langsung dari halaman ini (manual atau generate AI).
6. Klik "Simpan".

**Validasi:**
- Mapel pilihan tidak boleh sama dengan mapel wajib.
- Minimal 1 soal per mapel.
- Untuk tingkat SMK, mapel_pilihan_1 harus PKK.

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
5. Setelah selesai semua soal di satu mapel → Lanjut ke mapel berikutnya.
6. Setelah semua mapel selesai → Klik "Selesai".
7. Sistem menghitung:
   - Estimasi theta (MLE) dari semua jawaban.
   - Skor IRT (dikonversi ke skala 0-100 atau 200-700 sesuai tingkat).
   - Simpan ke `hasil_tryout`.
8. Peserta melihat hasil: Skor IRT, jumlah benar/salah, **pembahasan per soal** (dirender dengan WYSIWYG + KaTeX).

**Catatan:** Peserta tidak bisa kembali ke mapel sebelumnya setelah selesai.

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
   - **Nama KD** + kode.
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

## 5. SCRIPT SQL (FULL DATABASE SCHEMA)

```sql
-- ============================================
-- 1. TABEL USERS (Pakai Supabase Auth)
-- ============================================
-- Supabase sudah menyediakan auth.users
-- Kita tambahkan tabel profil untuk role
CREATE TABLE public.profiles (
    id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
    nama_lengkap VARCHAR(100) NOT NULL,
    sekolah VARCHAR(100),
    tingkat VARCHAR(20) CHECK (tingkat IN ('SD', 'SMP', 'SMA', 'SMK')),
    jurusan VARCHAR(50), -- khusus SMK
    role VARCHAR(20) DEFAULT 'peserta' CHECK (role IN ('admin', 'peserta')),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Trigger untuk auto-create profil saat user daftar
CREATE OR REPLACE FUNCTION public.handle_new_user()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO public.profiles (id, nama_lengkap, role)
    VALUES (NEW.id, COALESCE(NEW.raw_user_meta_data->>'nama_lengkap', 'User'), 'peserta');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

CREATE TRIGGER on_auth_user_created
AFTER INSERT ON auth.users
FOR EACH ROW EXECUTE FUNCTION public.handle_new_user();

-- ============================================
-- 2. TABEL MAPEL
-- ============================================
CREATE TABLE public.mapel (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kode VARCHAR(20) UNIQUE NOT NULL,
    nama VARCHAR(100) NOT NULL,
    tingkat VARCHAR(20) CHECK (tingkat IN ('SD', 'SMP', 'SMA', 'SMK', 'all')),
    jenis VARCHAR(20) CHECK (jenis IN ('wajib', 'pilihan_umum', 'pilihan_kejuruan')),
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================
-- 3. TABEL KOMPETENSI DASAR
-- ============================================
CREATE TABLE public.kompetensi_dasar (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    mapel_id UUID REFERENCES public.mapel(id) ON DELETE CASCADE,
    kode_kompetensi VARCHAR(50) NOT NULL,
    deskripsi TEXT NOT NULL,
    materi_pokok VARCHAR(255),
    level_kognitif VARCHAR(50) CHECK (level_kognitif IN ('pengetahuan', 'pemahaman', 'penerapan', 'penalaran')),
    batasan TEXT, -- batasan materi/topik yang diujikan
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(mapel_id, kode_kompetensi)
);

-- ============================================
-- 4. TABEL SOAL (DENGAN PARAMETER IRT)
-- ============================================
CREATE TABLE public.soal (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kompetensi_dasar_id UUID REFERENCES public.kompetensi_dasar(id) ON DELETE CASCADE,
    tipe_soal VARCHAR(20) CHECK (tipe_soal IN ('pg', 'pg_kompleks', 'pg_kategori')),
    pertanyaan TEXT NOT NULL, -- HTML dari WYSIWYG
    gambar_url VARCHAR(255) NULL, -- gambar tambahan (opsional)
    pembahasan TEXT NULL, -- HTML dari WYSIWYG
    
    -- PARAMETER IRT (3PL)
    a_diskriminasi DECIMAL(5,3) NOT NULL DEFAULT 1.0,
    b_kesulitan DECIMAL(5,3) NOT NULL DEFAULT 0.0,
    c_tebakan DECIMAL(5,3) NOT NULL DEFAULT 0.25,
    
    created_by UUID REFERENCES public.profiles(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- ============================================
-- 5. TABEL OPSI JAWABAN
-- ============================================
CREATE TABLE public.opsi_jawaban (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    soal_id UUID REFERENCES public.soal(id) ON DELETE CASCADE,
    teks_opsi TEXT NOT NULL, -- HTML dari WYSIWYG
    is_benar BOOLEAN DEFAULT FALSE,
    urutan INTEGER,
    a_diskriminasi DECIMAL(5,3) NULL,
    b_kesulitan DECIMAL(5,3) NULL,
    c_tebakan DECIMAL(5,3) NULL
);

-- ============================================
-- 6. TABEL PERNYATAAN KATEGORI (PG KATEGORI)
-- ============================================
CREATE TABLE public.pernyataan_kategori (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    soal_id UUID REFERENCES public.soal(id) ON DELETE CASCADE,
    teks_pernyataan TEXT NOT NULL, -- HTML dari WYSIWYG
    kategori_benar VARCHAR(50) NOT NULL, -- 'Benar', 'Salah', 'Setuju', 'Tidak Setuju'
    a_diskriminasi DECIMAL(5,3) NULL,
    b_kesulitan DECIMAL(5,3) NULL,
    c_tebakan DECIMAL(5,3) NULL,
    urutan INTEGER
);

-- ============================================
-- 7. TABEL PAKET SOAL
-- ============================================
CREATE TABLE public.paket_soal (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nama_paket VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    mapel_id UUID REFERENCES public.mapel(id),
    created_by UUID REFERENCES public.profiles(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE public.detail_paket_soal (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    paket_soal_id UUID REFERENCES public.paket_soal(id) ON DELETE CASCADE,
    soal_id UUID REFERENCES public.soal(id) ON DELETE CASCADE,
    UNIQUE(paket_soal_id, soal_id)
);

-- ============================================
-- 8. TABEL PAKET TRYOUT
-- ============================================
CREATE TABLE public.paket_tryout (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nama_paket VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    tingkat VARCHAR(20) DEFAULT 'SMK',
    
    -- 3 Mapel Wajib
    mapel_wajib_1 UUID REFERENCES public.mapel(id),
    mapel_wajib_2 UUID REFERENCES public.mapel(id),
    mapel_wajib_3 UUID REFERENCES public.mapel(id),
    
    -- 2 Mapel Pilihan
    mapel_pilihan_1 UUID REFERENCES public.mapel(id),
    mapel_pilihan_2 UUID REFERENCES public.mapel(id),
    
    -- Paket Soal untuk masing-masing mapel
    paket_soal_wajib_1_id UUID REFERENCES public.paket_soal(id),
    paket_soal_wajib_2_id UUID REFERENCES public.paket_soal(id),
    paket_soal_wajib_3_id UUID REFERENCES public.paket_soal(id),
    paket_soal_pilihan_1_id UUID REFERENCES public.paket_soal(id),
    paket_soal_pilihan_2_id UUID REFERENCES public.paket_soal(id),
    
    created_by UUID REFERENCES public.profiles(id),
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================
-- 9. TABEL RIWAYAT PENGERJAAN
-- ============================================
CREATE TABLE public.riwayat_pengerjaan (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES public.profiles(id) ON DELETE CASCADE,
    soal_id UUID REFERENCES public.soal(id) ON DELETE CASCADE,
    paket_tryout_id UUID REFERENCES public.paket_tryout(id) NULL,
    
    jawaban_user JSONB NOT NULL, -- {'opsi': 'A'} atau {'opsi': ['A','C']}
    is_benar BOOLEAN NOT NULL,
    mode VARCHAR(20) CHECK (mode IN ('tryout', 'latihan')),
    
    waktu_mulai TIMESTAMP DEFAULT NOW(),
    waktu_selesai TIMESTAMP NULL,
    durasi_detik INTEGER NULL,
    
    -- Hasil IRT per soal (diisi setelah selesai)
    theta_est_moment DECIMAL(5,3) NULL,
    skor_irt DECIMAL(5,3) NULL,
    
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================
-- 10. TABEL HASIL TRYOUT
-- ============================================
CREATE TABLE public.hasil_tryout (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES public.profiles(id) ON DELETE CASCADE,
    paket_tryout_id UUID REFERENCES public.paket_tryout(id) ON DELETE CASCADE,
    
    theta_final DECIMAL(5,3) NOT NULL,
    standard_error DECIMAL(5,3),
    skor_irt_total DECIMAL(5,3) NOT NULL,
    skor_konversi INTEGER, -- 0-100 atau 200-700 sesuai tingkat
    
    jumlah_benar INTEGER,
    jumlah_salah INTEGER,
    total_soal INTEGER,
    durasi_total INTEGER,
    selesai_pada TIMESTAMP DEFAULT NOW(),
    
    UNIQUE(user_id, paket_tryout_id)
);

-- ============================================
-- 11. TABEL TRACKING KOMPETENSI (PER KD)
-- ============================================
CREATE TABLE public.tracking_kompetensi (
    user_id UUID REFERENCES public.profiles(id) ON DELETE CASCADE,
    kompetensi_dasar_id UUID REFERENCES public.kompetensi_dasar(id) ON DELETE CASCADE,
    
    total_soal_dikerjakan INTEGER DEFAULT 0,
    total_benar INTEGER DEFAULT 0,
    persentase_benar DECIMAL(5,2) DEFAULT 0,
    
    theta_estimasi DECIMAL(5,3) DEFAULT 0,
    theta_se DECIMAL(5,3) NULL,
    
    last_updated TIMESTAMP DEFAULT NOW(),
    
    PRIMARY KEY (user_id, kompetensi_dasar_id)
);

-- ============================================
-- 12. TABEL TRACKING MAPEL
-- ============================================
CREATE TABLE public.tracking_mapel (
    user_id UUID REFERENCES public.profiles(id) ON DELETE CASCADE,
    mapel_id UUID REFERENCES public.mapel(id) ON DELETE CASCADE,
    
    theta_estimasi DECIMAL(5,3) DEFAULT 0,
    level_kompetensi VARCHAR(20),
    total_tryout_diikuti INTEGER DEFAULT 0,
    rata_rata_skor_irt DECIMAL(5,3),
    
    last_updated TIMESTAMP DEFAULT NOW(),
    
    PRIMARY KEY (user_id, mapel_id)
);

-- ============================================
-- 13. INDEXES UNTUK PERFORMANCE
-- ============================================
CREATE INDEX idx_soal_kd ON public.soal(kompetensi_dasar_id);
CREATE INDEX idx_riwayat_user ON public.riwayat_pengerjaan(user_id);
CREATE INDEX idx_hasil_tryout_user ON public.hasil_tryout(user_id);
CREATE INDEX idx_tracking_kompetensi_user ON public.tracking_kompetensi(user_id);
CREATE INDEX idx_tracking_mapel_user ON public.tracking_mapel(user_id);
```

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
  - Batasi sesi aktif: hanya 1 sesi login per akun.
  - Jika login dari perangkat lain, sesi sebelumnya otomatis logout.

### 6.6 Upload Gambar Gagal atau Terlalu Besar
- **Kasus:** Admin upload gambar > 5 MB atau format tidak didukung.
- **Penanganan:**
  - Kompres otomatis ke WebP (maks 500 KB) di browser pakai Canvas API (`toBlob('image/webp')`). Sharp tidak bisa dijalankan di Cloudflare edge runtime.
  - Validasi ekstensi: hanya JPG, PNG, WebP yang diizinkan.
  - Jika masih gagal, tampilkan pesan error dan minta upload ulang.

### 6.7 AI Generate Paket Soal Gagal (API Error/Timeout)
- **Kasus:** Google Gemini API down atau timeout saat generate paket soal.
- **Penanganan:**
  - Tampilkan pesan error ke admin.
  - Simpan prompt yang sudah dibuat → admin bisa coba lagi nanti.
  - Fallback: admin input manual (satu per satu) atau upload file.

### 6.8 Skor IRT di Luar Rentang Skala
- **Kasus:** Theta > 3 atau < -3 menghasilkan skor konversi di luar rentang 0-100 atau 200-700.
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
- **Penanganan:** Semua input dari WYSIWYG editor **disanitasi** menggunakan library DOMPurify di sisi server sebelum disimpan ke database. Hanya tag HTML yang diizinkan (dari TipTap) yang dipertahankan.

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
- **Kasus:** AI menghasilkan soal PG Kompleks yang hanya memiliki 1 (atau 0) jawaban benar.
- **Penanganan:**
  - Saat kurasi, sistem menampilkan peringatan: "Soal PG Kompleks harus memiliki minimal 2 jawaban benar."
  - Admin harus mengoreksi (menambah jawaban benar atau mengubah tipe soal menjadi PG biasa) sebelum menyimpan.
  - Sistem menolak menyimpan paket yang berisi PG Kompleks dengan jumlah benar < 2.

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
   - opsi_jawaban: minimal 2, maksimal 5 (hanya untuk tipe "pg" dan "pg_kompleks")
   - pernyataan_kategori: minimal 2 pernyataan (hanya untuk tipe "pg_kategori", lihat aturan khusus di bawah)
   - jawaban benar yang jelas
   - pembahasan yang edukatif dan mudah dipahami (bisa mengandung ekspresi matematika)
   - kompetensi_dasar yang sesuai dengan salah satu KD yang diberikan

2. Aturan khusus per tipe soal:
   a. **PG (Pilihan Ganda):**
      - Hanya 1 opsi yang `is_benar: true`.
      - Minimal 2 opsi, maksimal 5 opsi.

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
      - Minimal 2 pernyataan, maksimal 5 pernyataan.
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

### 7.4 Bagaimana Cara Menghitung Skor IRT Total dari Banyak Mapel?
Skor IRT total adalah **rata-rata theta dari semua mapel**, lalu dikonversi ke skala pelaporan. Misal:
- Theta per mapel: [1.2, 0.8, 0.5, 1.0, 0.3]
- Rata-rata theta: 0.76
- Skor untuk SMA (200-700): `(0.76 + 3) / 6 * 500 + 200 = (3.76/6)*500 + 200 = 313.33 + 200 = 513`

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
1. Admin mengklik ikon "Insert Image" di toolbar editor.
2. Muncul popup dengan 2 opsi:
   - **Upload dari lokal:** Pilih file gambar → sistem upload ke Supabase Storage → otomatis insert URL ke editor.
   - **Masukkan URL:** Tempel URL gambar → insert ke editor.
3. Gambar akan ditampilkan di editor sebagai preview.
4. Saat disimpan, gambar direferensikan melalui URL di konten HTML.
