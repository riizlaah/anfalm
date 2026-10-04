<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaketTryout extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'paket_tryout';

    public const TINGKAT_SD = 'SD';

    public const TINGKAT_SMP = 'SMP';

    public const TINGKAT_SMA = 'SMA';

    public const TINGKAT_SMK = 'SMK';

    protected $fillable = [
        'nama_paket',
        'deskripsi',
        'tingkat',
        'created_by',
    ];

    public function created_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Seluruh mapel yang masuk paket ini beserta paket soalnya — satu baris
     * per mapel, bukan lima slot tetap, sehingga jumlahnya mengikuti katalog.
     */
    public function daftarMapel(): HasMany
    {
        return $this->hasMany(PaketTryoutMapel::class);
    }

    /**
     * Isi paket terurut seperti peserta mengerjakannya: seluruh mapel wajib
     * dahulu menurut kode mapel, baru mapel pilihan menurut kode mapel.
     *
     * Relasinya ikut dimuat di sini supaya penyusunan ulang tidak menambah
     * query per baris pada halaman daftar dan kartu tryout.
     *
     * @return Collection<int, PaketTryoutMapel>
     */
    public function daftarMapelUrut(): Collection
    {
        $isi = $this->daftarMapel->loadMissing('mapel')->filter(
            fn (PaketTryoutMapel $baris): bool => $baris->mapel !== null
        );

        $urut = fn (Collection $baris): Collection => $baris
            ->sortBy(fn (PaketTryoutMapel $baris): string => $baris->mapel->kode)
            ->values();

        $wajib = $urut($isi->filter(
            fn (PaketTryoutMapel $baris): bool => $baris->mapel->jenis === Mapel::JENIS_WAJIB
        ));
        $pilihan = $urut($isi->filter(
            fn (PaketTryoutMapel $baris): bool => $baris->mapel->jenis !== Mapel::JENIS_WAJIB
        ));

        return $wajib->concat($pilihan);
    }

    /**
     * Percobaan peserta atas paket ini — baik yang masih berjalan maupun yang
     * sudah selesai. Inilah yang membuat paketnya tidak bisa dihapus sebelum
     * riwayatnya direset (S2).
     */
    public function percobaan(): HasMany
    {
        return $this->hasMany(Percobaan::class);
    }

    /** Nilai akhir tiap peserta pada paket ini, dipakai daftar leaderboard. */
    public function hasilTryout(): HasMany
    {
        return $this->hasMany(HasilTryout::class);
    }

    /** Jawaban per soal yang menunjuk paket ini, termasuk yang sedang berjalan. */
    public function riwayatPengerjaan(): HasMany
    {
        return $this->hasMany(RiwayatPengerjaan::class);
    }

    /**
     * Nama seluruh mapel pada paket ini, dipisah koma — dipakai kartu dan
     * tabel daftar tryout yang hanya perlu ikhtisar, bukan perinciannya.
     */
    public function namaMapel(): string
    {
        return $this->daftarMapelUrut()->pluck('mapel.nama')->implode(', ');
    }

    /**
     * Menyusun ulang isi paket: satu baris per mapel, lengkap dengan paket
     * soal yang dipakainya beserta batas waktu mengerjakan baris itu.
     *
     * @param  array<int|string, array{paket_soal_id: int|string, menit: int|string}>  $isiPerMapel
     *                                                                                               peta `mapel_id` => isian barisnya
     */
    public function susunIsiMapel(array $isiPerMapel): void
    {
        $barisDipakai = array_map('intval', array_keys($isiPerMapel));

        DB::transaction(function () use ($isiPerMapel, $barisDipakai): void {
            $this->daftarMapel()
                ->whereNotIn('mapel_id', $barisDipakai)
                ->delete();

            foreach ($isiPerMapel as $mapelId => $isi) {
                $this->daftarMapel()->updateOrCreate(
                    ['mapel_id' => (int) $mapelId],
                    [
                        'paket_soal_id' => (int) $isi['paket_soal_id'],
                        'menit' => (int) $isi['menit'],
                    ],
                );
            }
        });
    }

    /**
     * Label tingkat untuk ditampilkan. Tingkat SMA dan SMK kini menyatu
     * sebagai satu sasaran, sehingga keduanya ditulis sama; nilai yang
     * tersimpan di kolom tetap tidak diubah.
     */
    public function labelTingkat(): string
    {
        return match ($this->tingkat) {
            self::TINGKAT_SMA, self::TINGKAT_SMK => 'SMA/SMK/Sederajat',
            default => (string) $this->tingkat,
        };
    }

    /**
     * Waktu pengerjaan untuk ditampilkan — batas tiap mapel, bukan satu angka
     * untuk seluruh tryout. Bila seluruh baris sepakat satu angka, ditulis
     * ringkas "75 menit per mapel"; kalau berbeda, dipisah per jenis mapelnya.
     */
    public function labelWaktuPerMapel(): string
    {
        $isi = $this->daftarMapelUrut();

        if ($isi->isEmpty()) {
            return '—';
        }

        $sekali = $isi->pluck('menit')->unique()->sort()->values();

        if ($sekali->count() === 1) {
            return $sekali->first().' menit per mapel';
        }

        $wajib = $this->menitTerpakai(
            $isi->filter(fn (PaketTryoutMapel $baris): bool => $baris->mapel->jenis === Mapel::JENIS_WAJIB)
        );
        $pilihan = $this->menitTerpakai(
            $isi->filter(fn (PaketTryoutMapel $baris): bool => $baris->mapel->jenis !== Mapel::JENIS_WAJIB)
        );

        $bagian = array_filter([
            $wajib === '' ? null : $wajib.' menit wajib',
            $pilihan === '' ? null : $pilihan.' menit pilihan',
        ]);

        return implode(' · ', $bagian);
    }

    /**
     * Angka menit unik pada sekelompok baris, terurut naik dan dipisah garis
     * miring supaya campuran nilai tetap terbaca ("75/90").
     *
     * @param  Collection<int, PaketTryoutMapel>  $baris
     */
    private function menitTerpakai(Collection $baris): string
    {
        return $baris->pluck('menit')->unique()->sort()->values()->implode('/');
    }
}
