<?php

namespace Database\Seeders;

use App\Models\DetailPaketSoal;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Menyiapkan paket tryout yang bisa langsung dikerjakan peserta.
 *
 * Satu paket tryout sah butuh lima paket soal di lima mapel berbeda, dan tiap
 * paket soal minimal berisi satu soal. Database yang berisi hanya sebagian
 * mapel belum tentu memenuhi syarat itu, karena itu seeder ini melengkapi
 * kompetensi dasar dan soal yang masih kosong sebelum merangkai slotnya.
 *
 * Jalankan: php artisan db:seed --class=TryoutSeeder
 */
class TryoutSeeder extends Seeder
{
    private const SLOT_MAPEL = [
        'mapel_wajib_1',
        'mapel_wajib_2',
        'mapel_wajib_3',
        'mapel_pilihan_1',
        'mapel_pilihan_2',
    ];

    private const SLOT_PAKET = [
        'paket_soal_wajib_1_id',
        'paket_soal_wajib_2_id',
        'paket_soal_wajib_3_id',
        'paket_soal_pilihan_1_id',
        'paket_soal_pilihan_2_id',
    ];

    private const NAMA_TRYOUT = 'Tryout Fase 6 (uji coba)';

    private const TINGKAT = PaketTryout::TINGKAT_SMK;

    private const WAKTU_MENIT = 120;

    private const SOAL_PER_PAKET = 6;

    private const TIPE_SOAL = [
        Soal::TIPE_PG,
        Soal::TIPE_PG_KOMPLEKS,
        Soal::TIPE_PG_KATEGORI,
    ];

    public function run(): void
    {
        $mapels = $this->pilihMapel();

        $slotPaket = array_map(
            fn (Mapel $mapel): int => $this->paketSoalUntuk($mapel)->getKey(),
            $mapels
        );

        $atribut = [
            'deskripsi' => 'Paket tryout uji coba untuk memvalidasi alur pengerjaan Fase 6.',
            'tingkat' => self::TINGKAT,
            'batas_waktu_menit' => self::WAKTU_MENIT,
            'created_by' => $this->adminId(),
        ];

        foreach (self::SLOT_MAPEL as $index => $field) {
            $atribut[$field] = $mapels[$index]->getKey();
        }

        foreach (self::SLOT_PAKET as $index => $field) {
            $atribut[$field] = $slotPaket[$index];
        }

        PaketTryout::updateOrCreate(['nama_paket' => self::NAMA_TRYOUT], $atribut);
    }

    /**
     * Slot mapel dipilih agar aturan SMK tetap terpenuhi: minimal satu mapel
     * pilihan berjenis pilihan kejuruan atau berstatus PKK, dan tingkat mapel
     * harus cocok dengan tingkat tryout.
     *
     * @return array<int, Mapel>
     */
    private function pilihMapel(): array
    {
        $kandidat = Mapel::query()
            ->whereNull('deleted_at')
            ->whereIn('tingkat', [self::TINGKAT, Mapel::TINGKAT_SMA, Mapel::TINGKAT_ALL])
            ->orderBy('id')
            ->get();

        $wajib = $kandidat
            ->where('jenis', Mapel::JENIS_WAJIB)
            ->take(3)
            ->values();

        if ($wajib->count() < 3) {
            $wajib = $kandidat->take(3)->values();
        }

        $sisa = $kandidat->whereNotIn('id', $wajib->pluck('id'))->values();

        $kejuruan = $sisa
            ->filter(fn (Mapel $mapel): bool => $mapel->jenis === Mapel::JENIS_PILIHAN_KEJURUAN || $mapel->is_pkk)
            ->values();

        $pilihan1 = $kejuruan->first() ?? $sisa->first();
        $pilihan2 = $sisa->firstWhere('id', '!=', $pilihan1?->getKey());

        if ($pilihan1 === null || $pilihan2 === null) {
            throw new RuntimeException(
                'TryoutSeeder membutuhkan minimal 5 mapel hidup dengan tingkat yang cocok, termasuk satu mapel '
                .'pilihan_kejuruan atau berstatus PKK untuk aturan SMK.'
            );
        }

        return [...$wajib->all(), $pilihan1, $pilihan2];
    }

    /**
     * Paket soal yang sudah berisi soal untuk sebuah mapel; dipakai ulang bila
     * sudah ada supaya seeder tetap aman dijalankan berkali-kali.
     */
    private function paketSoalUntuk(Mapel $mapel): PaketSoal
    {
        $paket = PaketSoal::query()
            ->where('mapel_id', $mapel->getKey())
            ->whereHas('soal')
            ->orderBy('id')
            ->first();

        $paket ??= PaketSoal::query()->where('mapel_id', $mapel->getKey())->orderBy('id')->first();

        $paket ??= PaketSoal::create([
            'nama_paket' => 'Paket Uji Fase 6 '.$mapel->kode,
            'deskripsi' => 'Paket soal uji coba untuk mapel '.$mapel->nama.'.',
            'mapel_id' => $mapel->getKey(),
            'created_by' => $this->adminId(),
        ]);

        if ($paket->soal()->count() > 0) {
            return $paket;
        }

        $kd = $this->kompetensiDasarUntuk($mapel);

        for ($nomor = 1; $nomor <= self::SOAL_PER_PAKET; $nomor++) {
            DetailPaketSoal::create([
                'paket_soal_id' => $paket->getKey(),
                'soal_id' => $this->soalBaru($mapel, $kd, $nomor)->getKey(),
            ]);
        }

        return $paket;
    }

    private function kompetensiDasarUntuk(Mapel $mapel): KompetensiDasar
    {
        $kd = $mapel->kompetensiDasars()->orderBy('id')->first();

        if ($kd !== null) {
            return $kd;
        }

        $terpakai = $mapel->kompetensiDasars()
            ->pluck('kode_kompetensi')
            ->merge(KompetensiDasar::onlyTrashed()->where('mapel_id', $mapel->getKey())->pluck('kode_kompetensi'));

        $kode = collect(['1.1', '1.2', '2.1', '2.2'])
            ->first(fn (string $kandidat): bool => ! $terpakai->contains($kandidat));

        return $mapel->kompetensiDasars()->create([
            'kode_kompetensi' => $kode,
            'deskripsi' => 'Kompetensi dasar uji coba Fase 6 untuk '.$mapel->nama.'.',
            'materi_pokok' => 'Uji coba Fase 6',
            'level_kognitif' => 'pengetahuan_dan_pemahaman',
        ]);
    }

    /**
     * Membuat satu soal lengkap dengan opsi atau pernyataannya, supaya hasil
     * uji coba tidak hanya memakai satu tipe soal.
     */
    private function soalBaru(Mapel $mapel, KompetensiDasar $kd, int $nomor): Soal
    {
        $tipe = self::TIPE_SOAL[($nomor - 1) % count(self::TIPE_SOAL)];

        $soal = Soal::create([
            'kompetensi_dasar_id' => $kd->getKey(),
            'tipe_soal' => $tipe,
            'pertanyaan' => sprintf('%s — soal uji coba nomor %d.', $mapel->kode, $nomor),
            'pembahasan' => 'Pembahasan soal uji coba nomor '.$nomor.'.',
            'daftar_kategori' => $tipe === Soal::TIPE_PG_KATEGORI ? ['Benar', 'Salah'] : null,
            'a_diskriminasi' => 1.0,
            'b_kesulitan' => 0.0,
            'c_tebakan' => 0.25,
            'created_by' => $this->adminId(),
        ]);

        if ($tipe === Soal::TIPE_PG_KATEGORI) {
            $this->tambahkanPernyataan($soal);

            return $soal;
        }

        $this->tambahkanOpsi($soal, $tipe === Soal::TIPE_PG_KOMPLEKS ? 2 : 1);

        return $soal;
    }

    private function tambahkanOpsi(Soal $soal, int $jumlahBenar): void
    {
        foreach (range(1, 5) as $urutan) {
            $soal->opsiJawaban()->create([
                'teks_opsi' => 'Opsi '.$urutan,
                'is_benar' => $urutan <= $jumlahBenar,
                'urutan' => $urutan,
                'a_diskriminasi' => 1.0,
                'b_kesulitan' => 0.0,
                'c_tebakan' => 0.25,
            ]);
        }
    }

    private function tambahkanPernyataan(Soal $soal): void
    {
        foreach (range(1, 3) as $urutan) {
            $soal->pernyataanKategori()->create([
                'teks_pernyataan' => 'Pernyataan uji coba nomor '.$urutan.'.',
                'kategori_benar' => $urutan % 2 === 0 ? 'Salah' : 'Benar',
                'urutan' => $urutan,
                'a_diskriminasi' => 1.0,
                'b_kesulitan' => 0.0,
                'c_tebakan' => 0.25,
            ]);
        }
    }

    private function adminId(): ?int
    {
        return User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->value('id');
    }
}
