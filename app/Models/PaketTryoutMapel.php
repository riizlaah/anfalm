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
    protected $table = 'paket_tryout_mapel';

    protected $fillable = [
        'paket_tryout_id',
        'mapel_id',
        'paket_soal_id',
    ];

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
