<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KompetensiDasar extends Model
{
    use HasFactory, SoftDeletes;

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
}