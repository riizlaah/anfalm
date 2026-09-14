<?php

namespace App\Domain\Scoring;

use App\Models\OpsiJawaban;
use App\Models\PernyataanKategori;
use App\Models\Soal;
use InvalidArgumentException;

class ScoringService
{
    public function __construct(private readonly IrtService $irt) {}

    /**
     * Menskor satu soal berdasarkan jawaban mentah user (kredit parsial per-item).
     *
     * Format jawaban:
     * - pg:          ['opsi' => opsi_id]
     * - pg_kompleks: ['opsi' => [opsi_id, ...]]
     * - pg_kategori: ['kategori' => [pernyataan_id => kategori, ...]]
     *
     * Item yang tidak dijawab diabaikan (edge 6.2).
     */
    public function score(Soal $soal, ?array $jawaban): array
    {
        return match ($soal->tipe_soal) {
            Soal::TIPE_PG => $this->scorePG($soal, $jawaban),
            Soal::TIPE_PG_KOMPLEKS => $this->scorePGKompleks($soal, $jawaban),
            Soal::TIPE_PG_KATEGORI => $this->scorePGKategori($soal, $jawaban),
            default => throw new InvalidArgumentException("Tipe soal tidak dikenal: {$soal->tipe_soal}"),
        };
    }

    private function scorePG(Soal $soal, ?array $jawaban): array
    {
        $chosenId = isset($jawaban['opsi']) ? (int) $jawaban['opsi'] : null;

        if ($chosenId === null) {
            return $this->emptyResult($soal);
        }

        $option = $soal->opsiJawaban->firstWhere('id', $chosenId);
        $response = $option !== null ? ($option->is_benar ? 1 : 0) : 0;

        return $this->buildResult($soal, [[
            'tipe' => 'opsi',
            'id' => $chosenId,
            'urutan' => $option->urutan ?? null,
            'jawaban' => $chosenId,
            'resp' => $response,
            'a' => (float) $soal->a_diskriminasi,
            'b' => (float) $soal->b_kesulitan,
            'c' => (float) $soal->c_tebakan,
        ]]);
    }

    private function scorePGKompleks(Soal $soal, ?array $jawaban): array
    {
        $selected = isset($jawaban['opsi']) ? array_map('intval', (array) $jawaban['opsi']) : [];

        if ($selected === []) {
            return $this->emptyResult($soal);
        }

        $raw = $soal->opsiJawaban->map(fn (OpsiJawaban $option) => [
            'tipe' => 'opsi',
            'id' => $option->id,
            'urutan' => $option->urutan,
            'jawaban' => in_array($option->id, $selected, true),
            'resp' => in_array($option->id, $selected, true) === $option->is_benar ? 1 : 0,
            'a' => $option->a_diskriminasi,
            'b' => $option->b_kesulitan,
            'c' => $option->c_tebakan,
        ])->all();

        return $this->buildResult($soal, $raw);
    }

    private function scorePGKategori(Soal $soal, ?array $jawaban): array
    {
        $answers = $jawaban['kategori'] ?? [];
        $allowed = (array) $soal->daftar_kategori;

        if ($answers === []) {
            return $this->emptyResult($soal);
        }

        $raw = $soal->pernyataanKategori->map(fn (PernyataanKategori $pernyataan) => [
            'tipe' => 'pernyataan',
            'id' => $pernyataan->id,
            'urutan' => $pernyataan->urutan,
            'jawaban' => $answers[$pernyataan->id] ?? null,
            'resp' => $this->kategoriResponse($pernyataan, $answers, $allowed),
            'a' => $pernyataan->a_diskriminasi,
            'b' => $pernyataan->b_kesulitan,
            'c' => $pernyataan->c_tebakan,
        ])->all();

        return $this->buildResult($soal, $raw);
    }

    private function kategoriResponse(
        PernyataanKategori $pernyataan,
        array $answers,
        array $allowed
    ): ?int {
        $answer = $answers[$pernyataan->id] ?? null;

        if ($answer === null) {
            return null;
        }

        if ($allowed !== [] && ! in_array($answer, $allowed, true)) {
            return null;
        }

        return $answer === $pernyataan->kategori_benar ? 1 : 0;
    }

    private function buildResult(Soal $soal, array $raw): array
    {
        $jumlahBenar = 0;
        $jumlahSalah = 0;

        foreach ($raw as &$item) {
            $item['a'] = (float) ($item['a'] ?? IrtService::DEFAULT_A);
            $item['b'] = (float) ($item['b'] ?? IrtService::DEFAULT_B);
            $item['c'] = (float) ($item['c'] ?? IrtService::DEFAULT_C);

            if ($item['resp'] === 1) {
                $jumlahBenar++;
            } elseif ($item['resp'] === 0) {
                $jumlahSalah++;
            }
        }
        unset($item);

        return [
            'soal_id' => $soal->id,
            'jumlah_benar' => $jumlahBenar,
            'jumlah_salah' => $jumlahSalah,
            'total_soal' => $jumlahBenar + $jumlahSalah,
            'items' => $raw,
        ];
    }

    private function emptyResult(Soal $soal): array
    {
        return [
            'soal_id' => $soal->id,
            'jumlah_benar' => 0,
            'jumlah_salah' => 0,
            'total_soal' => 0,
            'items' => [],
        ];
    }
}
