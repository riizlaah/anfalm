<?php

use App\Domain\Ai\JsonOutputException;
use App\Domain\Ai\JsonRepairService;
use Illuminate\Support\Facades\Log;

it('mem-parse JSON valid polos', function () {
    $raw = '{"daftar_soal":[{"id":1}]}';

    expect((new JsonRepairService)->parse($raw))->toBe(['daftar_soal' => [['id' => 1]]]);
});

it('mengekstrak JSON dari fenced code block', function () {
    $raw = "Berikut outputnya:\n```json\n{\"a\":1}\n```\nSemoga membantu.";

    expect((new JsonRepairService)->parse($raw))->toBe(['a' => 1]);
});

it('mengekstrak JSON yang dibungkus teks tanpa fence', function () {
    $raw = 'Hasil: {"tips":[1,2,3]} selesai.';

    expect((new JsonRepairService)->parse($raw))->toBe(['tips' => [1, 2, 3]]);
});

it('mendukung array JSON polos', function () {
    $raw = '[{"id":1},{"id":2}]';

    expect((new JsonRepairService)->parse($raw))->toBe([['id' => 1], ['id' => 2]]);
});

it('memperbaiki JSON terpotong dengan menutup kurung yang belum tertutup', function () {
    $raw = '{"daftar_soal":[{"id":1}';

    expect((new JsonRepairService)->parse($raw))->toBe(['daftar_soal' => [['id' => 1]]]);
});

it('memperbaiki JSON yang terpotong di tengah string', function () {
    $raw = '{"ket":"hallo';

    expect((new JsonRepairService)->parse($raw))->toBe(['ket' => 'hallo']);
});

it('memperbaiki JSON terpotong yang diakhiri koma', function () {
    $raw = '{"a":[1,2,';

    expect((new JsonRepairService)->parse($raw))->toBe(['a' => [1, 2]]);
});

it('melempar JsonOutputException pada JSON rusak yang tidak bisa diperbaiki', function () {
    expect(fn () => (new JsonRepairService)->parse('{"a": }'))->toThrow(JsonOutputException::class);
});

it('mencatat alasan kegagalan parse ke channel ai', function () {
    Log::shouldReceive('channel')->with('ai')->andReturnSelf();
    Log::shouldReceive('warning')->once();

    expect(fn () => (new JsonRepairService)->parse('Maaf, saya tidak bisa membuat soal.'))
        ->toThrow(JsonOutputException::class);
});

it('melempar JsonOutputException pada teks tanpa JSON', function () {
    expect(fn () => (new JsonRepairService)->parse('Maaf, saya tidak bisa membuat soal.'))->toThrow(JsonOutputException::class);
});
