<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Ai\AiProvider;
use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\JsonOutputException;
use App\Domain\Ai\JsonRepairService;
use App\Domain\Ai\PromptBuilder;
use App\Domain\Ai\SoalSkemaException;
use App\Domain\Ai\SoalSkemaValidator;
use App\Http\Controllers\Controller;
use App\Models\DetailPaketSoal;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\Soal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GeneratePaketController extends Controller
{
    public const MAKSIMAL_SOAL = 30;

    public const SOAL_PER_PART = 6;

    public const TINGKAT_OPTIONS = ['mudah', 'sedang', 'sulit', 'campuran'];

    public function __construct(private AiProvider $aiProvider) {}

    public function create(): View
    {
        $mapels = Mapel::with('kompetensiDasar')->orderBy('nama')->get();
        $generateInput = session('ai_generate_input', []);

        return view('admin.paket-soal.generate', compact('mapels', 'generateInput'));
    }

    public function storePart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mapel_id' => ['required', 'integer', Rule::exists('mapel', 'id')],
            'kompetensi_dasar_ids' => ['required', 'array', 'min:1'],
            'kompetensi_dasar_ids.*' => ['required', 'integer', Rule::exists('kompetensi_dasar', 'id')],
            'jumlah_soal' => ['required', 'integer', 'min:1', 'max:'.self::MAKSIMAL_SOAL],
            'tingkat_kesulitan' => ['required', Rule::in(self::TINGKAT_OPTIONS)],
            'referensi' => ['nullable', 'string', 'max:20000'],
            'part' => ['required', 'integer', 'min:1'],
            'run' => ['required', 'string', 'max:64'],
        ]);

        $mapel = Mapel::findOrFail((int) $validated['mapel_id']);

        $kds = $this->kdsUntukMapel($validated['kompetensi_dasar_ids'], $mapel);

        if ($kds->isEmpty()) {
            return response()->json([
                'ok' => false,
                'error' => 'Kompetensi dasar yang dipilih harus milik mapel tersebut.',
            ], 422);
        }

        $target = (int) $validated['jumlah_soal'];
        $part = (int) $validated['part'];
        $partTotal = (int) ceil($target / self::SOAL_PER_PART);

        if ($part < 1 || $part > $partTotal) {
            return response()->json(['ok' => false, 'error' => 'Nomor part tidak valid.'], 422);
        }

        $parts = $this->daftarPartsSesi($mapel, $kds, $validated, $target, $partTotal);

        if (($parts['parts_done'] ?? 0) >= $part || count($parts['daftar_soal']) >= $target) {
            return response()->json([
                'ok' => true,
                'part' => $part,
                'part_total' => $partTotal,
                'jumlah_part' => 0,
                'jumlah_akumulasi' => count($parts['daftar_soal']),
                'selesai' => count($parts['daftar_soal']) >= $target,
            ]);
        }

        $jumlahBagian = min(self::SOAL_PER_PART, $target - count($parts['daftar_soal']));
        $referensi = trim((string) ($validated['referensi'] ?? ''));

        $prompt = (new PromptBuilder)->build(
            $kds->map(fn (KompetensiDasar $kd): array => [
                'kode' => $kd->kode_kompetensi,
                'deskripsi' => $kd->deskripsi,
                'materi_pokok' => $kd->materi_pokok,
            ])->values()->all(),
            $mapel->nama,
            $jumlahBagian,
            $validated['tingkat_kesulitan'],
            $referensi !== '' ? $referensi : null,
        );

        try {
            $rawOutput = $this->aiProvider->generate($prompt);

            Log::channel('ai')->info(
                'AI generate part paket soal berjalan.',
                $this->konteksLog($mapel, $validated, $part, $partTotal, $jumlahBagian)
            );

            $decoded = (new JsonRepairService)->parse($rawOutput);
            $bagian = (new SoalSkemaValidator)->validate($decoded);
        } catch (AiProviderException|JsonOutputException|SoalSkemaException $exception) {
            Log::channel('ai')->warning(
                'AI generate part paket soal gagal.',
                $this->konteksLog($mapel, $validated, $part, $partTotal, $jumlahBagian)
                    + ['pesan' => $exception->getMessage()]
            );

            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        }

        $parts['daftar_soal'] = array_merge($parts['daftar_soal'], $bagian['daftar_soal']);
        $parts['soal_dibuang'] = array_merge($parts['soal_dibuang'], $bagian['soal_dibuang']);
        $parts['parts_done'] = max((int) ($parts['parts_done'] ?? 0), $part);

        if (trim((string) ($bagian['nama_paket'] ?? '')) !== '' && trim((string) ($parts['nama_paket'] ?? '')) === '') {
            $parts['nama_paket'] = $bagian['nama_paket'];
        }

        if (! empty($bagian['deskripsi']) && empty($parts['deskripsi'])) {
            $parts['deskripsi'] = $bagian['deskripsi'];
        }

        session(['ai_parts' => $parts]);
        session(['ai_generate_input' => $validated]);

        $selesai = count($parts['daftar_soal']) >= $target;

        return response()->json([
            'ok' => true,
            'part' => $part,
            'part_total' => $partTotal,
            'jumlah_part' => count($bagian['daftar_soal']),
            'jumlah_dibuang' => count($bagian['soal_dibuang']),
            'jumlah_akumulasi' => count($parts['daftar_soal']),
            'selesai' => $selesai,
        ]);
    }

    public function kurasi(): View|RedirectResponse
    {
        $draft = session('ai_draft');

        if (! is_array($draft)) {
            $parts = session('ai_parts');

            if (is_array($parts) && ($parts['daftar_soal'] ?? []) !== []) {
                $draft = $this->materialisasiDraft($parts);

                if (is_array($draft)) {
                    session(['ai_draft' => $draft]);
                }
            }
        }

        if (! is_array($draft)) {
            return redirect()->route('admin.paket-soal.generate')
                ->with('error', 'Belum ada hasil generate untuk dikurasi.');
        }

        $mapel = Mapel::find((int) ($draft['mapel_id'] ?? 0));

        if ($mapel === null) {
            return redirect()->route('admin.paket-soal.generate')
                ->with('error', 'Sesi generate sudah kedaluwarsa, silakan generate ulang.');
        }

        $kompetensiDasars = KompetensiDasar::where('mapel_id', $mapel->getKey())
            ->orderBy('kode_kompetensi')
            ->get();

        return view('admin.paket-soal.kurasi', compact('draft', 'mapel', 'kompetensiDasars'));
    }

    public function simpan(Request $request): RedirectResponse
    {
        $draft = session('ai_draft');

        if (! is_array($draft)) {
            return redirect()->route('admin.paket-soal.generate')
                ->with('error', 'Sesi generate sudah berakhir, silakan generate ulang.');
        }

        $mapel = Mapel::find((int) ($draft['mapel_id'] ?? 0));

        if ($mapel === null) {
            return redirect()->route('admin.paket-soal.generate')
                ->with('error', 'Sesi generate sudah kedaluwarsa, silakan generate ulang.');
        }

        $data = $this->validateKurasi($request, $mapel->getKey());

        $keSimpan = collect($request->input('daftar_soal', []))
            ->filter(fn (array $soal): bool => empty($soal['dihapus']))
            ->values()
            ->all();

        if ($keSimpan === []) {
            return back()->withErrors(['daftar_soal' => 'Minimal satu soal harus disimpan.'])->withInput();
        }

        DB::transaction(function () use ($request, $mapel, $keSimpan, $data, &$paket): void {
            $paket = PaketSoal::create([
                'nama_paket' => $data['nama_paket'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'mapel_id' => $mapel->getKey(),
                'created_by' => $request->user()->id,
            ]);

            foreach ($keSimpan as $soal) {
                $soalRow = Soal::create([
                    'kompetensi_dasar_id' => (int) $soal['kompetensi_dasar_id'],
                    'tipe_soal' => $soal['tipe_soal'],
                    'pertanyaan' => $soal['pertanyaan'],
                    'gambar_url' => $soal['gambar_url'] ?? null,
                    'pembahasan' => $soal['pembahasan'] ?? null,
                    'daftar_kategori' => $soal['daftar_kategori'] ?? null,
                    'a_diskriminasi' => $soal['a_diskriminasi'] ?? null,
                    'b_kesulitan' => $soal['b_kesulitan'] ?? null,
                    'c_tebakan' => $soal['c_tebakan'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $this->simpanChildren($soalRow, $soal);

                DetailPaketSoal::create([
                    'paket_soal_id' => $paket->getKey(),
                    'soal_id' => $soalRow->getKey(),
                ]);
            }
        });

        session()->forget(['ai_draft', 'ai_generate_input', 'ai_parts']);

        return redirect()->route('admin.paket-soal.index')
            ->with('success', "Paket '{$paket->nama_paket}' berhasil disimpan (".count($keSimpan).' soal).');
    }

    private function simpanChildren(Soal $soal, array $data): void
    {
        if ($soal->tipe_soal === Soal::TIPE_PG || $soal->tipe_soal === Soal::TIPE_PG_KOMPLEKS) {
            foreach ($data['opsi_jawaban'] as $opsi) {
                $soal->opsiJawaban()->create([
                    'teks_opsi' => $opsi['teks_opsi'],
                    'is_benar' => (bool) ($opsi['is_benar'] ?? false),
                    'urutan' => $opsi['urutan'] ?? null,
                    'a_diskriminasi' => $opsi['a_diskriminasi'] ?? null,
                    'b_kesulitan' => $opsi['b_kesulitan'] ?? null,
                    'c_tebakan' => $opsi['c_tebakan'] ?? null,
                ]);
            }

            return;
        }

        foreach ($data['pernyataan_kategori'] as $pernyataan) {
            $soal->pernyataanKategori()->create([
                'teks_pernyataan' => $pernyataan['teks_pernyataan'],
                'kategori_benar' => $pernyataan['kategori_benar'],
                'urutan' => $pernyataan['urutan'] ?? null,
                'a_diskriminasi' => $pernyataan['a_diskriminasi'] ?? null,
                'b_kesulitan' => $pernyataan['b_kesulitan'] ?? null,
                'c_tebakan' => $pernyataan['c_tebakan'] ?? null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateKurasi(Request $request, int $mapelId): array
    {
        $daftar = $request->input('daftar_soal', []);
        $rules = [
            'nama_paket' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
        ];

        foreach (array_keys($daftar) as $index) {
            $spesifikasi = $daftar[$index];

            if (! empty($spesifikasi['dihapus'])) {
                continue;
            }

            $prefix = "daftar_soal.{$index}";

            $rules += [
                "{$prefix}.kompetensi_dasar_id" => ['required', 'integer', Rule::exists('kompetensi_dasar', 'id')->where('mapel_id', $mapelId)],
                "{$prefix}.tipe_soal" => ['required', Rule::in([Soal::TIPE_PG, Soal::TIPE_PG_KOMPLEKS, Soal::TIPE_PG_KATEGORI])],
                "{$prefix}.pertanyaan" => ['required', 'string'],
                "{$prefix}.gambar_url" => ['nullable', 'url', 'max:255'],
                "{$prefix}.pembahasan" => ['nullable', 'string'],
                "{$prefix}.a_diskriminasi" => ['nullable', 'numeric', 'min:0.5', 'max:2.5'],
                "{$prefix}.b_kesulitan" => ['nullable', 'numeric', 'min:-3', 'max:3'],
                "{$prefix}.c_tebakan" => ['nullable', 'numeric', 'min:0', 'max:0.35'],
                "{$prefix}.daftar_kategori" => ['nullable', 'array', 'min:1'],
                "{$prefix}.daftar_kategori.*" => ['required', 'string', 'max:50'],
                "{$prefix}.opsi_jawaban" => ['nullable', 'array', 'min:5', 'max:5'],
                "{$prefix}.opsi_jawaban.*.teks_opsi" => ['required_with:{$prefix}.opsi_jawaban', 'string'],
                "{$prefix}.opsi_jawaban.*.is_benar" => ['required_with:{$prefix}.opsi_jawaban', 'boolean'],
                "{$prefix}.opsi_jawaban.*.urutan" => ['nullable', 'integer'],
                "{$prefix}.opsi_jawaban.*.a_diskriminasi" => ['nullable', 'numeric', 'min:0.5', 'max:2.5'],
                "{$prefix}.opsi_jawaban.*.b_kesulitan" => ['nullable', 'numeric', 'min:-3', 'max:3'],
                "{$prefix}.opsi_jawaban.*.c_tebakan" => ['nullable', 'numeric', 'min:0', 'max:0.35'],
                "{$prefix}.pernyataan_kategori" => ['nullable', 'array', 'min:2', 'max:5'],
                "{$prefix}.pernyataan_kategori.*.teks_pernyataan" => ['required_with:{$prefix}.pernyataan_kategori', 'string'],
                "{$prefix}.pernyataan_kategori.*.kategori_benar" => ['required_with:{$prefix}.pernyataan_kategori', 'string', Rule::in($spesifikasi['daftar_kategori'] ?? [])],
                "{$prefix}.pernyataan_kategori.*.urutan" => ['nullable', 'integer'],
                "{$prefix}.pernyataan_kategori.*.a_diskriminasi" => ['nullable', 'numeric', 'min:0.5', 'max:2.5'],
                "{$prefix}.pernyataan_kategori.*.b_kesulitan" => ['nullable', 'numeric', 'min:-3', 'max:3'],
                "{$prefix}.pernyataan_kategori.*.c_tebakan" => ['nullable', 'numeric', 'min:0', 'max:0.35'],
            ];
        }

        $validator = ValidatorFacade::make($request->all(), $rules);

        $validator->after(function (Validator $validator) use ($daftar): void {
            foreach (array_keys($daftar) as $index) {
                $spesifikasi = $daftar[$index];

                if (! empty($spesifikasi['dihapus'])) {
                    continue;
                }

                $this->periksaAturanPerTipe($validator, $index, $spesifikasi);
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $spesifikasi
     */
    private function periksaAturanPerTipe(Validator $validator, string|int $index, array $spesifikasi): void
    {
        $tipe = $spesifikasi['tipe_soal'] ?? null;
        $opsi = $spesifikasi['opsi_jawaban'] ?? [];

        $jumlahBenar = collect($opsi)
            ->filter(fn ($opsiItem): bool => is_array($opsiItem) && (bool) ($opsiItem['is_benar'] ?? false))
            ->count();

        if ($tipe === Soal::TIPE_PG && $jumlahBenar !== 1) {
            $validator->errors()->add("daftar_soal.{$index}.opsi_jawaban", 'Soal PG harus memiliki tepat 1 jawaban benar.');
        }

        if ($tipe === Soal::TIPE_PG_KOMPLEKS && $jumlahBenar < 2) {
            $validator->errors()->add("daftar_soal.{$index}.opsi_jawaban", 'Soal PG Kompleks harus memiliki minimal 2 jawaban benar.');
        }

        if ($tipe !== Soal::TIPE_PG_KATEGORI) {
            return;
        }

        if ($opsi !== []) {
            $validator->errors()->add("daftar_soal.{$index}.opsi_jawaban", 'Soal PG Kategori tidak menggunakan opsi jawaban.');
        }

        if (count($spesifikasi['daftar_kategori'] ?? []) < 1) {
            $validator->errors()->add("daftar_soal.{$index}.daftar_kategori", 'Daftar kategori wajib diisi.');
        }

        if (count($spesifikasi['pernyataan_kategori'] ?? []) < 2) {
            $validator->errors()->add("daftar_soal.{$index}.pernyataan_kategori", 'Minimal 2 pernyataan wajib diisi.');
        }
    }

    /**
     * @param  array<int, int|string>  $kompetensiDasarIds
     */
    private function kdsUntukMapel(array $kompetensiDasarIds, Mapel $mapel): Collection
    {
        return KompetensiDasar::whereIn('id', $kompetensiDasarIds)
            ->where('mapel_id', $mapel->getKey())
            ->get();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function daftarPartsSesi(Mapel $mapel, Collection $kds, array $validated, int $target, int $partTotal): array
    {
        $parts = session('ai_parts');

        if (is_array($parts)
            && (int) ($parts['mapel_id'] ?? 0) === $mapel->getKey()
            && (int) ($parts['target'] ?? 0) === $target
            && ($parts['run'] ?? null) === $validated['run']) {
            return $parts;
        }

        session()->forget('ai_draft');

        return [
            'run' => $validated['run'],
            'mapel_id' => $mapel->getKey(),
            'target' => $target,
            'part_total' => $partTotal,
            'tingkat_kesulitan' => $validated['tingkat_kesulitan'],
            'referensi' => $validated['referensi'] ?? '',
            'nama_paket' => '',
            'deskripsi' => null,
            'parts_done' => 0,
            'daftar_soal' => [],
            'soal_dibuang' => [],
            'kds' => $kds->map(fn (KompetensiDasar $kd): array => [
                'id' => $kd->getKey(),
                'kode' => $kd->kode_kompetensi,
                'deskripsi' => $kd->deskripsi,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $parts
     * @return array<string, mixed>|null
     */
    private function materialisasiDraft(array $parts): ?array
    {
        $mapel = Mapel::find((int) ($parts['mapel_id'] ?? 0));

        if ($mapel === null) {
            return null;
        }

        $namaPaket = trim((string) ($parts['nama_paket'] ?? ''));

        return [
            'metadata' => [
                'mapel' => $mapel->nama,
                'tingkat_kesulitan' => $parts['tingkat_kesulitan'] ?? null,
            ],
            'nama_paket' => $namaPaket !== '' ? $namaPaket : 'Paket '.$mapel->nama,
            'deskripsi' => $parts['deskripsi'] ?? null,
            'jumlah_soal' => count($parts['daftar_soal']),
            'daftar_soal' => $parts['daftar_soal'],
            'soal_dibuang' => $parts['soal_dibuang'] ?? [],
            'mapel_id' => $mapel->getKey(),
            'kds' => $parts['kds'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function konteksLog(Mapel $mapel, array $validated, ?int $part = null, ?int $partTotal = null, ?int $jumlahBagian = null): array
    {
        return [
            'mapel_id' => $mapel->getKey(),
            'mapel' => $mapel->nama,
            'jumlah_soal' => (int) ($validated['jumlah_soal'] ?? 0),
            'tingkat_kesulitan' => $validated['tingkat_kesulitan'] ?? null,
            'jumlah_kd' => count($validated['kompetensi_dasar_ids'] ?? []),
            'part' => $part,
            'part_total' => $partTotal,
            'jumlah_bagian' => $jumlahBagian,
        ];
    }
}
