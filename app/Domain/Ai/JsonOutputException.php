<?php

namespace App\Domain\Ai;

use RuntimeException;

class JsonOutputException extends RuntimeException
{
    public const PESAN_RAMAH = 'Gagal memproses output AI. Silakan coba generate ulang.';

    public static function ramah(): self
    {
        return new self(self::PESAN_RAMAH);
    }
}
