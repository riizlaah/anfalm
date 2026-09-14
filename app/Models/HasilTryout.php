<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilTryout extends Model
{
    use HasFactory;

    protected $table = 'hasil_tryout';

    protected $fillable = [
        'user_id',
        'paket_tryout_id',
        'theta_final',
        'standard_error',
        'skor_irt_total',
        'skor_konversi',
        'jumlah_benar',
        'jumlah_salah',
        'total_soal',
        'durasi_total',
        'selesai_pada',
    ];

    protected function casts(): array
    {
        return [
            'theta_final' => 'float',
            'standard_error' => 'float',
            'skor_irt_total' => 'float',
            'selesai_pada' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paketTryout(): BelongsTo
    {
        return $this->belongsTo(PaketTryout::class);
    }
}
