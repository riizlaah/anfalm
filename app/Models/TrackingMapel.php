<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingMapel extends Model
{
    public $incrementing = false;

    protected $table = 'tracking_mapel';

    protected $fillable = [
        'user_id',
        'mapel_id',
        'theta_estimasi',
        'level_kompetensi',
        'total_tryout_diikuti',
        'rata_rata_skor_irt',
        'last_updated',
    ];

    protected function casts(): array
    {
        return [
            'theta_estimasi' => 'float',
            'total_tryout_diikuti' => 'integer',
            'rata_rata_skor_irt' => 'float',
            'last_updated' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class);
    }
}