<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaketTryout extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'paket_tryout';

    public const TINGKAT_SD = 'SD';

    public const TINGKAT_SMP = 'SMP';

    public const TINGKAT_SMA = 'SMA';

    public const TINGKAT_SMK = 'SMK';

    protected $fillable = [
        'nama_paket',
        'deskripsi',
        'tingkat',
        'batas_waktu_menit',
        'mapel_wajib_1',
        'mapel_wajib_2',
        'mapel_wajib_3',
        'mapel_pilihan_1',
        'mapel_pilihan_2',
        'paket_soal_wajib_1_id',
        'paket_soal_wajib_2_id',
        'paket_soal_wajib_3_id',
        'paket_soal_pilihan_1_id',
        'paket_soal_pilihan_2_id',
        'created_by',
    ];

    public function created_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wajib1(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'mapel_wajib_1');
    }

    public function wajib2(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'mapel_wajib_2');
    }

    public function wajib3(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'mapel_wajib_3');
    }

    public function pilihan1(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'mapel_pilihan_1');
    }

    public function pilihan2(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'mapel_pilihan_2');
    }

    public function soalWajib1(): BelongsTo
    {
        return $this->belongsTo(PaketSoal::class, 'paket_soal_wajib_1_id');
    }

    public function soalWajib2(): BelongsTo
    {
        return $this->belongsTo(PaketSoal::class, 'paket_soal_wajib_2_id');
    }

    public function soalWajib3(): BelongsTo
    {
        return $this->belongsTo(PaketSoal::class, 'paket_soal_wajib_3_id');
    }

    public function soalPilihan1(): BelongsTo
    {
        return $this->belongsTo(PaketSoal::class, 'paket_soal_pilihan_1_id');
    }

    public function soalPilihan2(): BelongsTo
    {
        return $this->belongsTo(PaketSoal::class, 'paket_soal_pilihan_2_id');
    }

    public function getSemuaMapelIdsAttribute(): array
    {
        return array_filter([
            $this->mapel_wajib_1, $this->mapel_wajib_2, $this->mapel_wajib_3,
            $this->mapel_pilihan_1, $this->mapel_pilihan_2,
        ]);
    }
}
