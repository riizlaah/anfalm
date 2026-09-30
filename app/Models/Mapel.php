<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mapel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mapel';

    public const TINGKAT_SD = 'SD';

    public const TINGKAT_SMP = 'SMP';

    public const TINGKAT_SMA = 'SMA';

    public const TINGKAT_SMK = 'SMK';

    public const TINGKAT_ALL = 'all';

    public const JENIS_WAJIB = 'wajib';

    public const JENIS_PILIHAN_UMUM = 'pilihan_umum';

    public const JENIS_PILIHAN_KEJURUAN = 'pilihan_kejuruan';

    protected $fillable = [
        'kode',
        'nama',
        'tingkat',
        'jenis',
        'is_pkk',
    ];

    protected function casts(): array
    {
        return [
            'is_pkk' => 'boolean',
        ];
    }

    public function kompetensiDasars(): HasMany
    {
        return $this->hasMany(KompetensiDasar::class);
    }

    public function paketSoal(): HasMany
    {
        return $this->hasMany(PaketSoal::class);
    }

    /**
     * Soal dijangkau lewat KD, jadi hubungan mapel ke soal bersifat tidak langsung.
     */
    public function soals(): HasManyThrough
    {
        return $this->hasManyThrough(Soal::class, KompetensiDasar::class, 'mapel_id', 'kompetensi_dasar_id');
    }
}
