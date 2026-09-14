<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPengerjaan extends Model
{
    use HasFactory;

    protected $table = 'riwayat_pengerjaan';

    protected $fillable = [
        'user_id',
        'percobaan_id',
        'soal_id',
        'paket_tryout_id',
        'jawaban_user',
        'is_benar',
        'mode',
        'waktu_mulai',
        'waktu_selesai',
        'durasi_detik',
        'theta_est_moment',
        'skor_irt',
    ];

    protected function casts(): array
    {
        return [
            'jawaban_user' => 'array',
            'is_benar' => 'boolean',
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
            'theta_est_moment' => 'float',
            'skor_irt' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function percobaan(): BelongsTo
    {
        return $this->belongsTo(Percobaan::class);
    }

    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class);
    }

    public function paketTryout(): BelongsTo
    {
        return $this->belongsTo(PaketTryout::class);
    }
}
