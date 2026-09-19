<?php

namespace App\Domain\Ai;

use Illuminate\Support\Facades\Log;
use JsonException;

class JsonRepairService
{
    private ?string $alasanTerakhir = null;

    /**
     * @return array<string|int, mixed>
     *
     * @throws JsonOutputException
     */
    public function parse(string $raw): array
    {
        $trimmed = trim($raw);

        $decoded = $this->cobaDecode($trimmed);
        if ($decoded !== null) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $trimmed, $matches) === 1) {
            $decoded = $this->cobaDecode(trim($matches[1]));
            if ($decoded !== null) {
                return $decoded;
            }
        }

        $span = $this->extractJsonSpan($trimmed);
        if ($span !== null) {
            $decoded = $this->cobaDecode($span);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        $diperbaiki = $this->cobaRepairTerpotong($trimmed);
        if ($diperbaiki !== null) {
            $decoded = $this->cobaDecode($diperbaiki);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        $this->tulisLog($raw, $this->alasanTerakhir ?? 'Format JSON tidak dikenali.');

        throw JsonOutputException::ramah();
    }

    private function cobaDecode(string $teks): ?array
    {
        try {
            $decoded = json_decode($teks, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (JsonException $exception) {
            $this->alasanTerakhir = $exception->getMessage();

            return null;
        }
    }

    /**
     * Menutup JSON yang terpotong di tengah (mis. karena batas token).
     */
    private function cobaRepairTerpotong(string $raw): ?string
    {
        $panjang = strlen($raw);
        $start = null;

        for ($i = 0; $i < $panjang; $i++) {
            if ($raw[$i] === '{' || $raw[$i] === '[') {
                $start = $i;

                break;
            }
        }

        if ($start === null) {
            return null;
        }

        $stack = [];
        $diDalamString = false;
        $escape = false;

        for ($i = $start; $i < $panjang; $i++) {
            $karakter = $raw[$i];

            if ($diDalamString) {
                if ($escape) {
                    $escape = false;
                } elseif ($karakter === '\\') {
                    $escape = true;
                } elseif ($karakter === '"') {
                    $diDalamString = false;
                }

                continue;
            }

            if ($karakter === '"') {
                $diDalamString = true;
            } elseif ($karakter === '{') {
                $stack[] = '}';
            } elseif ($karakter === '[') {
                $stack[] = ']';
            } elseif ($karakter === '}' || $karakter === ']') {
                array_pop($stack);
            }
        }

        if ($stack === [] && ! $diDalamString) {
            return null;
        }

        $potongan = substr($raw, $start);

        if ($diDalamString) {
            $potongan .= '"';
        } else {
            $potongan = rtrim($potongan);

            while ($potongan !== '' && in_array(substr($potongan, -1), [',', ':'], true)) {
                $potongan = rtrim(substr($potongan, 0, -1));
            }
        }

        while ($stack !== []) {
            $potongan .= array_pop($stack);
        }

        return $potongan;
    }

    private function extractJsonSpan(string $raw): ?string
    {
        $panjang = strlen($raw);
        $start = null;
        $open = null;

        for ($i = 0; $i < $panjang; $i++) {
            $karakter = $raw[$i];

            if ($karakter === '{' || $karakter === '[') {
                $start = $i;
                $open = $karakter;
                break;
            }
        }

        if ($start === null) {
            return null;
        }

        $close = $open === '{' ? '}' : ']';
        $kedalaman = 0;
        $diDalamString = false;
        $escape = false;

        for ($i = $start; $i < $panjang; $i++) {
            $karakter = $raw[$i];

            if ($diDalamString) {
                if ($escape) {
                    $escape = false;
                } elseif ($karakter === '\\') {
                    $escape = true;
                } elseif ($karakter === '"') {
                    $diDalamString = false;
                }

                continue;
            }

            if ($karakter === '"') {
                $diDalamString = true;

                continue;
            }

            if ($karakter === $open) {
                $kedalaman++;

                continue;
            }

            if ($karakter === $close) {
                $kedalaman--;

                if ($kedalaman === 0) {
                    return substr($raw, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    private function tulisLog(string $raw, string $alasan): void
    {
        Log::channel('ai')->warning(
            'Output AI gagal di-parse sebagai JSON.',
            ['alasan' => $alasan, 'output' => (string) mb_substr($raw, 0, 4000)]
        );
    }
}
