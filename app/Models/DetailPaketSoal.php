<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailPaketSoal extends Model
{
    public $timestamps = false;

    protected $table = 'detail_paket_soal';

    protected $fillable = [
        'paket_soal_id',
        'soal_id',
    ];

    public function paketSoal(): BelongsTo
    {
        return $this->belongsTo(PaketSoal::class);
    }
}
