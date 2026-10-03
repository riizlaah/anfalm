<?php

namespace App\Http\Controllers;

use App\Models\Mapel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Halaman profil peserta (butir 96): identitas dasar plus pilihan mapel.
 *
 * Pilihan mapel hanya menyaring *tampilan* — ia menentukan mapel apa yang
 * ditawarkan di Analisis dan form Latihan, sedangkan tryout dan catatan nilai
 * tetap mencakup semua mapel. Karena halaman inilah satu-satunya tempat
 * memilihnya, halaman yang sama juga dipakai sebagai onboarding: peserta baru
 * langsung diarahkan ke sini begitu pendaftarannya selesai.
 *
 * Pilihan hanya berisi mapel **pilihan**. Mapel wajib ditampilkan sebagai
 * keterangan berupa nama, tanpa kotak centang — menawarkannya berarti
 * menyuruh peserta memilih sesuatu yang sebenarnya tidak bisa tidak ia pilih.
 */
class ProfilController extends Controller
{
    /**
     * Maksimum jumlah mapel pilihan yang bisa dicentang peserta.
     *
     * Angka yang sama dipakai tiga kali: validasi server, pesan yang dibaca
     * peserta, dan penghentian kotak di sisi klien.
     */
    public const MAKS_PILIHAN = 2;

    /**
     * Opsi tingkat pada form, sama dengan nilai yang bisa dimiliki kolom
     * `users.tingkat`.
     *
     * @var array<int, string>
     */
    public const TINGKAT_OPSI = [
        Mapel::TINGKAT_SD,
        Mapel::TINGKAT_SMP,
        Mapel::TINGKAT_SMA,
        Mapel::TINGKAT_SMK,
    ];

    public function show(Request $request): View
    {
        $peserta = $request->user();
        $mapels = $this->semuaMapel();

        return view('profil.show', [
            'peserta' => $peserta,
            'wajib' => $mapels->where('jenis', Mapel::JENIS_WAJIB)->values(),
            'pilihan' => $mapels->where('jenis', '!=', Mapel::JENIS_WAJIB)->values(),
            'terpilih' => $peserta->mapelPilihan->pluck('id'),
            'tingkatOpsi' => self::TINGKAT_OPSI,
            'maksPilihan' => self::MAKS_PILIHAN,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $peserta = $request->user();

        $mapels = $this->semuaMapel();
        $wajibIds = $mapels->where('jenis', Mapel::JENIS_WAJIB)->pluck('id')->all();

        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'sekolah' => ['nullable', 'string', 'max:100'],
            'tingkat' => ['nullable', 'string', Rule::in(self::TINGKAT_OPSI)],
            'jurusan' => ['nullable', 'string', 'max:50'],
            'mapel_pilihan' => ['sometimes', 'array', 'max:'.self::MAKS_PILIHAN],
            'mapel_pilihan.*' => [
                'integer',
                Rule::exists('mapel', 'id')->whereNull('deleted_at'),
                Rule::notIn($wajibIds),
            ],
        ], [
            'tingkat.in' => 'Pilih tingkat sekolah yang tersedia.',
            'mapel_pilihan.max' => 'Maksimal '.self::MAKS_PILIHAN.' mapel pilihan. Pilih dua yang paling ingin kamu fokuskan.',
            'mapel_pilihan.*.exists' => 'Ada mapel pilihan yang sudah tidak tersedia. Muat ulang halaman lalu pilih lagi.',
            'mapel_pilihan.*.not_in' => 'Mapel wajib tidak perlu dipilih — ia selalu ditampilkan.',
        ]);

        $peserta->update([
            'nama_lengkap' => $validated['nama_lengkap'],
            'sekolah' => $validated['sekolah'] ?? null,
            'tingkat' => $validated['tingkat'] ?? null,
            'jurusan' => $validated['jurusan'] ?? null,
        ]);

        // Saat semua kotak dilepas, `mapel_pilihan` ikut tidak terkirim sehingga
        // `sync([])` mengosongkan pilihan — keadaan yang memang berarti "tampilkan
        // semua mapel" di `User::mapelTampil()`.
        $peserta->mapelPilihan()->sync((array) $request->input('mapel_pilihan', []));

        return redirect()->route('profil.show')->with('success', 'Profil berhasil disimpan.');
    }

    /**
     * @return Collection<int, Mapel>
     */
    private function semuaMapel(): Collection
    {
        return Mapel::query()
            ->whereNull('deleted_at')
            ->orderBy('kode')
            ->get();
    }
}
