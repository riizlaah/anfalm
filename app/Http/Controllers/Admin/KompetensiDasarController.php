<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KompetensiDasarController extends Controller
{
    public const LEVEL_KOGNITIF = [
        'pengetahuan',
        'pemahaman',
        'penerapan',
        'penalaran',
    ];

    public function index(Request $request): View
    {
        $kompetensiDasars = KompetensiDasar::with('mapel')
            ->when($request->filled('mapel_id'), fn (Builder $q) => $q->where('mapel_id', $request->query('mapel_id')))
            ->when($request->filled('level_kognitif'), fn (Builder $q) => $q->where('level_kognitif', $request->query('level_kognitif')))
            ->orderBy('mapel_id')
            ->orderBy('kode_kompetensi')
            ->get();

        $mapels = Mapel::orderBy('nama')->get();

        return view('admin.kompetensi_dasar.index', compact('kompetensiDasars', 'mapels'));
    }

    public function create(): View
    {
        $mapels = Mapel::orderBy('nama')->get();

        return view('admin.kompetensi_dasar.create', compact('mapels'));
    }

    public function store(Request $request): RedirectResponse
    {
        KompetensiDasar::create($this->validateData($request));

        return redirect()->route('admin.kompetensi-dasar.index')
            ->with('success', 'Kompetensi dasar berhasil ditambahkan.');
    }

    public function edit(KompetensiDasar $kompetensi_dasar): View
    {
        $mapels = Mapel::orderBy('nama')->get();

        return view('admin.kompetensi_dasar.edit', compact('kompetensi_dasar', 'mapels'));
    }

    public function update(Request $request, KompetensiDasar $kompetensi_dasar): RedirectResponse
    {
        $kompetensi_dasar->update($this->validateData($request, $kompetensi_dasar));

        return redirect()->route('admin.kompetensi-dasar.index')
            ->with('success', 'Kompetensi dasar berhasil diperbarui.');
    }

    public function destroy(KompetensiDasar $kompetensi_dasar): RedirectResponse
    {
        if ($kompetensi_dasar->soal()->exists()) {
            return redirect()->route('admin.kompetensi-dasar.index')
                ->with('error', 'Kompetensi dasar masih digunakan oleh soal, tidak dapat dihapus.');
        }

        $kompetensi_dasar->delete();

        return redirect()->route('admin.kompetensi-dasar.index')
            ->with('success', 'Kompetensi dasar berhasil dihapus.');
    }

    /**
     * @return array{
     *     mapel_id: int,
     *     kode_kompetensi: string,
     *     deskripsi: string,
     *     materi_pokok: ?string,
     *     level_kognitif: string,
     *     batasan: ?string,
     * }
     */
    private function validateData(Request $request, ?KompetensiDasar $kompetensiDasar = null): array
    {
        return $request->validate([
            'mapel_id' => ['required', 'integer', Rule::exists('mapel', 'id')],
            'kode_kompetensi' => ['required', 'string', 'max:50',
                Rule::unique('kompetensi_dasar', 'kode_kompetensi')
                    ->where('mapel_id', $request->integer('mapel_id'))
                    ->ignore($kompetensiDasar),
            ],
            'deskripsi' => ['required', 'string'],
            'materi_pokok' => ['nullable', 'string', 'max:255'],
            'level_kognitif' => ['required', Rule::in(self::LEVEL_KOGNITIF)],
            'batasan' => ['nullable', 'string'],
        ]);
    }
}
