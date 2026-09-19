<?php

namespace App\Domain\Ai;

interface AiProvider
{
    /**
     * @throws AiProviderException
     */
    public function generate(string $prompt): string;
}
