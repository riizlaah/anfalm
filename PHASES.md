# PHASES — Anfalm

Dokumen ini mendeskripsikan fase pengembangan Anfalm (Laravel + MySQL) dengan pendekatan **Test Driven Development (TDD)** menggunakan **Pest**. Setiap fase harus diselesaikan berurutan; fase tidak dianggap selesai sampai **Kriteria Selesai (Definition of Done)** terpenuhi.

Aturan umum TDD di semua fase:
1. **Tulis test lebih dulu (RED)** — test tersebut harus gagal dengan alasan yang benar.
2. **Implementasi minimum (GREEN)** — buat testnya lewat dengan perubahan paling sederhana.
3. **Refactor (REFACTOR)** — rapikan kode tanpa mengubah perilaku; semua test tetap hijau.
4. Jalankan `php artisan test` sebelum menyerahkan fase.

---

## Fase 0 — Fondasi & Skema Database

**Goal:** Base project Laravel yang sehat, terhubung MySQL/MariaDB, dengan skema database final (sesuai DESIGN.md §5 yang sudah dikonversi ke MySQL) seluruhnya tersedia sebagai migrations, dilengkapi factory & seeder, serta pipeline test yang hidup.

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
  - Konversi θ→skala (0-100 untuk SD, 200-800 untuk SMA/SMK) berdasarkan **tingkat user**, dengan clamp — edge 6.8.
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
- `Soal` (3.3): tipe pg/pg_kompleks/pg_kategori, WYSIWYG konten (pertanyaan & pembahasan mode **penuh**; opsi & pernyataan mode **inline** — lihat Fase 8), parameter IRT dengan default & peringatan (6.1), `daftar_kategori` untuk pg_kategori; opsi (min 5 / max 8) & pernyataan_kategori (min 3 / max 5).
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
  - Isi paket disimpan **per-baris mapel** (`paket_tryout_mapel`: mapel + paket_soal + batas waktu `menit` per mapel — bawaan wajib 75 / pilihan 60), bukan lagi pasangan kolom slot tetap; jumlah mapel mengikuti katalog, tidak terpaku 3+2 (7.6).
  - Mapel hanya tersedia sesuai tingkat paket (`tingkatMapelCocok`); paket soal wajib milik mapel yang sama dan minimal berisi 1 soal (6.10).
  - **Aturan SMK:** dari semua mapel pilihan pada paket, minimal satu berjenis `pilihan_kejuruan` ATAU `is_pkk=true`.
  - Validasi berlaku saat create DAN edit.

**Kriteria Selesai:**
- Feature test mencakup seluruh kombinasi validasi SMK (kejuruan+umum, PKK+umum, kejuruan+kejuruan, ditolak bila tidak ada kejuruan/PKK).
- Test duplikasi & minimal soal; pilihan mapel oleh peserta diuji pada Fase 6.

---

## Fase 5 — Generate Soal dari AI

**Goal:** Integrasi Google Gemini yang terisolasi (testable), alur kurasi, dan pemulihan output tak valid.

**Deliverables:**
- Contract `AiProvider` + `GeminiAiProvider` (HTTP) + `AiFake` (fixture JSON untuk test).
- Prompt builder (7.1) dengan distribusi merata antar KD yang dipilih.
- Validator skema output (7.2) — termasuk daftar kategori `pg_kategori` yang bersifat **per-soal** (bukan per-paket).
- `JsonRepairService`: parse JSON; ekstrak JSON dari teks; error ramah (6.14); log raw response.
- `MarkdownKeHtml`: konversi markdown dasar yang terselip dalam respons AI (tebal, miring, kode, daftar) menjadi HTML sebelum masuk kurasi.
- Batas maks 30 soal/generate (6.11); retry/error handling (6.7); **rantai model & timeout** diatur lewat env (fallback saat satu model ditolak); referensi PDF/teks via Gemini File API.

**Kriteria Selesai:**
- Unit test: prompt builder, validator skema, JSON repair (valid, terpotong, salah struktur, mengandung teks).
- Feature test: alur generate→kurasi→simpan paket; soal dari AI tetap lewat validasi 6.15.

---

## Fase 6 — Mode Ujian: Tryout & Latihan

**Goal:** Alur mengerjakan soal yang stabil, bisa dilanjutkan, dan hasil dihitung benar.

**Deliverables:**
- `Percobaan` flow: mulai → kerjakan → progres tersimpan → **lanjutkan / mulai ulang** (6.4).
- Tryout: soal diacak per percobaan, navigasi bebas, mapel dikunci saat "Lanjut ke Mapel Berikutnya" (modal konfirmasi), **countdown per mapel + auto-submit** saat waktu habis (dihitung ulang setiap pindah mapel), **satu percobaan** per user+paket, mapel tanpa jawaban diabaikan dari rata-rata theta (7.4).
- **Pilih mapel pilihan oleh peserta:** saat mulai tryout, peserta memilih **tepat dua** mapel pilihan dari seluruh yang ditawarkan paket (mapel wajib ikut otomatis; dikelompokkan per tingkat SMA/SMK).
- **Halaman jeda antar mapel** (§3.7): hitung mundur mapel berikutnya mulai saat tombol "Mulai" ditekan (bukan saat mapel dikunci), jeda tidak dibatasi waktu, kiriman ulang diabaikan, tanpa jeda di mapel terakhir.
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
- Komponen form soal: **TipTap** lewat `components/editor.blade.php` dengan dua mode — **penuh** (pertanyaan & pembahasan: daftar, kutipan, blok kode, heading, gambar) dan **inline** (baris opsi/pernyataan yang dirender di dalam `<label>` peserta: hanya teks format, kode inline, dan rumus; disimpan sebagai frasa tanpa `<p>`). Ekspresi **KaTeX** adalah node **atom** dengan dialog ƒ(x) (pratinjau di dialog, Simpan/Batal, mode blok hanya di mode penuh) — bukan panel preview terpisah; `\( ... \)` polos dan data lama dibungkus otomatis (idempoten).
- Upload gambar: kompresi WebP client-side (Canvas, maks 500 KB) → local disk (siap S3).
- **Sanitasi `symfony/html-sanitizer`** di sisi server, whitelist markup KaTeX; DOMPurify di client (6.12, 6.13 fallback teks mentah).
- Halaman: dashboard admin/peserta, manager mapel/KD/soal/paket, kurasi AI, player tryout, latihan, analisis kompetensi (Chart.js), leaderboard.
- Proteksi konten peserta: `user-select:none`, blok klik-kanan & shortcut copy — **hanya di view peserta**, bukan editor admin (7.10).
- **Penyempurnaan UI admin:** tabel manajemen soal me-render isi pertanyaan (HTML bersih) lalu memotongnya dengan CSS; tombol toggle lihat/sembunyi sandi pada seluruh input password; gaya blok kode & kode inline yang seragam di dalam maupun luar editor.

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

---

## Fase 10 — Mobile-first untuk Peserta

**Goal:** Halaman peserta benar-benar bisa dipakai di HP, bukan hanya "muat di layar kecil". Pengguna mayoritas mobile.

**Deliverables:**
- **Navigasi (10.1):** bottom tab bar khusus viewport `< md` berisi menu bagian peserta, dengan `safe-area-inset-bottom` dan active state mengikuti route. Nav desktop `md:flex` tidak berubah. Tab bar disembunyikan di halaman pengerjaan soal (`percobaan/kerja`) supaya tidak mengganggu fokus dan timer.
- **Opsi soal tidak meluber (10.2):** lepas `whitespace-nowrap` dari `.check` sehingga opsi panjang (terbukti 1258px di dalam kartu 288px) ikut wrap.
- **Kartu tryout (10.3):** judul dan grup tombol menumpuk vertikal di mobile; grup tombol kehilangan `shrink-0` agar "Mulai Ulang" + "Lanjutkan Tryout" turun baris alih-alih menambah scroll horizontal.
- **Zoom iOS & target sentuh (10.4):** kontrol form `max-md:text-base` (16px, menghentikan auto-zoom Safari), `.btn`, tombol header, `.rail-soal`, dan baris `.check` dinaikkan ke area tap yang layak — semuanya lewat varian `max-md:` agar tampilan desktop tidak berubah.

**Kriteria Selesai:**
- Test navigasi mobile hijau: tab bar ada di seluruh halaman peserta, nav desktop utuh, tab bar absen di halaman pengerjaan.
- Pengukuran browser 360×780: `scrollWidth === clientWidth` di seluruh halaman peserta; input 16px; `.btn`, tombol header, dan link tab bar 44px (rail-soal dan baris opsi 40px).
- `php artisan test --compact` hijau; `vendor/bin/pint --dirty` bersih; `npm run build` dijalankan.

---

## Fase 11 — Rilis & Deploy

**Goal:** Aplikasi bisa dirilis ke hosting produksi (InfinityFree) dan di-deploy ulang dengan jejak yang jelas — tanpa kredensial di dalam paket.

**Deliverables:**
- **Paket rilis** (`deploy/`): arsip zip siap unggah yang membuang berkas pengembangan dan **kredensial** (env/token), menyertakan template **env produksi**, paket **database** (SQL) terpisah, dan `htaccess` bila perlu.
- **Sinkron FTPS** (`deploy/sinkron.sh`): cek koneksi (dipaksa IPv4/PASV) sebelum merakit paket; unggah via curl dengan tampilan **kemajuan** dan **pesan keberhasilan tiap fase** unggah/buang.
- **Kebersihan paket:** berkas tak perlu dibuang dari paket dan folder `storage` runtime diisi supaya aplikasi langsung berjalan di server.
- **Manifest akhir** (`deploy/build/manifest-akhir.txt`): ditulis dari **keadaan server** pada setiap run (ikut tercatat walau run gagal), sehingga run berikutnya bisa memprediksi **berkas berubah/usang**.
- Catatan produksi: lingkungan tanpa SSH/cron/rute `migrate` — perubahan skema dilakukan manual via phpMyAdmin lalu disinkronkan lewat jalur ini.

**Kriteria Selesai:**
- Paket rilis teruji: tanpa kredensial, template env produksi tersedia, database terpisah.
- `deploy/sinkron.sh` sukses dari lokal dengan ramalan jumlah berkas berubah/usang yang cocok dengan keadaan server; `public/build` ikut terunggah.
- Gate penuh tetap hijau: `php artisan test --compact` lulus; `vendor/bin/pint --dirty` bersih; `npm run build` sukses.
