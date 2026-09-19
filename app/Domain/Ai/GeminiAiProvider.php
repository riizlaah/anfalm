<?php

namespace App\Domain\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class GeminiAiProvider implements AiProvider
{
    private const STATUS_TRANSIEN = [408, 425, 429, 500, 502, 503, 504];

    private const BACKOFF_DETIK = [1, 2, 4];

    private const MAKS_PERCOBAAN = 4;

    public function __construct(
        private string $apiKey,
        private string $model = 'gemini-3.5-flash',
        private int $timeoutSeconds = 180,
    ) {}

    public function generate(string $prompt): string
    {
        for ($percobaan = 1; $percobaan <= self::MAKS_PERCOBAAN; $percobaan++) {
            try {
                $response = Http::timeout($this->timeoutSeconds)
                    ->post($this->url(), $this->payload($prompt));
            } catch (ConnectionException) {
                if ($percobaan === self::MAKS_PERCOBAAN) {
                    throw new AiProviderException('Gagal menghubungi layanan AI. Periksa koneksi, lalu coba lagi.');
                }

                Sleep::sleep(self::BACKOFF_DETIK[$percobaan - 1]);

                continue;
            }

            if (in_array($response->status(), self::STATUS_TRANSIEN, true)) {
                if ($percobaan === self::MAKS_PERCOBAAN) {
                    throw new AiProviderException($this->pesanStatus($response->status()));
                }

                Sleep::sleep(self::BACKOFF_DETIK[$percobaan - 1]);

                continue;
            }

            if ($response->failed()) {
                throw new AiProviderException('Gagal menghubungi layanan AI (kode status '.$response->status().').');
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
    }

    private function url(): string
    {
        return "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
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

    private function pesanStatus(int $status): string
    {
        if ($status === 429 || $status === 503) {
            return 'Layanan AI sedang sibuk. Tunggu beberapa saat, lalu coba lagi.';
        }

        return 'Gagal menghubungi layanan AI (kode status '.$status.').';
    }
}
