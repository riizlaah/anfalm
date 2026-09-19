<?php

namespace App\Domain\Ai;

class AiFake implements AiProvider
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private array $payload) {}

    public function generate(string $prompt): string
    {
        return json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Fixture respons AI yang valid untuk unit dan feature test.
     *
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        return [
            'metadata' => [
                'mapel' => 'Matematika',
                'jumlah_soal' => 3,
                'tingkat_kesulitan' => 'campuran',
            ],
            'paket_soal' => [
                'nama' => 'Paket AI Matematika',
                'deskripsi' => 'Dihasilkan AI.',
            ],
            'daftar_soal' => [
                [
                    'id_soal_sementara' => 'S001',
                    'tipe_soal' => 'pg',
                    'pertanyaan' => 'Hasil dari 2^3 x 2^5 adalah ...',
                    'gambar_url' => null,
                    'opsi_jawaban' => [
                        ['teks' => '2^8', 'is_benar' => true, 'urutan' => 1, 'parameter_irt' => ['a_diskriminasi' => 1.2, 'b_kesulitan' => -0.5, 'c_tebakan' => 0.2]],
                        ['teks' => '2^15', 'is_benar' => false, 'urutan' => 2, 'parameter_irt' => []],
                        ['teks' => '2^6', 'is_benar' => false, 'urutan' => 3, 'parameter_irt' => []],
                        ['teks' => '4^8', 'is_benar' => false, 'urutan' => 4, 'parameter_irt' => []],
                        ['teks' => '8^5', 'is_benar' => false, 'urutan' => 5, 'parameter_irt' => []],
                    ],
                    'pembahasan' => 'a^m x a^n = a^(m+n).',
                    'kompetensi_dasar' => ['kode' => '3.1', 'deskripsi' => 'Bilangan berpangkat'],
                    'parameter_irt' => ['a_diskriminasi' => 1.0, 'b_kesulitan' => -0.3, 'c_tebakan' => 0.2],
                ],
                [
                    'id_soal_sementara' => 'S002',
                    'tipe_soal' => 'pg_kompleks',
                    'pertanyaan' => 'Pernyataan yang benar tentang pangkat:',
                    'gambar_url' => null,
                    'opsi_jawaban' => [
                        ['teks' => 'a^0 = 1 untuk a != 0', 'is_benar' => true, 'urutan' => 1, 'parameter_irt' => ['a_diskriminasi' => 1.5, 'b_kesulitan' => -0.8, 'c_tebakan' => 0.1]],
                        ['teks' => 'a^-n = 1/a^n', 'is_benar' => true, 'urutan' => 2, 'parameter_irt' => ['a_diskriminasi' => 1.3, 'b_kesulitan' => -0.3, 'c_tebakan' => 0.15]],
                        ['teks' => '(a^m)^n = a^(m+n)', 'is_benar' => false, 'urutan' => 3, 'parameter_irt' => []],
                        ['teks' => 'a^m x a^n = a^(m*n)', 'is_benar' => false, 'urutan' => 4, 'parameter_irt' => []],
                        ['teks' => '(ab)^n = a^n b^n', 'is_benar' => true, 'urutan' => 5, 'parameter_irt' => []],
                    ],
                    'pembahasan' => 'Pembahasan PG kompleks.',
                    'kompetensi_dasar' => ['kode' => '3.1', 'deskripsi' => 'Bilangan berpangkat'],
                    'parameter_irt' => ['a_diskriminasi' => 1.1, 'b_kesulitan' => 0.1, 'c_tebakan' => 0.15],
                ],
                [
                    'id_soal_sementara' => 'S003',
                    'tipe_soal' => 'pg_kategori',
                    'pertanyaan' => 'Kategorikan setiap pernyataan berikut:',
                    'gambar_url' => null,
                    'kategori_pg_kategori' => ['Benar', 'Salah'],
                    'pernyataan_kategori' => [
                        ['teks' => 'HTML adalah bahasa markup.', 'kategori_benar' => 'Benar', 'urutan' => 1, 'parameter_irt' => ['a_diskriminasi' => 1.0, 'b_kesulitan' => -0.4, 'c_tebakan' => 0.25]],
                        ['teks' => 'JavaScript berjalan di server saja.', 'kategori_benar' => 'Salah', 'urutan' => 2, 'parameter_irt' => ['a_diskriminasi' => 1.6, 'b_kesulitan' => 0.3, 'c_tebakan' => 0.15]],
                    ],
                    'pembahasan' => 'Pembahasan kategori.',
                    'kompetensi_dasar' => ['kode' => '3.2', 'deskripsi' => 'Konsep dasar TI'],
                    'parameter_irt' => ['a_diskriminasi' => 1.2, 'b_kesulitan' => -0.05, 'c_tebakan' => 0.22],
                ],
            ],
        ];
    }
}
