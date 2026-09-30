<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KompetensiDasarController extends Controller
{
    public function index(Request $request, Mapel $mapel): View
    {
        $kompetensiDasars = $mapel->kompetensiDasars()
            ->when($request->filled('level_kognitif'), fn (Builder $q) => $q->where('level_kognitif', $request->query('level_kognitif')))
            ->orderBy('kode_kompetensi')
            ->get();

        return view('admin.kompetensi_dasar.index', compact('kompetensiDasars', 'mapel'));
    }

    public function store(Request $request, Mapel $mapel): RedirectResponse
    {
        try {
            $data = $this->validateData($request, $mapel);
        } catch (ValidationException $e) {
            return $this->dialogErrorResponse($request, $e, 'admin.mapel.kompetensi-dasar.index', [$mapel], 'create-kd');
        }

        $mapel->kompetensiDasars()->create($data);

        return redirect()->route('admin.mapel.kompetensi-dasar.index', $mapel)
            ->with('success', 'Kompetensi dasar berhasil ditambahkan.');
    }

    public function update(Request $request, Mapel $mapel, KompetensiDasar $kompetensi_dasar): RedirectResponse
    {
        try {
            $data = $this->validateData($request, $mapel, $kompetensi_dasar);
        } catch (ValidationException $e) {
            return $this->dialogErrorResponse(
                $request,
                $e,
                'admin.mapel.kompetensi-dasar.index',
                [$mapel],
                "edit-kd-{$kompetensi_dasar->getKey()}",
            );
        }

        $kompetensi_dasar->update($data);

        return redirect()->route('admin.mapel.kompetensi-dasar.index', $mapel)
            ->with('success', 'Kompetensi dasar berhasil diperbarui.');
    }

    public function destroy(Mapel $mapel, KompetensiDasar $kompetensi_dasar): RedirectResponse
    {
        if ($kompetensi_dasar->soal()->exists()) {
            return redirect()->route('admin.mapel.kompetensi-dasar.index', $mapel)
                ->with('error', 'Kompetensi dasar masih digunakan oleh soal, tidak dapat dihapus.');
        }

        $kompetensi_dasar->delete();

        return redirect()->route('admin.mapel.kompetensi-dasar.index', $mapel)
            ->with('success', 'Kompetensi dasar berhasil dihapus.');
    }

    public function bulkDestroy(Request $request, Mapel $mapel): RedirectResponse
    {
        try {
            $data = $request->validate([
                'ids' => ['nullable', 'array'],
                'ids.*' => ['integer'],
                'all' => ['nullable', 'boolean'],
                'level_kognitif' => ['nullable', Rule::in(array_keys(KompetensiDasar::LEVEL_KOGNITIF))],
            ]);
        } catch (ValidationException $e) {
            // Index tidak memakai old(), jadi error ditampilkan lewat flash biasa
            // agar tidak ada input yang membekas ke dialog lain.
            return redirect()->route('admin.mapel.kompetensi-dasar.index', $mapel)
                ->with('error', $e->errors()->first());
        }

        $redirect = redirect()->route('admin.mapel.kompetensi-dasar.index', $mapel);

        if (($data['all'] ?? false) !== true && empty($data['ids'])) {
            return $redirect->with('error', 'Tidak ada kompetensi dasar yang dipilih.');
        }

        $kandidatIds = $mapel->kompetensiDasars()
            ->when($request->filled('level_kognitif'), fn (Builder $q) => $q->where('level_kognitif', $data['level_kognitif']))
            ->when(($data['all'] ?? false) !== true, fn (Builder $q) => $q->whereIn('id', $data['ids']))
            ->pluck('id');

        $dipakaiIds = KompetensiDasar::query()
            ->whereIn('id', $kandidatIds)
            ->whereHas('soal')
            ->pluck('id');

        $terhapus = KompetensiDasar::query()
            ->whereIn('id', $kandidatIds)
            ->whereNotIn('id', $dipakaiIds)
            ->delete();

        $pesan = $terhapus > 0
            ? "{$terhapus} kompetensi dasar berhasil dihapus"
            : 'Tidak ada kompetensi dasar yang dihapus';
        $pesan .= $dipakaiIds->isNotEmpty()
            ? ", {$dipakaiIds->count()} kompetensi dasar dilewati karena masih memiliki soal."
            : '.';

        return $redirect->with($terhapus > 0 ? 'success' : 'error', $pesan);
    }

    /**
     * Redirect kembali ke index dengan membuka dialog tertentu berikut isian
     * yang gagal divalidasi. Input sengaja tidak memakai `withInput()` agar
     * `old()` tetap kosong dan dialog lain tidak ikut terkontaminasi.
     *
     * @param  array<int, Mapel>  $parameters
     */
    private function dialogErrorResponse(
        Request $request,
        ValidationException $exception,
        string $route,
        array $parameters,
        string $dialogId,
    ): RedirectResponse {
        return redirect()
            ->route($route, $parameters)
            ->withErrors($exception->errors())
            ->with('open_dialog', $dialogId)
            ->with('form_input', $request->except(['_token', '_method']));
    }

    /**
     * @return array{
     *     kode_kompetensi: string,
     *     deskripsi: string,
     *     materi_pokok: ?string,
     *     level_kognitif: string,
     *     batasan: ?string,
     * }
     */
    private function validateData(Request $request, Mapel $mapel, ?KompetensiDasar $kompetensiDasar = null): array
    {
        return $request->validate([
            'kode_kompetensi' => ['required', 'string', 'max:50',
                Rule::unique('kompetensi_dasar', 'kode_kompetensi')
                    ->where('mapel_id', $mapel->getKey())
                    ->whereNull('deleted_at')
                    ->ignore($kompetensiDasar),
            ],
            'deskripsi' => ['required', 'string'],
            'materi_pokok' => ['nullable', 'string', 'max:255'],
            'level_kognitif' => ['required', Rule::in(array_keys(KompetensiDasar::LEVEL_KOGNITIF))],
            'batasan' => ['nullable', 'string'],
        ]);
    }
}
