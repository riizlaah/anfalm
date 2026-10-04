<?php

namespace App\Domain\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

class GeminiAiProvider implements AiProvider
{
    private const STATUS_TRANSIEN = [408, 425, 429, 500, 502, 503, 504];

    private const BACKOFF_DETIK = [1, 2, 4];

    private const MAKS_PERCOBAAN = 4;

    /**
     * Batas jeda yang masih wajar ditunggu di dalam satu permintaan. Melebihi
     * ini berarti server meminta antrean panjang; tetap memaksa empat percobaan
     * cuma membakar kuota tanpa harapan lolos.
     */
    private const MAKS_TUNGGU_ULANG = 60;

    /**
     * Rantai model ketika batas laju menolak permintaan (butir S3): yang utama
     * lebih dulu, lalu varian lite yang lebih murah, lalu model gratis Google
     * lain. Satu permintaan hanya menempuh rantai ini sejauh model sebelumnya
     * benar-benar ditolak, jadi jalur yang lolos pada percobaan pertama tidak
     * pernah menyentuh model cadangan mana pun.
     *
     * @var array<int, string>
     */
    public const MODEL_BAWAAN = [
        'gemini-3.5-flash',
        'gemini-3.5-flash-lite',
        'gemini-3.1-flash-lite',
        'gemini-3-flash-preview',
        'gemini-3.8-flash',
        'gemini-3.7-flash',
        'gemini-3.6-flash',
        'gemini-2.0-flash',
    ];

    /**
     * @param  array<int, string>  $models  kosong memakai `MODEL_BAWAAN`
     */
    public function __construct(
        private string $apiKey,
        private array $models = [],
        private int $timeoutSeconds = 180,
    ) {
        $this->models = array_values(array_filter(
            $models,
            fn ($model): bool => is_string($model) && $model !== ''
        ));

        if ($this->models === []) {
            $this->models = self::MODEL_BAWAAN;
        }
    }

    /**
     * Rantai yang diawali model utama lalu cadangan bawaan, tanpa mengulang
     * nama yang sama bila model utama kebetulan sudah ada di dalam daftar.
     *
     * @return array<int, string>
     */
    public static function rantai(string $utama): array
    {
        return array_values(array_unique([$utama, ...self::MODEL_BAWAAN]));
    }

    public function generate(string $prompt): string
    {
        // Alasan paling informatif yang terkumpul di sepanjang rantai. Kuota
        // harian pada model pertama, misalnya, tetap layak dilaporkan walaupun
        // model terakhir justru ditolak tanpa keterangan.
        $alasan = null;

        foreach ($this->models as $model) {
            $hasil = $this->cobaModel($model, $prompt, $alasan);

            if ($hasil !== null) {
                return $hasil;
            }
        }

        throw new AiProviderException($alasan ?? $this->pesanStatus(429));
    }

    /**
     * Menjalankan prompt pada satu model sampai jawabannya jadi.
     *
     * @param  string|null  $alasan  diisi saat model ini ditolak karena kuota
     *                               harian atau antrean panjang — pesan yang
     *                               dipakai kembali bila seluruh rantai habis
     * @return string|null teks respons, atau null kalau model ini harus
     *                     digantikan model berikutnya pada rantai
     */
    private function cobaModel(string $model, string $prompt, ?string &$alasan): ?string
    {
        for ($percobaan = 1; $percobaan <= self::MAKS_PERCOBAAN; $percobaan++) {
            try {
                $response = Http::timeout($this->timeoutSeconds)
                    ->post($this->url($model), $this->payload($prompt));
            } catch (ConnectionException) {
                if ($percobaan === self::MAKS_PERCOBAAN) {
                    throw new AiProviderException('Gagal menghubungi layanan AI. Periksa koneksi, lalu coba lagi.');
                }

                Sleep::sleep(self::BACKOFF_DETIK[$percobaan - 1]);

                continue;
            }

            $status = $response->status();

            if ($status === 429) {
                $jeda = $this->jedaRateLimit($response);
                $harian = $this->kuotaHarian($response);

                $this->catatRateLimit($model, $response, $jeda, $harian);

                $perluMenunggu = ! $harian && $jeda !== null && $jeda <= self::MAKS_TUNGGU_ULANG;

                if (! $perluMenunggu) {
                    // Kuota harian atau antrean panjang: model ini memang tidak
                    // punya ruang sekarang. Tanpa petunjuk jeda pun demikian —
                    // bila server tidak menyebut kapan boleh kembali, bertanya
                    // ke model lain lebih cepat daripada menebak waktunya.
                    if ($harian || ($jeda !== null && $jeda > self::MAKS_TUNGGU_ULANG)) {
                        $alasan ??= $this->pesanAntrean($harian, $jeda);
                    }

                    break;
                }

                // Jeda pendek hanya dihormati sekali pada model yang sama:
                // setelah itu bertanya ke model berikutnya lebih cepat daripada
                // menunggu lagi di model yang barusan menolak.
                if ($percobaan > 1) {
                    break;
                }

                Sleep::sleep($jeda);

                continue;
            }

            if (in_array($status, self::STATUS_TRANSIEN, true)) {
                if ($percobaan === self::MAKS_PERCOBAAN) {
                    throw new AiProviderException($this->pesanStatus($status));
                }

                Sleep::sleep(self::BACKOFF_DETIK[$percobaan - 1]);

                continue;
            }

            if ($response->failed()) {
                throw new AiProviderException('Gagal menghubungi layanan AI (kode status '.$status.').');
            }

            $body = $response->json();

            if (Arr::get($body, 'candidates.0.finishReason') === 'MAX_TOKENS') {
                throw new AiProviderException('Output AI terpotong karena melebihi batas token. Coba lagi atau kurangi jumlah soal.');
            }

            $text = Arr::get($body, 'candidates.0.content.parts.0.text');

            if (! is_string($text) || trim($text) === '') {
                throw new AiProviderException('Layanan AI mengembalikan respons kosong.');
            }

            return $text;
        }

        return null;
    }

    private function url(string $model): string
    {
        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $prompt): array
    {
        return [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 16384,
                'responseMimeType' => 'application/json',
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ];
    }

    /**
     * Jeda yang diminta server untuk dicoba lagi, dalam detik.
     *
     * Prioritas: header `Retry-After` (detik atau HTTP-date), lalu
     * `error.details[?@type=RetryInfo].retryDelay` (durasi protobuf "37s").
     * null kalau server tidak menyebutkan apa pun.
     */
    private function jedaRateLimit(Response $response): ?int
    {
        $header = trim($response->header('Retry-After'));

        if ($header !== '') {
            if (ctype_digit($header)) {
                return (int) $header;
            }

            $waktu = strtotime($header);

            if ($waktu !== false) {
                return max(0, $waktu - time());
            }
        }

        foreach ($this->detailRespons($response) as $detail) {
            if (! str_contains((string) Arr::get($detail, '@type', ''), 'RetryInfo')) {
                continue;
            }

            if (preg_match('/^(\d+(?:\.\d+)?)(ms|s|m|h)$/', (string) Arr::get($detail, 'retryDelay', ''), $m) === 1) {
                $faktor = ['ms' => 0.001, 's' => 1, 'm' => 60, 'h' => 3600][$m[2]];

                return (int) ceil((float) $m[1] * $faktor);
            }
        }

        return null;
    }

    /**
     * Apakah 429 ini menghabiskan kuota jangka panjang (harian), bukan sekadar
     * batas laju sesaat. Menebak berdasarkan label kuota milik Google karena
     * keduanya sama-sama berstatus 429.
     */
    private function kuotaHarian(Response $response): bool
    {
        foreach ($this->detailRespons($response) as $detail) {
            if (str_contains(strtoupper((string) Arr::get($detail, 'reason', '')), 'QUOTA')) {
                return true;
            }

            $metadata = Arr::get($detail, 'metadata', []);

            foreach (['quotaId', 'quotaLimit', 'quotaMetric'] as $kunci) {
                if (preg_match('/daily|per_?day|_24h/i', (string) Arr::get($metadata, $kunci, '')) === 1) {
                    return true;
                }
            }
        }

        $pesan = (string) Arr::get($response->json() ?? [], 'error.message', '');

        return preg_match('/daily quota|kuota harian/i', $pesan) === 1;
    }

    /**
     * Setiap penolakan 429 dicatat lengkap ke ai.log: tanpa ini penyebabnya
     * hanya terlihat dari pesan yang sudah disederhanakan untuk admin. Nama
     * model ikut dicatat karena sekarang satu permintaan bisa melewati
     * beberapa model, dan baris-baris inilah yang menunjukkan di mana rantai
     * berhenti.
     */
    private function catatRateLimit(string $model, Response $response, ?int $jeda, bool $harian): void
    {
        Log::channel('ai')->warning('Permintaan AI ditolak karena batas laju.', [
            'status' => $response->status(),
            'model' => $model,
            'jeda_detik' => $jeda,
            'kuota_harian' => $harian,
            'retry_after' => $response->header('Retry-After'),
            'headers' => $this->headerRateLimit($response),
            'body' => (string) mb_substr((string) $response->body(), 0, 2000),
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function headerRateLimit(Response $response): array
    {
        $hasil = [];

        foreach ($response->headers() as $nama => $nilai) {
            if (str_starts_with(mb_strtolower((string) $nama), 'x-ratelimit')) {
                $hasil[(string) $nama] = is_array($nilai) ? ($nilai[0] ?? null) : (string) $nilai;
            }
        }

        return $hasil;
    }

    /**
     * @return array<int, mixed>
     */
    private function detailRespons(Response $response): array
    {
        $detail = Arr::get($response->json() ?? [], 'error.details');

        return is_array($detail) ? array_values($detail) : [];
    }

    private function pesanAntrean(bool $harian, ?int $jeda): string
    {
        if ($harian) {
            return $jeda !== null
                ? 'Kuota AI harian habis. Coba lagi sekitar pukul '.now()->addSeconds($jeda)->format('H:i').'.'
                : 'Kuota AI harian habis. Coba lagi pada periode kuota berikutnya.';
        }

        // Pemanggil hanya sampai ke sini kalau $jeda ada dan terlalu panjang.
        $jeda = (int) $jeda;

        return 'Layanan AI menolak permintaan selama ±'.max(1, (int) ceil($jeda / 60)).' menit. '
            .'Coba lagi sekitar pukul '.now()->addSeconds($jeda)->format('H:i').'.';
    }

    private function pesanStatus(int $status): string
    {
        if ($status === 429 || $status === 503) {
            return 'Layanan AI sedang sibuk. Tunggu beberapa saat, lalu coba lagi.';
        }

        return 'Gagal menghubungi layanan AI (kode status '.$status.').';
    }
}
