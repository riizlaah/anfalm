<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Percobaan extends Model
{
    use HasFactory;

    protected $table = 'percobaan';

    public const JENIS_TRYOUT = 'tryout';
    public const JENIS_LATIHAN = 'latihan';

    public const STATUS_BERJALAN = 'berjalan';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'user_id',
        'jenis',
        'paket_tryout_id',
        'mapel_id',
        'jumlah_soal',
        'batas_waktu_menit',
        'status',
        'daftar_soal',
        'posisi_soal',
        'urutan_mapel',
        'waktu_mulai',
        'waktu_selesai',
        'durasi_detik',
    ];

    protected function casts(): array
    {
        return [
            'daftar_soal' => 'array',
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
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

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class);
    }

    public function riwayatPengerjaan(): HasMany
    {
        return $this->hasMany(RiwayatPengerjaan::class, 'percobaan_id');
    }

    public function isBerjalan(): bool
    {
        return $this->status === self::STATUS_BERJALAN;
    }

    public function isSelesai(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }
}