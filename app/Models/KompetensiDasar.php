<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KompetensiDasar extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Level kompetensi yang dipakai TKA. Kunci dipakai sebagai nilai tersimpan
     * di kolom `level_kognitif`, label dipakai untuk ditampilkan.
     *
     * @var array<string, string>
     */
    public const LEVEL_KOGNITIF = [
        'pengetahuan_dan_pemahaman' => 'Pengetahuan dan Pemahaman',
        'penerapan' => 'Penerapan',
        'penalaran' => 'Penalaran',
    ];

    protected $table = 'kompetensi_dasar';

    protected $fillable = [
        'mapel_id',
        'kode_kompetensi',
        'deskripsi',
        'materi_pokok',
        'level_kognitif',
        'batasan',
    ];

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class);
    }

    public function soal(): HasMany
    {
        return $this->hasMany(Soal::class);
    }

    protected function levelKognitifLabel(): Attribute
    {
        return Attribute::get(fn (): string => self::LEVEL_KOGNITIF[$this->level_kognitif] ?? $this->level_kognitif);
    }
}
