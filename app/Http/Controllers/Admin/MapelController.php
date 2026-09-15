<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mapel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MapelController extends Controller
{
    public function index(Request $request): View
    {
        $mapels = Mapel::query()
            ->when($request->filled('tingkat'), fn (Builder $q) => $q->where('tingkat', $request->query('tingkat')))
            ->when($request->filled('jenis'), fn (Builder $q) => $q->where('jenis', $request->query('jenis')))
            ->orderBy('kode')
            ->get();

        return view('admin.mapel.index', compact('mapels'));
    }

    public function create(): View
    {
        return view('admin.mapel.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Mapel::create([
            ...$this->validateData($request),
            'is_pkk' => $request->boolean('is_pkk'),
        ]);

        return redirect()->route('admin.mapel.index')
            ->with('success', 'Mapel berhasil ditambahkan.');
    }

    public function edit(Mapel $mapel): View
    {
        return view('admin.mapel.edit', compact('mapel'));
    }

    public function update(Request $request, Mapel $mapel): RedirectResponse
    {
        $mapel->update([
            ...$this->validateData($request, $mapel),
            'is_pkk' => $request->boolean('is_pkk'),
        ]);

        return redirect()->route('admin.mapel.index')
            ->with('success', 'Mapel berhasil diperbarui.');
    }

    public function destroy(Mapel $mapel): RedirectResponse
    {
        $dipakai = Mapel::query()
            ->whereKey($mapel->getKey())
            ->where(fn (Builder $q) => $q->whereHas('kompetensiDasar')->orWhereHas('paketSoal'))
            ->exists();

        if ($dipakai) {
            return redirect()->route('admin.mapel.index')
                ->with('error', 'Mapel masih digunakan oleh soal/paket, tidak dapat dihapus.');
        }

        $mapel->delete();

        return redirect()->route('admin.mapel.index')
            ->with('success', 'Mapel berhasil dihapus.');
    }

    /**
     * @return array{ kode: string, nama: string, tingkat: string, jenis: string }
     */
    private function validateData(Request $request, ?Mapel $mapel = null): array
    {
        return $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('mapel', 'kode')->ignore($mapel)],
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
