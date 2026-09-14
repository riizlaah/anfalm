<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Mapel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@anfalm.test'],
            [
                'nama_lengkap' => 'Administrator Anfalm',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
            ]
        );

        User::updateOrCreate(
            ['email' => 'siswa@anfalm.test'],
            [
                'nama_lengkap' => 'Siswa Contoh',
                'password' => Hash::make('password'),
                'sekolah' => 'SMK Negeri 1 Jakarta',
                'tingkat' => 'SMK',
                'jurusan' => 'Rekayasa Perangkat Lunak',
                'role' => User::ROLE_PESERTA,
            ]
        );

        $this->seedMapelDefault();
    }

    private function seedMapelDefault(): void
    {
        $mapel = [
            ['kode' => 'MTK', 'nama' => 'Matematika', 'jenis' => Mapel::JENIS_WAJIB],
            ['kode' => 'BIN', 'nama' => 'Bahasa Indonesia', 'jenis' => Mapel::JENIS_WAJIB],
            ['kode' => 'BIG', 'nama' => 'Bahasa Inggris', 'jenis' => Mapel::JENIS_WAJIB],
            ['kode' => 'PKK', 'nama' => 'Produk Kreatif dan Kewirausahaan', 'jenis' => Mapel::JENIS_PILIHAN_KEJURUAN, 'is_pkk' => true],
            ['kode' => 'PW', 'nama' => 'Pemrograman Web', 'jenis' => Mapel::JENIS_PILIHAN_KEJURUAN],
            ['kode' => 'BD', 'nama' => 'Basis Data', 'jenis' => Mapel::JENIS_PILIHAN_KEJURUAN],
            ['kode' => 'FIS', 'nama' => 'Fisika', 'jenis' => Mapel::JENIS_PILIHAN_UMUM],
            ['kode' => 'KIM', 'nama' => 'Kimia', 'jenis' => Mapel::JENIS_PILIHAN_UMUM],
            ['kode' => 'SEJ', 'nama' => 'Sejarah', 'jenis' => Mapel::JENIS_PILIHAN_UMUM],
        ];

        foreach ($mapel as $data) {
            Mapel::updateOrCreate(
                ['kode' => $data['kode']],
                [
                    'nama' => $data['nama'],
                    'tingkat' => 'SMK',
                    'jenis' => $data['jenis'],
                    'is_pkk' => $data['is_pkk'] ?? false,
                ]
            );
        }
    }
}
