<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailPaketSoal;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\Soal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SoalController extends Controller
{
    public function index(Request $request, Mapel $mapel): View
    {
        $soals = Soal::with(['kompetensiDasar.mapel', 'opsiJawaban', 'pernyataanKategori'])
            ->whereHas('kompetensiDasar', fn (Builder $q) => $q->where('mapel_id', $mapel->getKey()))
            ->when($request->filled('kompetensi_dasar_id'), fn (Builder $q) => $q->where('kompetensi_dasar_id', $request->query('kompetensi_dasar_id')))
            ->when($request->filled('tipe_soal'), fn (Builder $q) => $q->where('tipe_soal', $request->query('tipe_soal')))
            ->orderByDesc('id')
            ->get();

        $kompetensiDasars = $this->kompetensiDasarMapel($mapel);

        $jumlahSoal = $soals->count();

        return view('admin.mapel.soal.index', compact('soals', 'kompetensiDasars', 'jumlahSoal', 'mapel'));
    }

    public function create(Mapel $mapel): View
    {
        $kompetensiDasars = $this->kompetensiDasarMapel($mapel);

        return view('admin.mapel.soal.create', compact('kompetensiDasars', 'mapel'));
    }

    public function store(Request $request, Mapel $mapel): RedirectResponse
    {
        $data = $this->validateData($request, $mapel);
        $data['created_by'] = $request->user()->id;

        DB::transaction(function () use ($data, &$soal) {
            $children = ['opsi_jawaban' => $data['opsi_jawaban'] ?? [], 'pernyataan_kategori' => $data['pernyataan_kategori'] ?? []];

            $soal = Soal::create($this->soalAttributes($data));
            $this->syncChildren($soal, $children);
        });

        return redirect()->route('admin.mapel.soal.index', $mapel)
            ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function edit(Mapel $mapel, Soal $soal): View
    {
        $soal->load(['opsiJawaban', 'pernyataanKategori']);
        $kompetensiDasars = $this->kompetensiDasarMapel($mapel);

        return view('admin.mapel.soal.edit', compact('soal', 'kompetensiDasars', 'mapel'));
    }

    public function update(Request $request, Mapel $mapel, Soal $soal): RedirectResponse
    {
        $data = $this->validateData($request, $mapel);

        DB::transaction(function () use ($data, $soal) {
            $children = ['opsi_jawaban' => $data['opsi_jawaban'] ?? [], 'pernyataan_kategori' => $data['pernyataan_kategori'] ?? []];

            $soal->update($this->soalAttributes($data));
            $soal->opsiJawaban()->delete();
            $soal->pernyataanKategori()->delete();
            $this->syncChildren($soal, $children);
        });

        return redirect()->route('admin.mapel.soal.index', $mapel)
            ->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Mapel $mapel, Soal $soal): RedirectResponse
    {
        if (DetailPaketSoal::query()->where('soal_id', $soal->getKey())->whereHas('paketSoal')->exists()) {
            return redirect()->route('admin.mapel.soal.index', $mapel)
                ->with('error', 'Soal masih digunakan oleh paket soal, tidak dapat dihapus.');
        }

        $soal->delete();

        return redirect()->route('admin.mapel.soal.index', $mapel)
            ->with('success', 'Soal berhasil dihapus.');
    }

    public function bulkDestroy(Request $request, Mapel $mapel): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'all' => ['nullable', 'boolean'],
            'kompetensi_dasar_id' => ['nullable', 'integer'],
            'tipe_soal' => ['nullable', Rule::in([
                Soal::TIPE_PG,
                Soal::TIPE_PG_KOMPLEKS,
                Soal::TIPE_PG_KATEGORI,
            ])],
        ]);

        if (($data['all'] ?? false) !== true && empty($data['ids'])) {
            return redirect()->route('admin.mapel.soal.index', $mapel)
                ->with('error', 'Tidak ada soal yang dipilih.');
        }

        $kandidatIds = Soal::query()
            ->whereHas('kompetensiDasar', fn (Builder $q) => $q->where('mapel_id', $mapel->getKey()))
            ->when($request->filled('kompetensi_dasar_id'), fn (Builder $q) => $q->where('kompetensi_dasar_id', $data['kompetensi_dasar_id']))
            ->when($request->filled('tipe_soal'), fn (Builder $q) => $q->where('tipe_soal', $data['tipe_soal']))
            ->when(($data['all'] ?? false) !== true, fn (Builder $q) => $q->whereIn('id', $data['ids']))
            ->pluck('id');

        $dipakaiIds = DetailPaketSoal::query()
            ->whereIn('soal_id', $kandidatIds)
            ->whereHas('paketSoal')
            ->pluck('soal_id')
            ->unique()
            ->values();

        $terhapus = Soal::query()
            ->whereIn('id', $kandidatIds)
            ->whereNotIn('id', $dipakaiIds)
            ->delete();

        $pesan = $terhapus > 0
            ? "{$terhapus} soal berhasil dihapus"
            : 'Tidak ada soal yang dihapus';
        $pesan .= $dipakaiIds->isNotEmpty()
            ? ", {$dipakaiIds->count()} soal dilewati karena masih digunakan oleh paket soal."
            : '.';

        return redirect()->route('admin.mapel.soal.index', $mapel)
            ->with($terhapus > 0 ? 'success' : 'error', $pesan);
    }

    /**
     * @return Collection<int, KompetensiDasar>
     */
    private function kompetensiDasarMapel(Mapel $mapel): Collection
    {
        return $mapel->kompetensiDasars()->orderBy('kode_kompetensi')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function soalAttributes(array $data): array
    {
        foreach (['a_diskriminasi', 'b_kesulitan', 'c_tebakan'] as $key) {
            if (is_null($data[$key] ?? null)) {
                unset($data[$key]);
            }
        }
        if (! isset($data['daftar_kategori']) || is_null($data['daftar_kategori'])) {
            unset($data['daftar_kategori']);
        }
        $data['daftar_kategori'] ??= null;

        return Arr::except($data, ['opsi_jawaban', 'pernyataan_kategori']);
    }

    /**
     * @param  array<string, mixed>  $children
     */
    private function syncChildren(Soal $soal, array $children): void
    {
        if ($soal->tipe_soal === Soal::TIPE_PG || $soal->tipe_soal === Soal::TIPE_PG_KOMPLEKS) {
            foreach ($children['opsi_jawaban'] as $opsi) {
                $soal->opsiJawaban()->create([
                    'teks_opsi' => $opsi['teks_opsi'],
                    'is_benar' => (bool) $opsi['is_benar'],
                    'urutan' => $opsi['urutan'] ?? null,
                ]);
            }

            return;
        }

        foreach ($children['pernyataan_kategori'] as $pernyataan) {
            $soal->pernyataanKategori()->create([
                'teks_pernyataan' => $pernyataan['teks_pernyataan'],
                'kategori_benar' => $pernyataan['kategori_benar'],
                'urutan' => $pernyataan['urutan'] ?? null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, Mapel $mapel): array
    {
        $tipe = $request->input('tipe_soal');
        $opsiJawaban = $request->input('opsi_jawaban', []);
        $pernyataanKategori = $request->input('pernyataan_kategori', []);
        $daftarKategori = $request->input('daftar_kategori', []);

        $opsiJawaban = is_array($opsiJawaban) ? $opsiJawaban : [];
        $pernyataanKategori = is_array($pernyataanKategori) ? $pernyataanKategori : [];
        $daftarKategori = is_array($daftarKategori) ? $daftarKategori : [];

        $jumlahBenar = collect($opsiJawaban)
            ->filter(fn ($opsi): bool => is_array($opsi) && (bool) ($opsi['is_benar'] ?? false))
            ->count();

        $validator = ValidatorFacade::make($request->all(), [
            'kompetensi_dasar_id' => ['required', 'integer', Rule::exists('kompetensi_dasar', 'id')->where('mapel_id', $mapel->getKey())],
            'tipe_soal' => ['required', Rule::in([
                Soal::TIPE_PG,
                Soal::TIPE_PG_KOMPLEKS,
                Soal::TIPE_PG_KATEGORI,
            ])],
            'pertanyaan' => ['required', 'string'],
            'gambar_url' => ['nullable', 'url', 'max:255'],
            'pembahasan' => ['nullable', 'string'],
            'a_diskriminasi' => ['nullable', 'numeric', 'min:0.5', 'max:2.5'],
            'b_kesulitan' => ['nullable', 'numeric', 'min:-3', 'max:3'],
            'c_tebakan' => ['nullable', 'numeric', 'min:0', 'max:0.35'],
            'daftar_kategori' => ['nullable', 'array', 'min:1'],
            'daftar_kategori.*' => ['required', 'string', 'max:50'],
            'opsi_jawaban' => ['nullable', 'array', 'min:5', 'max:8'],
            'opsi_jawaban.*.teks_opsi' => ['required_with:opsi_jawaban', 'string'],
            'opsi_jawaban.*.is_benar' => ['required_with:opsi_jawaban', 'boolean'],
            'opsi_jawaban.*.urutan' => ['nullable', 'integer'],
            'pernyataan_kategori' => ['nullable', 'array', 'min:3', 'max:5'],
            'pernyataan_kategori.*.teks_pernyataan' => ['required_with:pernyataan_kategori', 'string'],
            'pernyataan_kategori.*.kategori_benar' => ['required_with:pernyataan_kategori', 'string', Rule::in($daftarKategori)],
            'pernyataan_kategori.*.urutan' => ['nullable', 'integer'],
        ]);

        $validator->after(function (Validator $validator) use ($tipe, $opsiJawaban, $jumlahBenar, $daftarKategori, $pernyataanKategori): void {
            if ($tipe === Soal::TIPE_PG && $jumlahBenar !== 1) {
                $validator->errors()->add('opsi_jawaban', 'Soal PG harus memiliki tepat 1 jawaban benar.');
            }
            if ($tipe === Soal::TIPE_PG_KOMPLEKS && $jumlahBenar < 2) {
                $validator->errors()->add('opsi_jawaban', 'Soal PG Kompleks harus memiliki minimal 2 jawaban benar.');
            }
            if ($tipe === Soal::TIPE_PG_KATEGORI) {
                if (! empty($opsiJawaban)) {
                    $validator->errors()->add('opsi_jawaban', 'Soal PG Kategori tidak menggunakan opsi jawaban.');
                }
                if (count($daftarKategori) < 1) {
                    $validator->errors()->add('daftar_kategori', 'Daftar kategori wajib diisi.');
                }
                if (count($pernyataanKategori) < 3) {
                    $validator->errors()->add('pernyataan_kategori', 'Minimal 3 pernyataan wajib diisi.');
                }
            }
        });

        return $validator->validate();
    }
}
