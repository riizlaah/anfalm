<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PernyataanKategori extends Model
{
    public $timestamps = false;

    protected $table = 'pernyataan_kategori';

    protected $fillable = [
        'soal_id',
        'teks_pernyataan',
        'kategori_benar',
        'a_diskriminasi',
        'b_kesulitan',
        'c_tebakan',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'a_diskriminasi' => 'float',
            'b_kesulitan' => 'float',
            'c_tebakan' => 'float',
        ];
    }

    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class);
    }
}