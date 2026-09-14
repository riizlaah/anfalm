<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Soal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'soal';

    public const TIPE_PG = 'pg';

    public const TIPE_PG_KOMPLEKS = 'pg_kompleks';

    public const TIPE_PG_KATEGORI = 'pg_kategori';

    protected $fillable = [
        'kompetensi_dasar_id',
        'tipe_soal',
        'pertanyaan',
        'gambar_url',
        'pembahasan',
        'daftar_kategori',
        'a_diskriminasi',
        'b_kesulitan',
        'c_tebakan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'daftar_kategori' => 'array',
            'a_diskriminasi' => 'float',
            'b_kesulitan' => 'float',
            'c_tebakan' => 'float',
        ];
    }

    public function kompetensiDasar(): BelongsTo
    {
        return $this->belongsTo(KompetensiDasar::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function opsiJawaban(): HasMany
    {
        return $this->hasMany(OpsiJawaban::class)->orderBy('urutan');
    }

    public function pernyataanKategori(): HasMany
    {
        return $this->hasMany(PernyataanKategori::class)->orderBy('urutan');
    }

    public function isPG(): bool
    {
        return $this->tipe_soal === self::TIPE_PG;
    }

    public function isPGKompleks(): bool
    {
        return $this->tipe_soal === self::TIPE_PG_KOMPLEKS;
    }

    public function isPGKategori(): bool
    {
        return $this->tipe_soal === self::TIPE_PG_KATEGORI;
    }
}
