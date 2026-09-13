<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_PESERTA = 'peserta';

    protected $fillable = [
        'nama_lengkap',
        'email',
        'password',
        'sekolah',
        'tingkat',
        'jurusan',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPeserta(): bool
    {
        return $this->role === self::ROLE_PESERTA;
    }

    public function soalDibuat(): HasMany
    {
        return $this->hasMany(Soal::class, 'created_by');
    }

    public function paketSoalDibuat(): HasMany
    {
        return $this->hasMany(PaketSoal::class, 'created_by');
    }

    public function paketTryoutDibuat(): HasMany
    {
        return $this->hasMany(PaketTryout::class, 'created_by');
    }

    public function percobaan(): HasMany
    {
        return $this->hasMany(Percobaan::class);
    }

    public function riwayatPengerjaan(): HasMany
    {
        return $this->hasMany(RiwayatPengerjaan::class);
    }

    public function hasilTryout(): HasMany
    {
        return $this->hasMany(HasilTryout::class);
    }

    public function trackingKompetensi(): HasMany
    {
        return $this->hasMany(TrackingKompetensi::class);
    }

    public function trackingMapel(): HasMany
    {
        return $this->hasMany(TrackingMapel::class);
    }
}