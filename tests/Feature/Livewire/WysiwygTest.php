<?php

use App\Livewire\Wysiwyg;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test('wysiwyg', ['nama' => 'pertanyaan'])
        ->assertStatus(200);
});

it('membuang tombol pratinjau dan panelnya karena tampilan sudah sama dengan yang disimpan', function () {
    $editor = Livewire::test(Wysiwyg::class, ['nama' => 'pertanyaan'])
        ->assertStatus(200);

    $editor->assertDontSee('data-tugas="preview"', false)
        ->assertDontSee('data-wysiwyg-preview', false);

    // Lukis pratinjau di skrip ikut dibuang; yang tersisa hanya sinkronisasi
    // input tersembunyi, karena editor memang sudah menampilkan isi finalnya.
    expect(file_get_contents(resource_path('js/wysiwyg.js')))
        ->not->toContain('lukisPreview');
});

it('menyediakan popup sisipkan ekspresi matematika berisi input, pratinjau, dan pilihan mode', function () {
    Livewire::test(Wysiwyg::class, ['nama' => 'pertanyaan'])
        ->assertSee('data-tugas="rumus"', false)
        ->assertSee('data-rumus-dialog', false)
        ->assertSee('data-rumus-input', false)
        ->assertSee('data-rumus-pratinjau', false)
        ->assertSee('data-rumus-mode-blok', false);
});

it('memuat modul node ekspresi sehingga ekspresi di editor ikut ter-render dan bisa diklik', function () {
    expect(file_get_contents(resource_path('js/wysiwyg.js')))
        ->toContain("from './ekspresi'");
});

it('memasang input tersembunyi dengan nama field dan isi awal yang diberikan', function () {
    Livewire::test(Wysiwyg::class, ['nama' => 'pertanyaan', 'nilai' => '<p>Isi awal</p>'])
        ->assertSee('name="pertanyaan"', false)
        ->assertSee('value="&lt;p&gt;Isi awal&lt;/p&gt;"', false)
        ->assertSee('data-wysiwyg', false);
});

it('menolak unggahan yang bukan gambar', function () {
    Storage::fake('public');

    Livewire::test(Wysiwyg::class, ['nama' => 'gambar'])
        ->set('gambar', UploadedFile::fake()->create('dokumen.txt', 10, 'text/plain'))
        ->call('unggahGambar')
        ->assertHasErrors(['gambar' => 'image']);

    expect(Storage::disk('public')->allFiles('gambar-soal'))->toBe([]);
});

it('menolak unggahan gambar lebih dari 500 KB (maks. 500 KB)', function () {
    Storage::fake('public');

    $potonganAsli = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
    $kembaliBesar = $potonganAsli.str_repeat("\0", 600 * 1024);

    Livewire::test(Wysiwyg::class, ['nama' => 'gambar'])
        ->set('gambar', UploadedFile::fake()->createWithContent('soal.png', $kembaliBesar))
        ->call('unggahGambar')
        ->assertHasErrors(['gambar' => 'max']);
});

it('menyimpan gambar valid ke disk public dan mengirim URL-nya ke editor', function () {
    Storage::fake('public');

    $pngSatuPiksel = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );

    Livewire::test(Wysiwyg::class, ['nama' => 'gambar'])
        ->set('gambar', UploadedFile::fake()->createWithContent('soal.png', $pngSatuPiksel))
        ->call('unggahGambar')
        ->assertHasNoErrors()
        ->assertDispatched('gambarTerunggah');

    expect(Storage::disk('public')->allFiles('gambar-soal'))->toHaveCount(1);
});
