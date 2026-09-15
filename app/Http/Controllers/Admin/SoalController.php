<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailPaketSoal;
use App\Models\KompetensiDasar;
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

class SoalController extends Controller
{
    public function index(Request $request): View
    {
        $soals = Soal::with(['kompetensiDasar.mapel', 'opsiJawaban', 'pernyataanKategori'])
            ->when($request->filled('kompetensi_dasar_id'), fn (Builder $q) => $q->where('kompetensi_dasar_id', $request->query('kompetensi_dasar_id')))
            ->when($request->filled('tipe_soal'), fn (Builder $q) => $q->where('tipe_soal', $request->query('tipe_soal')))
            ->orderByDesc('id')
            ->get();

        $kompetensiDasars = KompetensiDasar::with('mapel')->orderBy('mapel_id')->orderBy('kode_kompetensi')->get();

        return view('admin.soal.index', compact('soals', 'kompetensiDasars'));
    }

    public function create(): View
    {
        $kompetensiDasars = KompetensiDasar::with('mapel')->orderBy('mapel_id')->orderBy('kode_kompetensi')->get();

        return view('admin.soal.create', compact('kompetensiDasars'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['created_by'] = $request->user()->id;

        DB::transaction(function () use ($data, &$soal) {
            $children = ['opsi_jawaban' => $data['opsi_jawaban'] ?? [], 'pernyataan_kategori' => $data['pernyataan_kategori'] ?? []];

            $soal = Soal::create($this->soalAttributes($data));
            $this->syncChildren($soal, $children);
        });

        return redirect()->route('admin.soal.index')
            ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function edit(Soal $soal): View
    {
        $soal->load(['opsiJawaban', 'pernyataanKategori']);
        $kompetensiDasars = KompetensiDasar::with('mapel')->orderBy('mapel_id')->orderBy('kode_kompetensi')->get();

        return view('admin.soal.edit', compact('soal', 'kompetensiDasars'));
    }

    public function update(Request $request, Soal $soal): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $soal) {
            $children = ['opsi_jawaban' => $data['opsi_jawaban'] ?? [], 'pernyataan_kategori' => $data['pernyataan_kategori'] ?? []];

            $soal->update($this->soalAttributes($data));
            $soal->opsiJawaban()->delete();
            $soal->pernyataanKategori()->delete();
            $this->syncChildren($soal, $children);
        });

        return redirect()->route('admin.soal.index')
            ->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Soal $soal): RedirectResponse
    {
        if (DetailPaketSoal::query()->where('soal_id', $soal->getKey())->exists()) {
            return redirect()->route('admin.soal.index')
                ->with('error', 'Soal masih digunakan oleh paket soal, tidak dapat dihapus.');
        }

        $soal->delete();

        return redirect()->route('admin.soal.index')
            ->with('success', 'Soal berhasil dihapus.');
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
    private function validateData(Request $request): array
    {
        $tipe = $request->input('tipe_soal');
        $opsiJawaban = $request->input('opsi_jawaban', []);
        $pernyataanKategori = $request->input('pernyataan_kategori', []);
        $daftarKategori = $request->input('daftar_kategori', []);

        $jumlahBenar = collect($opsiJawaban)
            ->filter(fn (array $opsi) => (bool) ($opsi['is_benar'] ?? false))
            ->count();

        $validator = ValidatorFacade::make($request->all(), [
            'kompetensi_dasar_id' => ['required', 'integer', Rule::exists('kompetensi_dasar', 'id')],
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
            'opsi_jawaban' => ['nullable', 'array', 'min:5', 'max:5'],
            'opsi_jawaban.*.teks_opsi' => ['required_with:opsi_jawaban', 'string'],
            'opsi_jawaban.*.is_benar' => ['required_with:opsi_jawaban', 'boolean'],
            'opsi_jawaban.*.urutan' => ['nullable', 'integer'],
            'pernyataan_kategori' => ['nullable', 'array', 'min:2', 'max:5'],
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
                if (count($pernyataanKategori) < 2) {
                    $validator->errors()->add('pernyataan_kategori', 'Minimal 2 pernyataan wajib diisi.');
                }
            }
        });

        return $validator->validate();
    }
}
