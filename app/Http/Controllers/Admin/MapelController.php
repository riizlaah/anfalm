<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mapel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MapelController extends Controller
{
    public function index(Request $request): View
    {
        $mapels = Mapel::query()
            ->withCount(['kompetensiDasars as jumlah_kd', 'soals as jumlah_soal'])
            ->when($request->filled('tingkat'), fn (Builder $q) => $q->where('tingkat', $request->query('tingkat')))
            ->when($request->filled('jenis'), fn (Builder $q) => $q->where('jenis', $request->query('jenis')))
            ->orderBy('kode')
            ->get();

        return view('admin.mapel.index', compact('mapels'));
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $data = $this->validateData($request);
        } catch (ValidationException $e) {
            return $this->dialogErrorResponse($request, $e, 'admin.mapel.index', 'create-mapel');
        }

        Mapel::create([
            ...$data,
            'is_pkk' => $request->boolean('is_pkk'),
        ]);

        return redirect()->route('admin.mapel.index')
            ->with('success', 'Mapel berhasil ditambahkan.');
    }

    public function update(Request $request, Mapel $mapel): RedirectResponse
    {
        try {
            $data = $this->validateData($request, $mapel);
        } catch (ValidationException $e) {
            return $this->dialogErrorResponse($request, $e, 'admin.mapel.index', "edit-mapel-{$mapel->getKey()}");
        }

        $mapel->update([
            ...$data,
            'is_pkk' => $request->boolean('is_pkk'),
        ]);

        return redirect()->route('admin.mapel.index')
            ->with('success', 'Mapel berhasil diperbarui.');
    }

    public function destroy(Mapel $mapel): RedirectResponse
    {
        $dipakai = Mapel::query()
            ->whereKey($mapel->getKey())
            ->where(fn (Builder $q) => $q->whereHas('kompetensiDasars')->orWhereHas('paketSoal'))
            ->exists();

        if ($dipakai) {
            return redirect()->route('admin.mapel.index')
                ->with('error', 'Mapel masih digunakan oleh soal/paket, tidak dapat dihapus.');
        }

        $mapel->delete();

        return redirect()->route('admin.mapel.index')
            ->with('success', 'Mapel berhasil dihapus.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        try {
            $data = $request->validate([
                'ids' => ['nullable', 'array'],
                'ids.*' => ['integer'],
                'all' => ['nullable', 'boolean'],
                'tingkat' => ['nullable', Rule::in([
                    Mapel::TINGKAT_SD,
                    Mapel::TINGKAT_SMP,
                    Mapel::TINGKAT_SMA,
                    Mapel::TINGKAT_SMK,
                    Mapel::TINGKAT_ALL,
                ])],
                'jenis' => ['nullable', Rule::in([
                    Mapel::JENIS_WAJIB,
                    Mapel::JENIS_PILIHAN_UMUM,
                    Mapel::JENIS_PILIHAN_KEJURUAN,
                ])],
            ]);
        } catch (ValidationException $e) {
            // Index tidak memakai old(), jadi error ditampilkan lewat flash biasa
            // agar tidak ada input yang membekas ke dialog lain.
            return redirect()->route('admin.mapel.index')
                ->with('error', $e->errors()->first());
        }

        if (($data['all'] ?? false) !== true && empty($data['ids'])) {
            return redirect()->route('admin.mapel.index')
                ->with('error', 'Tidak ada mapel yang dipilih.');
        }

        $kandidatIds = Mapel::query()
            ->when($request->filled('tingkat'), fn (Builder $q) => $q->where('tingkat', $data['tingkat']))
            ->when($request->filled('jenis'), fn (Builder $q) => $q->where('jenis', $data['jenis']))
            ->when(($data['all'] ?? false) !== true, fn (Builder $q) => $q->whereIn('id', $data['ids']))
            ->pluck('id');

        $dipakaiIds = Mapel::query()
            ->whereIn('id', $kandidatIds)
            ->where(fn (Builder $q) => $q->whereHas('kompetensiDasars')->orWhereHas('paketSoal'))
            ->pluck('id');

        $terhapus = Mapel::query()
            ->whereIn('id', $kandidatIds)
            ->whereNotIn('id', $dipakaiIds)
            ->delete();

        $pesan = $terhapus > 0
            ? "{$terhapus} mapel berhasil dihapus"
            : 'Tidak ada mapel yang dihapus';
        $pesan .= $dipakaiIds->isNotEmpty()
            ? ", {$dipakaiIds->count()} mapel dilewati karena masih digunakan oleh KD/paket soal."
            : '.';

        return redirect()->route('admin.mapel.index')
            ->with($terhapus > 0 ? 'success' : 'error', $pesan);
    }

    /**
     * Redirect kembali ke index dengan membuka dialog tertentu berikut isian
     * yang gagal divalidasi. Input sengaja tidak memakai `withInput()` agar
     * `old()` tetap kosong dan dialog lain tidak ikut terkontaminasi.
     */
    private function dialogErrorResponse(
        Request $request,
        ValidationException $exception,
        string $route,
        string $dialogId,
    ): RedirectResponse {
        return redirect()
            ->route($route)
            ->withErrors($exception->errors())
            ->with('open_dialog', $dialogId)
            ->with('form_input', $request->except(['_token', '_method']));
    }

    /**
     * @return array{ kode: string, nama: string, tingkat: string, jenis: string }
     */
    private function validateData(Request $request, ?Mapel $mapel = null): array
    {
        return $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('mapel', 'kode')->whereNull('deleted_at')->ignore($mapel)],
            'nama' => ['required', 'string', 'max:100'],
            'tingkat' => ['required', Rule::in([
                Mapel::TINGKAT_SD,
                Mapel::TINGKAT_SMP,
                Mapel::TINGKAT_SMA,
                Mapel::TINGKAT_SMK,
                Mapel::TINGKAT_ALL,
            ])],
            'jenis' => ['required', Rule::in([
                Mapel::JENIS_WAJIB,
                Mapel::JENIS_PILIHAN_UMUM,
                Mapel::JENIS_PILIHAN_KEJURUAN,
            ])],
        ]);
    }
}
