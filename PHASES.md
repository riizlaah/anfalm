# PHASES — Anfalm

Dokumen ini mendeskripsikan fase pengembangan Anfalm (Laravel + MySQL) dengan pendekatan **Test Driven Development (TDD)** menggunakan **Pest**. Setiap fase harus diselesaikan berurutan; fase tidak dianggap selesai sampai **Kriteria Selesai (Definition of Done)** terpenuhi.

Aturan umum TDD di semua fase:
1. **Tulis test lebih dulu (RED)** — test tersebut harus gagal dengan alasan yang benar.
2. **Implementasi minimum (GREEN)** — buat testnya lewat dengan perubahan paling sederhana.
3. **Refactor (REFACTOR)** — rapikan kode tanpa mengubah perilaku; semua test tetap hijau.
4. Jalankan `php artisan test` sebelum menyerahkan fase.

---

## Fase 0 — Fondasi & Skema Database

**Goal:** Base project Laravel yang sehat, terhubung MySQL/MariaDB, dengan skema database final (sesuai DESN.md §5 yang sudah dikonversi ke MySQL) seluruhnya tersedia sebagai migrations, dilengkapi factory & seeder, serta pipeline test yang hidup.

**Deliverables:**
- Project Laravel di root repo dengan `.env` terkonfigurasi (DB `anfalm` untuk lokal, `anfalm_test` untuk test).
- Pest + Livewire + Pint terpasang.
- Seluruh migration: `users`, `mapel`, `kompetensi_dasar`, `soal`, `opsi_jawaban`, `pernyataan_kategori`, `paket_soal`, `detail_paket_soal`, `paket_tryout`, `percobaan`, `riwayat_pengerjaan`, `hasil_tryout`, `tracking_kompetensi`, `tracking_mapel` + tabel standar Laravel.
- Eloquent models dasar beserta relasi dan casts.
- Factories untuk seluruh model inti, seeder (admin + mapel default SMK RPL).

**Kriteria Selesai:**
- `php artisan migrate --database=anfalm` dan `anfalm_test` berjalan tanpa error, bisa di-rollback & migrate ulang.
- `php artisan test` hijau (memiliki minimal smoke test: migration + seeder).
- Skema mencerminkan seluruh keputusan yang dikunci:
  - PK `BIGINT` auto-increment disemua tabel (tanpa UUID).
  - `users` memuat kolom `profiles` (nama_lengkap, sekolah, tingkat, jurusan, role).
  - `mapel.is_pkk`, soft delete pada mapel/KD/soal/paket_soal/paket_tryout.
  - `soal.daftar_kategori` (JSON) untuk PG Kategori.
  - `paket_tryout.batas_waktu_menit` wajib (NOT NULL).
  - Tabel `percobaan` untuk sesi/progres (edge: lanjutkan/mulai ulang, 6.4).
  - `riwayat_pengerjaan.percobaan_id`.
  - `theta` default nullable (belum teridentifikasi ≠ 0).

---

## Fase 1 — Domain IRT & Skoring

**Goal:** Logika penilaian IRT (3PL) sebagai domain service murni (tanpa coupling HTTP/DB) yang dibuktikan benar oleh unit test.

**Deliverables:**
- `IrtService`:
  - Fungsi probabilitas 3PL `P(θ) = c + (1-c)/(1+e^-a(θ-b))`.
  - Estimasi MLE (Newton-Raphson) + fallback ekstrem (semua benar→θ=3.0, semua salah→θ=-3.0) — edge 6.3.
  - Estimasi dengan prior lemah N(0,1) untuk data sedikit / per-KD (hindari θ ekstrem palsu).
  - Standard error dari test information.
  - Konversi θ→skala (0-100 untuk SD, 200-700 untuk SMA/SMK) berdasarkan **tingkat user**, dengan clamp — edge 6.8.
- `ScoringService`:
  - **Kredit parsial per-item**: PG = 1 item; PG Kompleks = 1 item per opsi; PG Kategori = 1 item per pernyataan.
  - `jumlah_benar`/`jumlah_salah`/`total_soal` dihitung per-item.
  - Jawaban kosong diabaikan (tidak masuk estimasi) — edge 6.2.
  - Parameter IRT per-opsi/pernyataan yang null memakai default (a=1, b=0, c=0.25).
- `KompetensiLevel`: klasifikasi level (7.5); data kosong = "Belum Teridentifikasi".

**Kriteria Selesai:**
- Unit test menutupi seluruh fungsi + edge cases di atas (target ≥ 90% coverage domain ini).
- Nilai numerik diverifikasi dengan kasus tangan (hand-computed).

---

## Fase 2 — Autentikasi & Role

**Goal:** Login/registrasi dengan dua peran (admin/peserta) dan kebijakan satu-sesi-per-akun.

**Deliverables:**
- Register (role default peserta), login, logout; middleware `role:admin`.
- **Satu sesi aktif per akun** — login dari perangkat lain menginvalidasi sesi lama (edge 6.5) via `database` session driver.

**Kriteria Selesai:**
- Feature test: register → login → logout; akses admin ditolak untuk peserta; sesi lama mati saat login baru.

---

## Fase 3 — Master Data (CRUD + Validasi)

**Goal:** CRUD aman dan tervalidasi untuk Mapel, Kompetensi Dasar, dan Soal.

**Deliverables:**
- `Mapel` (3.1): kode unik, tingkat, jenis, `is_pkk`.
- `KompetensiDasar` (3.2): unik per (mapel_id, kode_kompetensi); single source of truth.
- `Soal` (3.3): tipe pg/pg_kompleks/pg_kategori, WYSIWYG konten, parameter IRT dengan default & peringatan (6.1), `daftar_kategori` untuk pg_kategori; opsi & pernyataan_kategori; min 2 / max 5.
- Soft delete + **blokir hapus permanen** bila data sudah dipakai (paket/tryout/riwayat); edit soal aman karena `is_benar` sudah snapshot di riwayat.

**Kriteria Selesai:**
- Feature test per CRUD: validasi, restriksi hapus, cascade, filter tingkat.
- Validasi PG Kompleks ≤1 benar = ditolak (6.15).

---

## Fase 4 — Paket Soal & Paket Tryout

**Goal:** Mengelompokkan soal dan menyusun tryout 3+2 dengan aturan SMK yang tepat.

**Deliverables:**
- `PaketSoal` (3.5): kumpulan soal satu mapel, soal unik dalam paket.
- `PaketTryout` (3.6):
  - 3 mapel wajib + 2 pilihan; wajib tidak boleh sama dengan pilihan; pilihan_1 ≠ pilihan_2 (7.6).
  - **Aturan SMK:** minimal satu dari pilihan_1/pilihan_2 berjenis `pilihan_kejuruan` ATAU `is_pkk=true`. Keduanya boleh kejuruan (jarang).
  - `batas_waktu_menit` wajib (default 120).
  - Minimal 1 soal per mapel (6.10).
  - Validasi berlaku saat create DAN edit.

**Kriteria Selesai:**
- Feature test mencakup seluruh kombinasi validasi SMK (kejuruan+umum, PKK+umum, kejuruan+kejuruan, ditolak bila tidak ada kejuruan/PKK).
- Test duplikasi & minimal soal.

---

## Fase 5 — Generate Soal dari AI

**Goal:** Integrasi Google Gemini yang terisolasi (testable), alur kurasi, dan pemulihan output tak valid.

**Deliverables:**
- Contract `AiProvider` + `GeminiAiProvider` (HTTP) + `AiFake` (fixture JSON untuk test).
- Prompt builder (7.1) dengan distribusi merata antar KD yang dipilih.
- Validator skema output (7.2) — termasuk daftar kategori `pg_kategori` yang bersifat **per-soal** (bukan per-paket).
- `JsonRepairService`: parse JSON; ekstrak JSON dari teks; error ramah (6.14); log raw response.
- Batas maks 30 soal/generate (6.11); retry/error handling (6.7); referensi PDF/teks via Gemini File API.

**Kriteria Selesai:**
- Unit test: prompt builder, validator skema, JSON repair (valid, terpotong, salah struktur, mengandung teks).
- Feature test: alur generate→kurasi→simpan paket; soal dari AI tetap lewat validasi 6.15.

---

## Fase 6 — Mode Ujian: Tryout & Latihan

**Goal:** Alur mengerjakan soal yang stabil, bisa dilanjutkan, dan hasil dihitung benar.

**Deliverables:**
- `Percobaan` flow: mulai → kerjakan → progres tersimpan → **lanjutkan / mulai ulang** (6.4).
- Tryout: soal diacak per percobaan, navigasi bebas, mapel dikunci saat "Lanjut ke Mapel Berikutnya" (modal konfirmasi), **global countdown + auto-submit** saat waktu habis, **satu percobaan** per user+paket, mapel tanpa jawaban diabaikan dari rata-rata theta (7.4).
- Latihan: pilih mapel/jumlah/KD filter, timer stopwatch atau countdown, auto-submit saat waktu habis.
- Penyimpanan hasil → `riwayat_pengerjaan` + `hasil_tryout` + `percobaan` selesai.

**Kriteria Selesai:**
- Feature test: seluruh alur di atas + edge cases; perhitungan hasil tervalidasi terhadap domain service Fase 1.

---

## Fase 7 — Tracking Kompetensi & Leaderboard

**Goal:** Analisis kompetensi peserta dan peringkat tryout.

**Deliverables:**
- `TrackingKompetensi` per KD: total dikerjakan, benar, persentase, theta+SE (MLE/prior) — data kosong = belum teridentifikasi.
- `TrackingMapel`: theta dari seluruh item mapel, level, jumlah tryout, rata-rata skor.
- Leaderboard per tryout (3.10): hanya peserta selesai (6.9), urut skor IRT desc, tie-break durasi → waktu selesai (7.7).
- Data untuk radar/bar/line chart (3.9).

**Kriteria Selesai:**
- Feature test agregasi & tie-break leaderboard (skor sama, durasi sama).
- Test level & rekomendasi pada berbagai theta.

---

## Fase 8 — Frontend Livewire (WYSIWYG, KaTeX, Upload)

**Goal:** UI admin & peserta berbasis Livewire + Alpine dengan editor konten kaya.

**Deliverables:**
- Komponen form soal: **TipTap** (wrapper Alpine) + preview **KaTeX** (`\( ... \)`, `\[ ... \]`).
- Upload gambar: kompresi WebP client-side (Canvas, maks 500 KB) → local disk (siap S3).
- **Sanitasi HTMLPurifier** di sisi server, whitelist markup KaTeX; DOMPurify di client (6.12, 6.13 fallback teks mentah).
- Halaman: dashboard admin/peserta, manager mapel/KD/soal/paket, kurasi AI, player tryout, latihan, analisis kompetensi (Chart.js), leaderboard.
- Proteksi konten peserta: `user-select:none`, blok klik-kanan & shortcut copy — **hanya di view peserta**, bukan editor admin (7.10).

**Kriteria Selesai:**
- Test: sanitasi server (XSS), upload validasi, proteksi tidak mengganggu editor.
- Manual/visual smoke test seluruh halaman.

---

## Fase 9 — Hardening & Non-fungsional

**Goal:** Kesiapan produksi.

**Deliverables:**
- Index MySQL untuk pola query utama (soal per KD, riwayat per user/percobaan, hasil per user, tracking) + eliminasi N+1.
- Peninjauan ulang semua edge case DESIGN.md §6.
- Update DESIGN.md §5 ke skema MySQL final.
- Smoke test end-to-end semua alur utama.

**Kriteria Selesai:**
- Seluruh `php artisan test` hijau; tidak ada query N+1 di halaman utama; DESIGN.md sinkron.