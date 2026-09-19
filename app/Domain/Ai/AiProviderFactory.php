<?php

namespace App\Domain\Ai;

class AiProviderFactory
{
    public static function make(): AiProvider
    {
        $apiKey = config('services.gemini.key');

        if (! app()->runningUnitTests() && is_string($apiKey) && $apiKey !== '') {
            return new GeminiAiProvider(
                $apiKey,
                (string) (config('services.gemini.model') ?? 'gemini-3.5-flash'),
            );
        }

        return new AiFake(AiFake::fixture());
    }
}
