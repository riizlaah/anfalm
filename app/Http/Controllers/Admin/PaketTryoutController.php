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
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaketTryoutController extends Controller
{
    private const SLOT_MAPEL = [
        'mapel_wajib_1',
        'mapel_wajib_2',
        'mapel_wajib_3',
        'mapel_pilihan_1',
        'mapel_pilihan_2',
    ];

    private const SLOT_PAKET = [
        'paket_soal_wajib_1_id',
        'paket_soal_wajib_2_id',
        'paket_soal_wajib_3_id',
        'paket_soal_pilihan_1_id',
        'paket_soal_pilihan_2_id',
    ];

    public function index(): View
    {
        $paketTryouts = PaketTryout::with(['wajib1', 'wajib2', 'wajib3', 'pilihan1', 'pilihan2'])
            ->orderByDesc('id')
            ->get();

        return view('admin.paket-tryout.index', compact('paketTryouts'));
    }

    public function create(): View
    {
        $mapels = Mapel::orderBy('kode')->get();
        $paketSoals = PaketSoal::with('mapel')->orderBy('nama_paket')->get();

        return view('admin.paket-tryout.create', compact('mapels', 'paketSoals'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        PaketTryout::create([
            ...$data,
            'batas_waktu_menit' => $data['batas_waktu_menit'] ?? 120,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.paket-tryout.index')
            ->with('success', 'Paket tryout berhasil ditambahkan.');
    }

    public function edit(PaketTryout $paketTryout): View
    {
        $mapels = Mapel::orderBy('kode')->get();
        $paketSoals = PaketSoal::with('mapel')->orderBy('nama_paket')->get();

        return view('admin.paket-tryout.edit', compact('paketTryout', 'mapels', 'paketSoals'));
    }

    public function update(Request $request, PaketTryout $paketTryout): RedirectResponse
    {
        $data = $this->validateData($request);

        $paketTryout->update([
            ...$data,
            'batas_waktu_menit' => $data['batas_waktu_menit'] ?? 120,
        ]);

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
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        $rules = [
            'nama_paket' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'tingkat' => ['required', Rule::in([
                PaketTryout::TINGKAT_SD,
                PaketTryout::TINGKAT_SMP,
                PaketTryout::TINGKAT_SMA,
                PaketTryout::TINGKAT_SMK,
            ])],
            'batas_waktu_menit' => ['nullable', 'integer', 'min:1'],
        ];

        foreach (self::SLOT_MAPEL as $field) {
            $rules[$field] = ['required', 'integer', Rule::exists('mapel', 'id')->whereNull('deleted_at')];
        }
        foreach (self::SLOT_PAKET as $field) {
            $rules[$field] = ['required', 'integer', Rule::exists('paket_soal', 'id')->whereNull('deleted_at')];
        }

        $tingkat = $request->filled('tingkat') ? (string) $request->input('tingkat') : null;

        $validator = ValidatorFacade::make($request->all(), $rules);

        $validator->after(function (Validator $validator) use ($request, $tingkat) {
            $this->validateSlots($validator, $request, $tingkat);
        });

        return $validator->validate();
    }

    private function validateSlots(Validator $validator, Request $request, ?string $tingkat): void
    {
        $mapelIds = [];
        foreach (self::SLOT_MAPEL as $field) {
            $mapelId = (int) $request->input($field);
            if ($mapelId === 0) {
                continue;
            }
            if (isset($mapelIds[$mapelId])) {
                $validator->errors()->add($field, 'Mapel wajib dan pilihan tidak boleh sama satu sama lain.');
            }
            $mapelIds[$mapelId] = true;
        }

        $this->validateAturanSmk($validator, $request, $tingkat);

        foreach (self::SLOT_PAKET as $index => $paketField) {
            $mapelField = self::SLOT_MAPEL[$index];
            $mapelId = (int) $request->input($mapelField);
            $paketId = (int) $request->input($paketField);

            if ($mapelId === 0 || $paketId === 0) {
                continue;
            }

            $this->validateTingkatMapel($validator, $mapelField, $mapelId, $tingkat);

            if ($this->paketTidakCocokSlot($paketId, $mapelId) || $this->paketTanpaSoal($paketId)) {
                $validator->errors()->add($paketField, 'Paket soal harus milik mapel slot dan minimal berisi 1 soal.');
            }
        }
    }

    private function validateAturanSmk(Validator $validator, Request $request, ?string $tingkat): void
    {
        if ($tingkat !== PaketTryout::TINGKAT_SMK) {
            return;
        }

        $pilihan1 = Mapel::find((int) $request->input('mapel_pilihan_1'));
        $pilihan2 = Mapel::find((int) $request->input('mapel_pilihan_2'));

        $adaKejuruan = $this->apakahKejuruan($pilihan1) || $this->apakahKejuruan($pilihan2);

        if (! $adaKejuruan) {
            $validator->errors()->add(
                'mapel_pilihan_1',
                'Untuk tingkat SMK, minimal satu mapel pilihan harus berjenis pilihan_kejuruan atau berstatus PKK.'
            );
        }
    }

    private function validateTingkatMapel(Validator $validator, string $field, int $mapelId, ?string $tingkat): void
    {
        if ($tingkat === null) {
            return;
        }

        $mapel = Mapel::find($mapelId);

        if ($mapel && ! $this->tingkatMapelCocok($mapel->tingkat, $tingkat)) {
            $validator->errors()->add($field, 'Mapel tingkat harus sesuai dengan tingkat tryout.');
        }
    }

    private function tingkatMapelCocok(string $mapelTingkat, string $tingkat): bool
    {
        if ($mapelTingkat === $tingkat || $mapelTingkat === Mapel::TINGKAT_ALL) {
            return true;
        }

        return $tingkat === PaketTryout::TINGKAT_SMK && $mapelTingkat === PaketTryout::TINGKAT_SMA;
    }

    private function paketTidakCocokSlot(int $paketId, int $mapelId): bool
    {
        $paket = PaketSoal::find($paketId);

        return $paket === null || $paket->mapel_id !== $mapelId;
    }

    private function paketTanpaSoal(int $paketId): bool
    {
        return PaketSoal::find($paketId)?->soal()->count() === 0;
    }

    private function apakahKejuruan(?Mapel $mapel): bool
    {
        return $mapel !== null
            && ($mapel->jenis === Mapel::JENIS_PILIHAN_KEJURUAN || $mapel->is_pkk);
    }
}
