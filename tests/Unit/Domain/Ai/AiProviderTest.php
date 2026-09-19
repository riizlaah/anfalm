<?php

use App\Domain\Ai\AiFake;
use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\GeminiAiProvider;
use App\Domain\Ai\JsonRepairService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

it('mengirim prompt ke API Gemini dan mengembalikan teks respons', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{"daftar_soal":[]}']]]]],
        ], 200),
    ]);

    $hasil = (new GeminiAiProvider('kunci-rahasia', 'gemini-3.5-flash'))->generate('buatkan 5 soal');

    expect($hasil)->toBe('{"daftar_soal":[]}');

    Http::assertSent(
        fn ($request) => str_contains($request->url(), 'key=kunci-rahasia')
            && str_contains($request->url(), 'gemini-3.5-flash')
            && $request->data()['contents'][0]['parts'][0]['text'] === 'buatkan 5 soal'
            && $request->data()['generationConfig']['responseMimeType'] === 'application/json'
            && $request->data()['generationConfig']['thinkingConfig']['thinkingBudget'] === 0
            && $request->data()['generationConfig']['maxOutputTokens'] === 16384
    );
});

it('melempar pesan khusus saat output terpotong (MAX_TOKENS)', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => '{"a":']]]],
            ],
        ], 200),
    ]);

    expect(fn () => (new GeminiAiProvider('kunci-rahasia'))->generate('prompt'))
        ->toThrow(AiProviderException::class, 'Output AI terpotong karena melebihi batas token. Coba lagi atau kurangi jumlah soal.');
});

it('melempar AiProviderException saat API mengembalikan non-2xx', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 400)]);

    expect(fn () => (new GeminiAiProvider('kunci-rahasia'))->generate('prompt'))
        ->toThrow(AiProviderException::class);

    Http::assertSentCount(1);
});

it('mencoba ulang saat layanan sibuk lalu berhasil', function () {
    Sleep::fake();

    Http::fakeSequence()
        ->push([], 503)
        ->push(['candidates' => [['content' => ['parts' => [['text' => '{"ok":true}']]]]]], 200);

    $hasil = (new GeminiAiProvider('kunci-rahasia'))->generate('prompt');

    expect($hasil)->toBe('{"ok":true}');
    Http::assertSentCount(2);
});

it('berhenti setelah percobaan habis dengan pesan ramah saat 503', function () {
    Sleep::fake();

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);

    expect(fn () => (new GeminiAiProvider('kunci-rahasia'))->generate('prompt'))
        ->toThrow(AiProviderException::class, 'Layanan AI sedang sibuk. Tunggu beberapa saat, lalu coba lagi.');

    Http::assertSentCount(4);
});

it('memberi pesan ramah saat kena rate limit 429', function () {
    Sleep::fake();

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 429)]);

    expect(fn () => (new GeminiAiProvider('kunci-rahasia'))->generate('prompt'))
        ->toThrow(AiProviderException::class, 'Layanan AI sedang sibuk. Tunggu beberapa saat, lalu coba lagi.');
});

it('memakai timeout 180 detik secara bawaan', function () {
    $parameter = (new ReflectionClass(GeminiAiProvider::class))->getConstructor()->getParameters()[2];

    expect($parameter->getDefaultValue())->toBe(180);
});

it('melempar AiProviderException saat respons kosong', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => []], 200)]);

    expect(fn () => (new GeminiAiProvider('kunci-rahasia'))->generate('prompt'))
        ->toThrow(AiProviderException::class);
});

it('AiFake menghasilkan JSON fixture yang valid untuk test', function () {
    $raw = (new AiFake(AiFake::fixture()))->generate('prompt apa pun');

    $data = (new JsonRepairService)->parse($raw);

    expect($data)
        ->toHaveKey('paket_soal')
        ->toHaveKey('daftar_soal');
    expect($data['daftar_soal'])->toHaveCount(3);
    expect($data['daftar_soal'][0]['tipe_soal'])->toBe('pg');
    expect($data['daftar_soal'][0]['opsi_jawaban'][0]['is_benar'])->toBeTrue();
});
