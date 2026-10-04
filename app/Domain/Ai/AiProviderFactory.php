<?php

namespace App\Domain\Ai;

class AiProviderFactory
{
    public static function make(): AiProvider
    {
        $apiKey = config('services.gemini.key');

        if (! app()->runningUnitTests() && is_string($apiKey) && $apiKey !== '') {
            return new GeminiAiProvider($apiKey, self::rantaiGemini(), self::timeoutGemini());
        }

        return new AiFake(AiFake::fixture());
    }

    /**
     * Urutan model yang dipakai. `GEMINI_MODELS` yang menggantikan urutan
     * bawaan begitu diisi, sehingga pengguna di hosting dengan batas waktu
     * pendek bisa memendekkan rantai tanpa menyentuh perilaku bawaan.
     *
     * @return array<int, string>
     */
    public static function rantaiGemini(): array
    {
        $khusus = trim((string) (config('services.gemini.models') ?? ''));

        if ($khusus === '') {
            return GeminiAiProvider::rantai((string) (config('services.gemini.model') ?? 'gemini-3.5-flash'));
        }

        return array_values(array_filter(array_map('trim', explode(',', $khusus))));
    }

    /**
     * Detik tunggu satu percobaan. Nilai yang tidak positif dianggap tidak
     * terisi, karena nol justru berarti tunggu tanpa batas.
     */
    public static function timeoutGemini(): int
    {
        $timeout = (int) (config('services.gemini.timeout') ?? 0);

        return $timeout > 0 ? $timeout : 180;
    }
}
