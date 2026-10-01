<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Editor WYSIWYG berbasis TipTap untuk input konten kaya (DESIGN §4).
 *
 * Komponen ini berdiri di dalam `<form>` biasa: yang dikirim ke form induk
 * adalah input tersembunyi bernama `$nama`, yang diisi dari JavaScript setiap
 * kali isi editor berubah. Seluruh area editor dibungkus `wire:ignore` agar
 * morph Livewire — termasuk setelah unggahan gambar — tidak mereset TipTap
 * yang sedang berjalan di browser.
 */
class Wysiwyg extends Component
{
    use WithFileUploads;

    /**
     * Nama field form yang menerima HTML editor saat form induk disubmit.
     */
    public string $nama = '';

    /**
     * Label yang ditampilkan di atas editor, kosong bila labelnya ditulis
     * sendiri oleh pemanggil.
     */
    public string $label = '';

    /**
     * HTML awal yang dimuat ke editor. Pemanggil sudah mengolah old() dan
     * nilai tersimpan di dalamnya. Tipe-nya nullable karena Livewire menyetel
     * parameter tag langsung ke property yang namanya cocok sebelum `mount()`
     * berjalan, sehingga `null` dari `old()` boleh masuk lebih dulu.
     */
    public ?string $nilai = null;

    /**
     * @var UploadedFile|string|null unggahan sementara
     */
    public $gambar = null;

    public function mount(string $nama, string $label = '', ?string $nilai = null): void
    {
        $this->nama = $nama;
        $this->label = $label;
        $this->nilai = $nilai ?? '';
    }

    /**
     * Simpan gambar ke disk publik lalu beri tahu editor agar menyisipkannya.
     * Kompresi WebP maksimal 500 KB sudah dilakukan di browser sebelum ini.
     */
    public function unggahGambar(): void
    {
        $this->validate([
            'gambar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:500'],
        ]);

        $path = $this->gambar->store('gambar-soal', 'public');

        $this->dispatch('gambarTerunggah', url: Storage::disk('public')->url($path));
    }

    public function render(): View
    {
        return view('livewire.wysiwyg');
    }
}
