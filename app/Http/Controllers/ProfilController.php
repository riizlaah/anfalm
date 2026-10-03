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
 */
class ProfilController extends Controller
{
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

        return view('profil.show', [
            'peserta' => $peserta,
            'mapels' => $this->semuaMapel(),
            'terpilih' => $peserta->mapelPilihan->pluck('id'),
            'tingkatOpsi' => self::TINGKAT_OPSI,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $peserta = $request->user();

        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'sekolah' => ['nullable', 'string', 'max:100'],
            'tingkat' => ['nullable', 'string', Rule::in(self::TINGKAT_OPSI)],
            'jurusan' => ['nullable', 'string', 'max:50'],
            'mapel_pilihan' => ['sometimes', 'array'],
            'mapel_pilihan.*' => ['integer', Rule::exists('mapel', 'id')->whereNull('deleted_at')],
        ], [
            'tingkat.in' => 'Pilih tingkat sekolah yang tersedia.',
            'mapel_pilihan.*.exists' => 'Ada mapel pilihan yang sudah tidak tersedia. Muat ulang halaman lalu pilih lagi.',
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
