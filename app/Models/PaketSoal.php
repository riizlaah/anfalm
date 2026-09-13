<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaketSoal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'paket_soal';

    protected $fillable = [
        'nama_paket',
        'deskripsi',
        'mapel_id',
        'created_by',
    ];

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function soal(): BelongsToMany
    {
        return $this->belongsToMany(Soal::class, 'detail_paket_soal');
    }
}