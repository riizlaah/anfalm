<?php

it('memakai zona waktu Asia/Jakarta untuk seluruh aplikasi', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(now()->timezone->getName())->toBe('Asia/Jakarta')
        ->and(now()->offset)->toBe(7 * 3600);
});
