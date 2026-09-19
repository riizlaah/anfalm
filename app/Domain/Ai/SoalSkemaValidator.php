<?php

namespace App\Domain\Ai;

use App\Models\Soal;

class SoalSkemaValidator
{
    public const MAKSIMAL_SOAL = 30;

    public const JUMLAH_OPSI = 5;

    /**
     * @param  array<string|int, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws SoalSkemaException
     */
    public function validate(array $data): array
    {
        $daftarSoal = $data['daftar_soal'] ?? null;

        if (! is_array($daftarSoal) && array_is_list($data)) {
            $daftarSoal = $data;
        }

        if (! is_array($daftarSoal)) {
            throw new SoalSkemaException('AI tidak mengembalikan daftar soal yang valid.');
        }

        if (count($daftarSoal) > self::MAKSIMAL_SOAL) {
            throw new SoalSkemaException('Jumlah soal melebihi batas maksimal '.self::MAKSIMAL_SOAL.' soal per generate.');
        }

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $paket = is_array($data['paket_soal'] ?? null) ? $data['paket_soal'] : [];
        $namaPaket = is_string($paket['nama'] ?? null) ? trim($paket['nama']) : '';
        $deskripsi = is_string($paket['deskripsi'] ?? null) ? $paket['deskripsi'] : null;

        $daftarNormal = [];
        $dibuang = [];

        foreach ($daftarSoal as $index => $rawSoal) {
            if (! is_array($rawSoal)) {
                $dibuang[] = ['id' => 'Urutan #'.($index + 1), 'alasan' => 'Bukan objek soal.'];

                continue;
            }

            $hasil = $this->normalisasiSoal($rawSoal, $index);

            if ($hasil['ok'] === false) {
                $id = is_string($rawSoal['id_soal_sementara'] ?? null)
                    ? $rawSoal['id_soal_sementara']
                    : 'Urutan #'.($index + 1);
                $dibuang[] = ['id' => $id, 'alasan' => $hasil['alasan']];

                continue;
            }

            $daftarNormal[] = $hasil['soal'];
        }

        if ($daftarNormal === []) {
            throw new SoalSkemaException('AI tidak mengembalikan daftar soal yang valid.');
        }

        $namaPaketFallback = is_string($metadata['mapel'] ?? null)
            ? 'Paket '.$metadata['mapel']
            : 'Paket Soal AI';

        return [
            'metadata' => $metadata,
            'nama_paket' => $namaPaket !== '' ? $namaPaket : $namaPaketFallback,
            'deskripsi' => $deskripsi,
            'jumlah_soal' => count($daftarNormal),
            'daftar_soal' => $daftarNormal,
            'soal_dibuang' => $dibuang,
        ];
    }

    /**
     * @param  array<string, mixed>  $soal
     * @return array{ok: bool, soal: array<string, mixed>|null, alasan: string}
     */
    private function normalisasiSoal(array $soal, int $index): array
    {
        $tipe = $soal['tipe_soal'] ?? null;

        if (! in_array($tipe, [Soal::TIPE_PG, Soal::TIPE_PG_KOMPLEKS, Soal::TIPE_PG_KATEGORI], true)) {
            return ['ok' => false, 'soal' => null, 'alasan' => 'tipe_soal tidak dikenali.'];
        }

        $pertanyaan = $soal['pertanyaan'] ?? null;

        if (! is_string($pertanyaan) || trim($pertanyaan) === '') {
            return ['ok' => false, 'soal' => null, 'alasan' => 'pertanyaan kosong.'];
        }

        $kd = $soal['kompetensi_dasar'] ?? null;
        $kodeKd = is_array($kd) ? ($kd['kode'] ?? null) : ($kd ?? null);
        $kodeKd = is_string($kodeKd) ? $kodeKd : null;

        $irt = is_array($soal['parameter_irt'] ?? null) ? $soal['parameter_irt'] : [];

        $normal = [
            'id_soal_sementara' => is_string($soal['id_soal_sementara'] ?? null)
                ? $soal['id_soal_sementara']
                : 'S'.($index + 1),
            'tipe_soal' => $tipe,
            'pertanyaan' => $pertanyaan,
            'gambar_url' => is_string($soal['gambar_url'] ?? null) ? $soal['gambar_url'] : null,
            'pembahasan' => is_string($soal['pembahasan'] ?? null) ? $soal['pembahasan'] : null,
            'kompetensi_dasar_kode' => $kodeKd,
            'a_diskriminasi' => $this->angkaNullable($irt['a_diskriminasi'] ?? null),
            'b_kesulitan' => $this->angkaNullable($irt['b_kesulitan'] ?? null),
            'c_tebakan' => $this->angkaNullable($irt['c_tebakan'] ?? null),
            'daftar_kategori' => null,
            'opsi_jawaban' => [],
            'pernyataan_kategori' => [],
        ];

        if ($tipe === Soal::TIPE_PG_KATEGORI) {
            return $this->normalisasiKategori($normal, $soal);
        }

        return $this->normalisasiOpsi($normal, $soal);
    }

    /**
     * @param  array<string, mixed>  $normal
     * @param  array<string, mixed>  $soal
     * @return array{ok: bool, soal: array<string, mixed>|null, alasan: string}
     */
    private function normalisasiKategori(array $normal, array $soal): array
    {
        $kategori = $soal['kategori_pg_kategori'] ?? ['Benar', 'Salah'];
        $kategori = is_array($kategori) && $kategori !== []
            ? array_values(array_map('strval', $kategori))
            : ['Benar', 'Salah'];

        $pernyataan = is_array($soal['pernyataan_kategori'] ?? null) ? $soal['pernyataan_kategori'] : [];

        $pernyataanNormal = [];

        foreach ($pernyataan as $pIndex => $p) {
            if (! is_array($p) || ! is_string($p['teks'] ?? null) || trim($p['teks']) === '') {
                continue;
            }

            $kategoriBenar = $p['kategori_benar'] ?? null;

            if (! is_string($kategoriBenar) || ! in_array($kategoriBenar, $kategori, true)) {
                continue;
            }

            $pirt = is_array($p['parameter_irt'] ?? null) ? $p['parameter_irt'] : [];

            $pernyataanNormal[] = [
                'teks_pernyataan' => $p['teks'],
                'kategori_benar' => $kategoriBenar,
                'urutan' => $pIndex + 1,
                'a_diskriminasi' => $this->angkaNullable($pirt['a_diskriminasi'] ?? null),
                'b_kesulitan' => $this->angkaNullable($pirt['b_kesulitan'] ?? null),
                'c_tebakan' => $this->angkaNullable($pirt['c_tebakan'] ?? null),
            ];
        }

        if (count($pernyataanNormal) < 2) {
            return ['ok' => false, 'soal' => null, 'alasan' => 'pg_kategori membutuhkan minimal 2 pernyataan valid.'];
        }

        $normal['daftar_kategori'] = $kategori;
        $normal['pernyataan_kategori'] = array_slice($pernyataanNormal, 0, 5);

        return ['ok' => true, 'soal' => $normal, 'alasan' => ''];
    }

    /**
     * @param  array<string, mixed>  $normal
     * @param  array<string, mixed>  $soal
     * @return array{ok: bool, soal: array<string, mixed>|null, alasan: string}
     */
    private function normalisasiOpsi(array $normal, array $soal): array
    {
        $opsi = is_array($soal['opsi_jawaban'] ?? null) ? $soal['opsi_jawaban'] : [];

        $opsiNormal = [];

        foreach (array_slice($opsi, 0, self::JUMLAH_OPSI) as $oIndex => $opsiItem) {
            if (! is_array($opsiItem)) {
                continue;
            }

            $oirt = is_array($opsiItem['parameter_irt'] ?? null) ? $opsiItem['parameter_irt'] : [];

            $opsiNormal[] = [
                'teks_opsi' => is_string($opsiItem['teks'] ?? null) ? $opsiItem['teks'] : '',
                'is_benar' => (bool) ($opsiItem['is_benar'] ?? false),
                'urutan' => $oIndex + 1,
                'a_diskriminasi' => $this->angkaNullable($oirt['a_diskriminasi'] ?? null),
                'b_kesulitan' => $this->angkaNullable($oirt['b_kesulitan'] ?? null),
                'c_tebakan' => $this->angkaNullable($oirt['c_tebakan'] ?? null),
            ];
        }

        if (count($opsiNormal) < 2) {
            return ['ok' => false, 'soal' => null, 'alasan' => 'soal membutuhkan minimal 2 opsi.'];
        }

        while (count($opsiNormal) < self::JUMLAH_OPSI) {
            $opsiNormal[] = [
                'teks_opsi' => '',
                'is_benar' => false,
                'urutan' => count($opsiNormal) + 1,
                'a_diskriminasi' => null,
                'b_kesulitan' => null,
                'c_tebakan' => null,
            ];
        }

        $normal['opsi_jawaban'] = $opsiNormal;

        return ['ok' => true, 'soal' => $normal, 'alasan' => ''];
    }

    private function angkaNullable(mixed $nilai): ?float
    {
        return is_numeric($nilai) ? (float) $nilai : null;
    }
}
