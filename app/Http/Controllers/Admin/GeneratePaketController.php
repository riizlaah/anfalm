<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Ai\AiProvider;
use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\JsonOutputException;
use App\Domain\Ai\JsonRepairService;
use App\Domain\Ai\PenjadwalKd;
use App\Domain\Ai\PromptBuilder;
use App\Domain\Ai\SoalSkemaException;
use App\Domain\Ai\SoalSkemaValidator;
use App\Domain\Konten\KontenSanitizer;
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

    public function __construct(private AiProvider $aiProvider, private KontenSanitizer $konten) {}

    public function create(): View
    {
        $mapels = Mapel::with('kompetensiDasars')->orderBy('nama')->get();
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

        $sesi = $this->sesiPartsCocok($mapel, $validated, $target);
        $akumulasi = count($sesi['daftar_soal'] ?? []);
        $partsDone = (int) ($sesi['parts_done'] ?? 0);

        if ($akumulasi >= $target) {
            return $this->responsPart($part, $partsDone, $akumulasi, $target);
        }

        $partTotal = $this->perkiraanPartTotal($partsDone, $akumulasi, $target);

        if ($part < 1 || $part > $partTotal) {
            return response()->json(['ok' => false, 'error' => 'Nomor part tidak valid.'], 422);
        }

        $parts = $this->daftarPartsSesi($mapel, $kds, $validated, $target, $partTotal);

        if ((int) $parts['parts_done'] >= $part) {
            return $this->responsPart(
                $part,
                (int) $parts['parts_done'],
                count($parts['daftar_soal']),
                $target
            );
        }

        $jumlahBagian = min(self::SOAL_PER_PART, $target - count($parts['daftar_soal']));
        $referensi = trim((string) ($validated['referensi'] ?? ''));

        // Kunci: kode ternormalisasi (untuk mencocokkan jawaban AI), nilai: kode
        // persis seperti di database (untuk ditampilkan ke AI dan halaman kurasi).
        $kodeKd = $kds->mapWithKeys(fn (KompetensiDasar $kd): array => [
            $this->normalisasiKodeKd($kd->kode_kompetensi) => $kd->kode_kompetensi,
        ])->all();

        $jadwal = (new PenjadwalKd)->targetPart(
            $target,
            array_keys($kodeKd),
            $this->kdTerpakai($parts['daftar_soal']),
            $jumlahBagian,
        );

        $kodePart = [];
        $targetPerKd = [];

        foreach ($jadwal as $baris) {
            $kode = $kodeKd[$baris['kode']];
            $kodePart[] = $kode;
            $targetPerKd[$kode] = $baris['target'];
        }

        $prompt = (new PromptBuilder)->build(
            $this->kdsUntukPart($kds, $kodePart),
            $mapel->nama,
            $jumlahBagian,
            $validated['tingkat_kesulitan'],
            $referensi !== '' ? $referensi : null,
            $targetPerKd,
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

        $parts['daftar_soal'] = $this->renumberIdSoal(
            array_merge($parts['daftar_soal'], $bagian['daftar_soal'])
        );
        $parts['soal_dibuang'] = array_merge(
            $parts['soal_dibuang'],
            $this->prefixIdDibuang($bagian['soal_dibuang'], $part)
        );
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

        Log::channel('ai')->info(
            'AI generate part paket soal selesai diproses.',
            $this->konteksLog($mapel, $validated, $part, $partTotal, $jumlahBagian)
                + [
                    'jumlah_diterima' => count($bagian['daftar_soal']),
                    'jumlah_dibuang' => count($bagian['soal_dibuang']),
                    'jumlah_akumulasi' => count($parts['daftar_soal']),
                    'selesai' => $selesai,
                ]
        );

        return $this->responsPart(
            $part,
            (int) $parts['parts_done'],
            count($parts['daftar_soal']),
            $target,
            count($bagian['daftar_soal']),
            count($bagian['soal_dibuang'])
        );
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

        // Sudah termasuk old() bila sebelumnya ada submit yang gagal, sehingga
        // penanda "KD belum cocok" ikut terbawa saat halaman dirender ulang.
        [$draft['daftar_soal'], $kdBelumCocok] = $this->cocokkanKdSoal(
            old('daftar_soal', $draft['daftar_soal'] ?? []),
            $kompetensiDasars,
        );

        return view('admin.paket-soal.kurasi', compact('draft', 'mapel', 'kompetensiDasars', 'kdBelumCocok'));
    }

    public function simpan(Request $request): RedirectResponse
    {
        if (! $this->isiDaftarSoalDariJson($request)) {
            return back()->withErrors([
                'daftar_soal' => 'Data soal tidak terbaca dengan benar. Muat ulang halaman, lalu coba lagi.',
            ]);
        }

        $draft = session('ai_draft');

        if (! is_array($draft)) {
            return redirect()->route('admin.paket-soal.generate')
                ->with('error', 'Sesi generate sudah berakhir, silakan generate ulang.');
        }

        if ($this->jumlahSoalTerkirimTidakSesuai($request, $draft)) {
            return back()->withErrors([
                'daftar_soal' => 'Sebagian soal tidak ikut terkirim ('
                    .count($request->input('daftar_soal', []))
                    .' dari '
                    .count(old('daftar_soal') ?? ($draft['daftar_soal'] ?? []))
                    .'). Muat ulang halaman, lalu coba lagi.',
            ]);
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
                    'pertanyaan' => $this->konten->bersihkan($soal['pertanyaan']),
                    'gambar_url' => $soal['gambar_url'] ?? null,
                    'pembahasan' => $this->konten->bersihkan($soal['pembahasan'] ?? null),
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

    /**
     * Halaman kurasi mengirim seluruh daftar soal sebagai satu field JSON
     * (`daftar_soal_json`) agar tidak tergantung pada max_input_vars PHP yang
     * default hanya 1000 — 30 soal pada form biasa membutuhkan ±1.220 field.
     *
     * Jalur lama (daftar_soal sebagai array) tetap diterima untuk kompatibilitas.
     */
    private function isiDaftarSoalDariJson(Request $request): bool
    {
        if (! $request->has('daftar_soal_json')) {
            return true;
        }

        $raw = $request->input('daftar_soal_json');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($decoded) || $decoded === []) {
            return false;
        }

        $request->merge(['daftar_soal' => $this->kosongkanStringKosong($decoded)]);

        return true;
    }

    /**
     * Middleware ConvertEmptyStringsToNull tidak menyentuh isi body JSON,
     * jadi string kosong di dalamnya harus dinetralkan manual agar identik
     * dengan jalur form biasa (nilai kosong pada kolom numerik merusak insert).
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function kosongkanStringKosong(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = is_array($value)
                ? $this->kosongkanStringKosong($value)
                : ($value === '' ? null : $value);
        }

        return $data;
    }

    /**
     * Jumlah soal terkirim harus sama dengan jumlah yang dirender oleh halaman
     * (old input bila ada, selain itu draft sesi). Bila beda, lebih baik
     * menolak daripada diam-diam menyimpan paket parsial.
     *
     * @param  array<string, mixed>  $draft
     */
    private function jumlahSoalTerkirimTidakSesuai(Request $request, array $draft): bool
    {
        if (! $request->has('daftar_soal_json')) {
            return false;
        }

        $dirender = old('daftar_soal') ?? ($draft['daftar_soal'] ?? null);

        if (! is_array($dirender) || $dirender === []) {
            return false;
        }

        return count($request->input('daftar_soal', [])) !== count($dirender);
    }

    private function simpanChildren(Soal $soal, array $data): void
    {
        if ($soal->tipe_soal === Soal::TIPE_PG || $soal->tipe_soal === Soal::TIPE_PG_KOMPLEKS) {
            foreach ($data['opsi_jawaban'] as $opsi) {
                $soal->opsiJawaban()->create([
                    'teks_opsi' => $this->konten->bersihkan($opsi['teks_opsi']),
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
                'teks_pernyataan' => $this->konten->bersihkan($pernyataan['teks_pernyataan']),
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
                "{$prefix}.opsi_jawaban" => ['nullable', 'array', 'min:5', 'max:8'],
                "{$prefix}.opsi_jawaban.*.teks_opsi" => ["required_with:{$prefix}.opsi_jawaban", 'string'],
                "{$prefix}.opsi_jawaban.*.is_benar" => ["required_with:{$prefix}.opsi_jawaban", 'boolean'],
                "{$prefix}.opsi_jawaban.*.urutan" => ['nullable', 'integer'],
                "{$prefix}.opsi_jawaban.*.a_diskriminasi" => ['nullable', 'numeric', 'min:0.5', 'max:2.5'],
                "{$prefix}.opsi_jawaban.*.b_kesulitan" => ['nullable', 'numeric', 'min:-3', 'max:3'],
                "{$prefix}.opsi_jawaban.*.c_tebakan" => ['nullable', 'numeric', 'min:0', 'max:0.35'],
                "{$prefix}.pernyataan_kategori" => ['nullable', 'array', 'min:3', 'max:5'],
                "{$prefix}.pernyataan_kategori.*.teks_pernyataan" => ["required_with:{$prefix}.pernyataan_kategori", 'string'],
                "{$prefix}.pernyataan_kategori.*.kategori_benar" => ["required_with:{$prefix}.pernyataan_kategori", 'string', Rule::in($spesifikasi['daftar_kategori'] ?? [])],
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

        if (count($spesifikasi['pernyataan_kategori'] ?? []) < 3) {
            $validator->errors()->add("daftar_soal.{$index}.pernyataan_kategori", 'Minimal 3 pernyataan wajib diisi.');
        }
    }

    /**
     * @param  array<int, int|string>  $kompetensiDasarIds
     */
    private function kdsUntukMapel(array $kompetensiDasarIds, Mapel $mapel): Collection
    {
        // Urutan kode dipakai sebagai pengikat seri di PenjadwalKd, jadi wajib
        // deterministik agar jadwal yang sama bisa direproduksi tiap part.
        return KompetensiDasar::whereIn('id', $kompetensiDasarIds)
            ->where('mapel_id', $mapel->getKey())
            ->orderBy('kode_kompetensi')
            ->get();
    }

    /**
     * Kode KD dibakukan agar kecocokan tidak gugur hanya karena beda huruf
     * besar-kecil, spasi ganda, atau awalan "KD" yang sering ditulis model.
     */
    private function normalisasiKodeKd(string $kode): string
    {
        $kode = trim((string) preg_replace('/\s+/u', ' ', $kode));
        $kode = (string) preg_replace('/^kd[\s.:\-_]*/iu', '', $kode);

        return mb_strtoupper(trim($kode));
    }

    /**
     * Menautkan tiap soal draft ke Kompetensi Dasar berdasarkan kode yang
     * dikembalikan AI, plus daftar soal yang tetap tidak cocok.
     *
     * Pencocokan memakai kode ternormalisasi supaya beda format tulisan model
     * tidak menjatuhkan soal ke placeholder "— pilih KD —". Kode yang memang
     * tidak dikenal sengaja **tidak ditebak**: KD salah pilih membuat statistik
     * per KD dan latihan lanjutannya jadi salah sasaran. Soal dikembalikan ke
     * admin beserta penandanya supaya keputusannya terlihat, bukan diam-diam
     * memblokir tombol simpan.
     *
     * @param  array<array-key, array<string, mixed>>  $daftarSoal
     * @param  Collection<int, KompetensiDasar>  $kompetensiDasars
     * @return array{0: array<array-key, array<string, mixed>>, 1: list<array-key>}
     */
    private function cocokkanKdSoal(array $daftarSoal, Collection $kompetensiDasars): array
    {
        $idPerKode = [];

        foreach ($kompetensiDasars as $kd) {
            $idPerKode[$this->normalisasiKodeKd($kd->kode_kompetensi)] = $kd->getKey();
        }

        $belumCocok = [];

        foreach ($daftarSoal as $index => $soal) {
            if ((int) ($soal['kompetensi_dasar_id'] ?? 0) > 0) {
                continue;
            }

            $kode = trim((string) ($soal['kompetensi_dasar_kode'] ?? ''));
            $id = $kode === '' ? 0 : ($idPerKode[$this->normalisasiKodeKd($kode)] ?? 0);

            $daftarSoal[$index]['kompetensi_dasar_id'] = $id;

            if ($id === 0) {
                $belumCocok[] = $index;
            }
        }

        return [$daftarSoal, $belumCocok];
    }

    /**
     * Jumlah soal yang benar-benar terkumpul per kode KD. Dihitung dari isi
     * draft, bukan dari jumlah yang diminta ke AI, karena yield per part bisa
     * kurang dari yang diminta.
     *
     * @param  array<int, array<string, mixed>>  $daftarSoal
     * @return array<string, int>
     */
    private function kdTerpakai(array $daftarSoal): array
    {
        $terpakai = [];

        foreach ($daftarSoal as $soal) {
            $kode = $this->normalisasiKodeKd((string) ($soal['kompetensi_dasar_kode'] ?? ''));

            if ($kode !== '') {
                $terpakai[$kode] = ($terpakai[$kode] ?? 0) + 1;
            }
        }

        return $terpakai;
    }

    /**
     * Subset KD yang mendapat kuota pada part ini, urutannya tetap mengikuti
     * `$kds` supaya prompt menyusun daftar KD dengan cara yang sama.
     *
     * @param  Collection<int, KompetensiDasar>  $kds
     * @param  array<int, string>  $kodePart  kode KD persis seperti di database
     * @return list<array{kode: string, deskripsi: string, materi_pokok: string|null}>
     */
    private function kdsUntukPart(Collection $kds, array $kodePart): array
    {
        return $kds
            ->filter(fn (KompetensiDasar $kd): bool => in_array($kd->kode_kompetensi, $kodePart, true))
            ->map(fn (KompetensiDasar $kd): array => [
                'kode' => $kd->kode_kompetensi,
                'deskripsi' => $kd->deskripsi,
                'materi_pokok' => $kd->materi_pokok,
            ])
            ->values()
            ->all();
    }

    /**
     * Membaca sesi ai_parts tanpa efek samping, agar guard nomor part bisa
     * dihitung sebelum sesi diinisialisasi ulang.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|null
     */
    private function sesiPartsCocok(Mapel $mapel, array $validated, int $target): ?array
    {
        $parts = session('ai_parts');

        if (! is_array($parts)) {
            return null;
        }

        if ((int) ($parts['mapel_id'] ?? 0) !== $mapel->getKey()
            || (int) ($parts['target'] ?? 0) !== $target
            || ($parts['run'] ?? null) !== $validated['run']) {
            return null;
        }

        return $parts;
    }

    /**
     * Perkiraan total part: sisa soal dibagi SOAL_PER_PART, ditambah part yang
     * sudah selesai. Dihitung ulang tiap respons karena yield per part bisa
     * kurang dari SOAL_PER_PART (AI kurang atau soal dibuang validator).
     */
    private function perkiraanPartTotal(int $partsDone, int $akumulasi, int $target): int
    {
        return $partsDone + (int) ceil(max(0, $target - $akumulasi) / self::SOAL_PER_PART);
    }

    private function responsPart(
        int $part,
        int $partsDone,
        int $akumulasi,
        int $target,
        int $jumlahPart = 0,
        int $jumlahDibuang = 0,
    ): JsonResponse {
        return response()->json([
            'ok' => true,
            'part' => $part,
            'part_total' => $this->perkiraanPartTotal($partsDone, $akumulasi, $target),
            'jumlah_part' => $jumlahPart,
            'jumlah_dibuang' => $jumlahDibuang,
            'jumlah_akumulasi' => $akumulasi,
            'selesai' => $akumulasi >= $target,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function daftarPartsSesi(Mapel $mapel, Collection $kds, array $validated, int $target, int $partTotal): array
    {
        $parts = $this->sesiPartsCocok($mapel, $validated, $target);

        if ($parts !== null) {
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
     * ID sementara dari AI selalu mulai dari S001 di tiap part, sehingga
     * menyebabkan duplikat saat part digabung. Enumerasi ulang berdasarkan
     * posisi (idempoten, list hanya bertambah) agar ID unik di seluruh draft.
     *
     * @param  array<int, array<string, mixed>>  $daftarSoal
     * @return array<int, array<string, mixed>>
     */
    private function renumberIdSoal(array $daftarSoal): array
    {
        foreach ($daftarSoal as $index => $soal) {
            $daftarSoal[$index]['id_soal_sementara'] = sprintf('S%03d', $index + 1);
        }

        return $daftarSoal;
    }

    /**
     * Pesan buangan dari tiap part memakai ID yang sama (S001 dst), jadi perlu
     * diberi nomor part agar ambigu saat ditampilkan di halaman kurasi.
     *
     * @param  array<int, array{id: string, alasan: string}>  $soalDibuang
     * @return array<int, array{id: string, alasan: string}>
     */
    private function prefixIdDibuang(array $soalDibuang, int $part): array
    {
        return array_map(fn (array $dibuang): array => [
            'id' => 'Part '.$part.' · '.($dibuang['id'] ?? ''),
            'alasan' => $dibuang['alasan'] ?? '',
        ], $soalDibuang);
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
