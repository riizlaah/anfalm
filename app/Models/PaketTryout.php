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
        'batas_waktu_menit',
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
     * Nama seluruh mapel pada paket ini, dipisah koma — dipakai kartu dan
     * tabel daftar tryout yang hanya perlu ikhtisar, bukan perinciannya.
     */
    public function namaMapel(): string
    {
        return $this->daftarMapelUrut()->pluck('mapel.nama')->implode(', ');
    }

    /**
     * Menyusun ulang isi paket: satu baris per mapel dengan paket soalnya.
     *
     * @param  array<int|string, int|string>  $paketPerMapel  peta `mapel_id` => `paket_soal_id`
     */
    public function susunIsiMapel(array $paketPerMapel): void
    {
        DB::transaction(function () use ($paketPerMapel): void {
            $this->daftarMapel()
                ->whereNotIn('mapel_id', array_map('intval', array_keys($paketPerMapel)))
                ->delete();

            foreach ($paketPerMapel as $mapelId => $paketSoalId) {
                $this->daftarMapel()->updateOrCreate(
                    ['mapel_id' => (int) $mapelId],
                    ['paket_soal_id' => (int) $paketSoalId],
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
}
