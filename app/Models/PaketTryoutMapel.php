<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris isi paket tryout: sebuah mapel beserta paket soal yang
 * dipakainya. Jumlahnya mengikuti katalog mapel, sehingga paket tryout bisa
 * memuat seluruh mapel yang tersedia dan peserta bebas memilih dua mapel
 * pilihan yang ia kerjakan sendiri.
 */
class PaketTryoutMapel extends Model
{
    public const MENIT_WAJIB = 75;

    public const MENIT_PILIHAN = 60;

    protected $table = 'paket_tryout_mapel';

    protected $fillable = [
        'paket_tryout_id',
        'mapel_id',
        'paket_soal_id',
        'menit',
    ];

    protected $casts = [
        'menit' => 'integer',
    ];

    /**
     * Batas waktu bawaan sebuah baris: 75 menit untuk mapel wajib, 60 menit
     * untuk mapel pilihan apa pun jenisnya. Nilai inilah yang diisikan form
     * saat admin baru membuka halaman, dan yang tertulis bila tidak disentuh.
     */
    public static function menitBawaan(string $jenis): int
    {
        return $jenis === Mapel::JENIS_WAJIB ? self::MENIT_WAJIB : self::MENIT_PILIHAN;
    }

    public function paketTryout(): BelongsTo
    {
        return $this->belongsTo(PaketTryout::class);
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class);
    }

    public function paketSoal(): BelongsTo
    {
        return $this->belongsTo(PaketSoal::class);
    }
}
