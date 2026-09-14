<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingKompetensi extends Model
{
    public $incrementing = false;

    protected $table = 'tracking_kompetensi';

    protected $fillable = [
        'user_id',
        'kompetensi_dasar_id',
        'total_soal_dikerjakan',
        'total_benar',
        'persentase_benar',
        'theta_estimasi',
        'theta_se',
        'last_updated',
    ];

    protected function casts(): array
    {
        return [
            'total_soal_dikerjakan' => 'integer',
            'total_benar' => 'integer',
            'persentase_benar' => 'float',
            'theta_estimasi' => 'float',
            'theta_se' => 'float',
            'last_updated' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kompetensiDasar(): BelongsTo
    {
        return $this->belongsTo(KompetensiDasar::class);
    }
}
