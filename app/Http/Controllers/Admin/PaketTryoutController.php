<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HasilTryout;
use App\Models\Mapel;
use App\Models\PaketSoal;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\RiwayatPengerjaan;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaketTryoutController extends Controller
{
    public function index(): View
    {
        $paketTryouts = PaketTryout::with('daftarMapel.mapel')
            ->orderByDesc('id')
            ->get();

        return view('admin.paket-tryout.index', compact('paketTryouts'));
    }

    public function create(): View
    {
        return view('admin.paket-tryout.create', $this->dataForm(
            (string) old('tingkat', PaketTryout::TINGKAT_SMK)
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $request): void {
            $paketTryout = PaketTryout::create([
                ...collect($data)->except(['paket_soal', 'menit'])->all(),
                'created_by' => $request->user()->id,
            ]);

            $paketTryout->susunIsiMapel($this->isiBaris($data));
        });

        return redirect()->route('admin.paket-tryout.index')
            ->with('success', 'Paket tryout berhasil ditambahkan.');
    }

    public function edit(PaketTryout $paketTryout): View
    {
        return view('admin.paket-tryout.edit', [
            ...$this->dataForm((string) $paketTryout->tingkat),
            'paketTryout' => $paketTryout,
        ]);
    }

    public function update(Request $request, PaketTryout $paketTryout): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $paketTryout): void {
            $paketTryout->update(collect($data)->except(['paket_soal', 'menit'])->all());

            $paketTryout->susunIsiMapel($this->isiBaris($data));
        });

        return redirect()->route('admin.paket-tryout.index')
            ->with('success', 'Paket tryout berhasil diperbarui.');
    }

    public function destroy(PaketTryout $paketTryout): RedirectResponse
    {
        $dipakai = Percobaan::query()->where('paket_tryout_id', $paketTryout->getKey())->exists()
            || HasilTryout::query()->where('paket_tryout_id', $paketTryout->getKey())->exists()
            || RiwayatPengerjaan::query()->where('paket_tryout_id', $paketTryout->getKey())->exists();

        if ($dipakai) {
            return redirect()->route('admin.paket-tryout.index')
                ->with('error', 'Paket tryout masih memiliki riwayat pengerjaan, tidak dapat dihapus.');
        }

        $paketTryout->delete();

        return redirect()->route('admin.paket-tryout.index')
            ->with('success', 'Paket tryout berhasil dihapus.');
    }

    /**
     * Isi form paket tryout: seluruh mapel yang ditawarkan pada tingkat ini,
     * beserta daftar paket soal miliknya masing-masing.
     *
     * Tiap baris memilih paket soalnya sendiri, jadi tidak ada lagi peta slot
     * yang harus disinkronkan lewat JavaScript — dan jumlah barisnya bebas
     * mengikuti katalog mapel.
     *
     * @return array{mapels: Collection<int, Mapel>, paketSoals: Collection<int, PaketSoal>}
     */
    private function dataForm(string $tingkat): array
    {
        return [
            'mapels' => $this->mapelsUntukForm($tingkat),
            'paketSoals' => $this->paketSoalsUntukForm(),
        ];
    }

    /**
     * Mapel yang ditawarkan pada form paket tryout. SD/SMP bukan lagi sasaran
     * aplikasi, dan tingkatnya harus cocok dengan paket tryout yang disunting —
     * supaya admin tidak pernah menempatkan mapel yang nanti ditolak validasi.
     *
     * @return Collection<int, Mapel>
     */
    private function mapelsUntukForm(string $tingkat): Collection
    {
        return Mapel::orderBy('kode')
            ->whereNotIn('tingkat', [Mapel::TINGKAT_SD, Mapel::TINGKAT_SMP])
            ->get()
            ->filter(fn (Mapel $mapel): bool => $this->tingkatMapelCocok($mapel->tingkat, $tingkat))
            ->values();
    }

    /**
     * Paket soal yang layak ditawarkan: minimal berisi satu soal, karena paket
     * tanpa soal akan selalu ditolak validasi dan barisnya hanya mengunci form.
     *
     * Diurut menurun supaya keputusan bawaan tiap baris jatuh ke paket yang
     * paling baru dibuat — aturan yang sama dengan sebelumnya.
     *
     * @return Collection<int, PaketSoal>
     */
    private function paketSoalsUntukForm(): Collection
    {
        return PaketSoal::with('mapel')
            ->whereHas('soal')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Menggabungkan dua peta form — paket soal dan batas waktu — menjadi satu
     * isian per baris mapel, sesuai kontrak `PaketTryout::susunIsiMapel()`.
     *
     * @param  array<string, mixed>  $data
     * @return array<int|string, array{paket_soal_id: mixed, menit: mixed}>
     */
    private function isiBaris(array $data): array
    {
        $menit = (array) ($data['menit'] ?? []);

        return collect($data['paket_soal'])
            ->map(fn ($paketSoalId, $mapelId): array => [
                'paket_soal_id' => $paketSoalId,
                'menit' => $menit[$mapelId] ?? null,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        $tingkat = $request->filled('tingkat') ? (string) $request->input('tingkat') : null;

        $rules = [
            'nama_paket' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'tingkat' => ['required', Rule::in([
                PaketTryout::TINGKAT_SD,
                PaketTryout::TINGKAT_SMP,
                PaketTryout::TINGKAT_SMA,
                PaketTryout::TINGKAT_SMK,
            ])],
            'menit' => ['required', 'array'],
            'menit.*' => ['required', 'integer', 'min:1', 'max:600'],
            'paket_soal' => ['required', 'array'],
        ];

        $validator = ValidatorFacade::make($request->all(), $rules);

        $validator->after(function (Validator $validator) use ($request, $tingkat) {
            $this->validateIsiMapel($validator, $request, $tingkat);
        });

        return $validator->validate();
    }

    /**
     * Satu aturan utama form: peta `paket_soal` berisi tepat seluruh mapel yang
     * ditawarkan, tiap barisnya menunjuk paket soal milik mapel itu sendiri.
     *
     * Mapel tanpa paket soal tidak ikut wajib diisi — barisnya tetap tampil
     * sebagai keterangan, tetapi tidak menyimpan apa pun sehingga form tidak
     * pernah terkunci hanya karena satu mapel belum punya paket.
     */
    private function validateIsiMapel(Validator $validator, Request $request, ?string $tingkat): void
    {
        $isian = $request->input('paket_soal');

        if (! is_array($isian) || $tingkat === null) {
            return;
        }

        // Kunci datang sebagai teks dari form; dinormalkan lebih dulu supaya
        // penelusuran tiap baris tidak terpecah oleh variasi penulisan angka.
        $terurut = [];
        foreach ($isian as $kunciBaris => $nilai) {
            $terurut[(int) $kunciBaris] = $nilai;
        }
        $isian = $terurut;

        $ditawarkan = $this->mapelsUntukForm($tingkat);
        $paketSoals = $this->paketSoalsUntukForm();
        $menit = (array) $request->input('menit');

        foreach ($ditawarkan as $mapel) {
            $kunci = 'paket_soal.'.$mapel->getKey();
            $nilai = $isian[$mapel->getKey()] ?? null;
            $kosong = ! is_scalar($nilai) || ! filled($nilai);
            $mapelPunyaPaket = $paketSoals->contains(
                fn (PaketSoal $paketSoal): bool => (int) $paketSoal->mapel_id === $mapel->getKey()
            );

            if (! $mapelPunyaPaket) {
                if (! $kosong) {
                    $validator->errors()->add($kunci, 'Mapel ini belum punya paket soal yang bisa dipakai.');
                }

                continue;
            }

            // Baris ini akan tersimpan, jadi batas waktunya wajib ikut terkirim —
            // aturan `menit.*` hanya menilai yang benar-benar hadir.
            if (! isset($menit[$mapel->getKey()])) {
                $validator->errors()->add(
                    'menit.'.$mapel->getKey(),
                    'Batas waktu mengerjakan mapel '.$mapel->nama.' wajib diisi.'
                );
            }

            if ($kosong) {
                $validator->errors()->add($kunci, 'Paket soal untuk mapel '.$mapel->nama.' wajib dipilih.');

                continue;
            }

            $paketTerpilih = $paketSoals->first(fn (PaketSoal $paketSoal): bool => $paketSoal->getKey() === (int) $nilai);

            if ($paketTerpilih === null || (int) $paketTerpilih->mapel_id !== $mapel->getKey()) {
                $validator->errors()->add($kunci, 'Paket soal harus milik mapel yang sama dan minimal berisi 1 soal.');
            }
        }

        foreach (array_keys($isian) as $kunciBaris) {
            $ditawarkanSaja = $ditawarkan->contains(fn (Mapel $mapel): bool => $mapel->getKey() === (int) $kunciBaris);

            if (! $ditawarkanSaja) {
                $validator->errors()->add(
                    'paket_soal.'.$kunciBaris,
                    'Mapel tersebut tidak tersedia pada paket tryout tingkat ini.'
                );
            }
        }

        $this->validateAturanSmk($validator, $ditawarkan, $tingkat);
    }

    /**
     * Seluruh mapel pilihan yang ikut tersimpan pada paket — nanti menjadi
     * bebas yang dipilih peserta — harus menyisakan minimal satu pilihan
     * kejuruan atau PKK untuk tingkat SMK.
     *
     * @param  Collection<int, Mapel>  $ditawarkan
     */
    private function validateAturanSmk(Validator $validator, Collection $ditawarkan, ?string $tingkat): void
    {
        if ($tingkat !== PaketTryout::TINGKAT_SMK) {
            return;
        }

        $adaKejuruan = $ditawarkan
            ->filter(fn (Mapel $mapel): bool => $mapel->jenis !== Mapel::JENIS_WAJIB)
            ->contains(fn (Mapel $mapel): bool => $this->apakahKejuruan($mapel));

        if (! $adaKejuruan) {
            $validator->errors()->add(
                'paket_soal',
                'Untuk tingkat SMK, minimal satu mapel pilihan harus berjenis pilihan_kejuruan atau berstatus PKK.'
            );
        }
    }

    private function tingkatMapelCocok(string $mapelTingkat, string $tingkat): bool
    {
        if ($mapelTingkat === $tingkat || $mapelTingkat === Mapel::TINGKAT_ALL) {
            return true;
        }

        return $tingkat === PaketTryout::TINGKAT_SMK && $mapelTingkat === PaketTryout::TINGKAT_SMA;
    }

    private function apakahKejuruan(Mapel $mapel): bool
    {
        return $mapel->jenis === Mapel::JENIS_PILIHAN_KEJURUAN || $mapel->is_pkk;
    }
}
