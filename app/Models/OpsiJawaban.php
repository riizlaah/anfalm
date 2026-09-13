<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsiJawaban extends Model
{
    public $timestamps = false;

    protected $table = 'opsi_jawaban';

    protected $fillable = [
        'soal_id',
        'teks_opsi',
        'is_benar',
        'urutan',
        'a_diskriminasi',
        'b_kesulitan',
        'c_tebakan',
    ];

    protected function casts(): array
    {
        return [
            'is_benar' => 'boolean',
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