<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Soal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaketSoalController extends Controller
{
    public function index(): View
    {
        $paketSoals = PaketSoal::with('mapel')
            ->withCount('soal')
            ->orderByDesc('id')
            ->get();

        return view('admin.paket-soal.index', compact('paketSoals'));
    }

    public function create(): View
    {
        $mapels = Mapel::orderBy('kode')->get();
        $soals = Soal::with('kompetensiDasar.mapel')->orderBy('kompetensi_dasar_id')->get();

        return view('admin.paket-soal.create', compact('mapels', 'soals'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $request) {
            $paketSoal = PaketSoal::create([
                ...Arr::except($data, ['soal_ids']),
                'created_by' => $request->user()->id,
            ]);
            $paketSoal->soal()->attach($data['soal_ids']);
        });

        return redirect()->route('admin.paket-soal.index')
            ->with('success', 'Paket soal berhasil ditambahkan.');
    }

    public function edit(PaketSoal $paketSoal): View
    {
        $paketSoal->load('soal');
        $mapels = Mapel::orderBy('kode')->get();
        $soals = Soal::with('kompetensiDasar.mapel')->orderBy('kompetensi_dasar_id')->get();

        return view('admin.paket-soal.edit', compact('paketSoal', 'mapels', 'soals'));
    }

    public function update(Request $request, PaketSoal $paketSoal): RedirectResponse
    {
        $data = $this->validateData($request, $paketSoal);

        DB::transaction(function () use ($data, $paketSoal) {
            $paketSoal->update(Arr::except($data, ['soal_ids']));
            $paketSoal->soal()->sync($data['soal_ids']);
        });

        return redirect()->route('admin.paket-soal.index')
            ->with('success', 'Paket soal berhasil diperbarui.');
    }

    public function destroy(PaketSoal $paketSoal): RedirectResponse
    {
        $dipakai = PaketTryout::query()
            ->where(fn (Builder $q) => $q
                ->where('paket_soal_wajib_1_id', $paketSoal->getKey())
                ->orWhere('paket_soal_wajib_2_id', $paketSoal->getKey())
                ->orWhere('paket_soal_wajib_3_id', $paketSoal->getKey())
                ->orWhere('paket_soal_pilihan_1_id', $paketSoal->getKey())
                ->orWhere('paket_soal_pilihan_2_id', $paketSoal->getKey()))
            ->exists();

        if ($dipakai) {
            return redirect()->route('admin.paket-soal.index')
                ->with('error', 'Paket soal masih digunakan oleh tryout, tidak dapat dihapus.');
        }

        $paketSoal->delete();

        return redirect()->route('admin.paket-soal.index')
            ->with('success', 'Paket soal berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?PaketSoal $paketSoal = null): array
    {
        $mapelId = (int) $request->input('mapel_id');
        $soalIds = array_values(array_unique($request->input('soal_ids', [])));

        $validator = ValidatorFacade::make($request->all(), [
            'nama_paket' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'mapel_id' => ['required', 'integer', Rule::exists('mapel', 'id')->whereNull('deleted_at')],
            'soal_ids' => ['required', 'array', 'min:1'],
            'soal_ids.*' => ['integer', Rule::exists('soal', 'id')->whereNull('deleted_at')],
        ]);

        $validator->after(function (Validator $validator) use ($mapelId, $soalIds, $request) {
            if (count($soalIds) !== count($request->input('soal_ids', []))) {
                $validator->errors()->add('soal_ids', 'Soal tidak boleh duplikat dalam satu paket.');
            }

            $soalSesuaiMapel = Soal::query()
                ->whereIn('id', $soalIds)
                ->whereHas('kompetensiDasar', fn (Builder $q) => $q->where('mapel_id', $mapelId))
                ->count();

            if ($soalSesuaiMapel !== count($soalIds)) {
                $validator->errors()->add('soal_ids', 'Semua soal dalam paket harus berasal dari mapel yang dipilih.');
            }
        });

        $data = $validator->validate();

        return [...$data, 'soal_ids' => $soalIds];
    }
}
