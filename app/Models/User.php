<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PESERTA = 'peserta';

    protected $fillable = [
        'nama_lengkap',
        'email',
        'password',
        'sekolah',
        'tingkat',
        'jurusan',
        'role',
        'session_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPeserta(): bool
    {
        return $this->role === self::ROLE_PESERTA;
    }

    public function soalDibuat(): HasMany
    {
        return $this->hasMany(Soal::class, 'created_by');
    }

    public function paketSoalDibuat(): HasMany
    {
        return $this->hasMany(PaketSoal::class, 'created_by');
    }

    public function paketTryoutDibuat(): HasMany
    {
        return $this->hasMany(PaketTryout::class, 'created_by');
    }

    public function percobaan(): HasMany
    {
        return $this->hasMany(Percobaan::class);
    }

    public function riwayatPengerjaan(): HasMany
    {
        return $this->hasMany(RiwayatPengerjaan::class);
    }

    public function hasilTryout(): HasMany
    {
        return $this->hasMany(HasilTryout::class);
    }

    public function trackingKompetensi(): HasMany
    {
        return $this->hasMany(TrackingKompetensi::class);
    }

    public function trackingMapel(): HasMany
    {
        return $this->hasMany(TrackingMapel::class);
    }

    /**
     * Mapel yang dipilih peserta di halaman profil (butir 96).
     *
     * Sifatnya filter tampilan: pilihan menentukan mapel apa yang ditawarkan
     * di Analisis dan form Latihan, sedangkan seluruh pengerjaan tetap
     * dicatat untuk semua mapel.
     */
    public function mapelPilihan(): BelongsToMany
    {
        return $this->belongsToMany(Mapel::class, 'mapel_pilihan_user')->orderBy('kode');
    }

    /**
     * Menyaring daftar mapel menurut pilihan peserta ini.
     *
     * Mapel wajib **tidak pernah tersaring**: ia bukan sesuatu yang dipilih
     * (halaman profil menampilkan namanya sebagai keterangan, tanpa kotak),
     * sehingga memasukkannya ke daftar pilihan justru menempatkan hal yang
     * sama di dua tempat. Ia ikut tampil berbarengan pilihan peserta.
     *
     * Dua hal tetap dijaga. Peserta yang belum memilih apa pun — termasuk
     * seluruh akun yang lahir sebelum fitur ini ada — tetap melihat seluruh
     * mapel, jadi tidak ada yang mendadak kehilangan isi halaman. Karena itu
     * aturan "kosong = semua" ada di `mapelTampil()` saja, sedangkan yang
     * hanya ingin memakai wajib ∪ pilihan tanpa pengecualian itu memanggil
     * `mapelTerpilih()`.
     *
     * @param  Collection<int, Mapel>  $mapels  seluruh mapel yang tidak dihapus
     * @return Collection<int, Mapel>
     */
    public function mapelTampil(Collection $mapels): Collection
    {
        if ($this->mapelPilihan->isEmpty()) {
            return $mapels;
        }

        return $this->mapelTerpilih($mapels);
    }

    /**
     * Seluruh mapel wajib beserta mapel pilihan yang dipilih peserta ini —
     * tanpa fallback "kosong = semua" yang dimiliki `mapelTampil()`.
     *
     * Pemakainya adalah rangkuman di dashboard (butir C2): daftar itu
     * merangkum yang sedang dikejar, jadi peserta yang belum memilih apa pun
     * harusnya hanya melihat mapel wajibnya, bukan seluruh katalog mapel yang
     * ditawarkan form latihan. Jumlahnya pun sekaligus terbatas dengan
     * sendirinya — wajib selalu ada, pilihan maksimum dua.
     *
     * @param  Collection<int, Mapel>  $mapels  seluruh mapel yang tidak dihapus
     * @return Collection<int, Mapel>
     */
    public function mapelTerpilih(Collection $mapels): Collection
    {
        $pilihan = $this->mapelPilihan->pluck('id');

        $wajib = $mapels
            ->filter(fn (Mapel $mapel): bool => $mapel->jenis === Mapel::JENIS_WAJIB)
            ->pluck('id');

        return $mapels
            ->filter(fn (Mapel $mapel): bool => $wajib->contains($mapel->getKey())
                || $pilihan->contains($mapel->getKey()))
            ->values();
    }
}
