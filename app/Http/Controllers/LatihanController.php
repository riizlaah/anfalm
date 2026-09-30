<?php

namespace App\Http\Controllers;

use App\Domain\Percobaan\PercobaanService;
use App\Models\Mapel;
use App\Models\Percobaan;
use App\Models\RiwayatPengerjaan;
use App\Models\Soal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Mode latihan peserta (3.8): pilih mapel, jumlah soal, filter KD, lalu timer
 * stopwatch atau hitung mundur.
 *
 * Latihan tidak punya paket dan tidak menghasilkan `hasil_tryout`. Penutupannya
 * tercatat di `percobaan` + `riwayat_pengerjaan`, dan perubahannya ditulis ke
 * `tracking_kompetensi` per KD — tampilan analisisnya sendiri milik Fase 7.
 */
class LatihanController extends Controller
{
    public const TIMER_STOPWATCH = 'stopwatch';

    public const TIMER_COUNTDOWN = 'countdown';

    /** Opsi jumlah soal yang ditawarkan; nilai lain di luar rentang tetap sah. */
    public const JUMLAH_PRESET = [5, 10, 15, 20, 30];

    public function __construct(private readonly PercobaanService $percobaan) {}

    public function index(Request $request): View
    {
        $mapels = Mapel::query()
            ->whereNull('deleted_at')
            ->orderBy('kode')
            ->get();

        // Korelasi KD per mapel disertakan langsung supaya dropdown filter
        // bisa berganti tanpa endpoint tambahan.
        $kdPeta = $mapels->mapWithKeys(fn (Mapel $mapel): array => [
            $mapel->getKey() => $mapel->kompetensiDasars()
                ->orderBy('kode_kompetensi')
                ->get(['id', 'kode_kompetensi', 'deskripsi'])
                ->map(fn ($kd): array => [
                    'id' => $kd->id,
                    'label' => "{$kd->kode_kompetensi} — {$kd->deskripsi}",
                ])
                ->all(),
        ]);

        $berjalan = Percobaan::query()
            ->with('mapel')
            ->where('user_id', $request->user()->getKey())
            ->where('jenis', Percobaan::JENIS_LATIHAN)
            ->where('status', Percobaan::STATUS_BERJALAN)
            ->orderByDesc('id')
            ->get();

        return view('latihan.index', [
            'mapels' => $mapels,
            'kdPeta' => $kdPeta,
            'berjalan' => $berjalan,
            'jumlahPreset' => self::JUMLAH_PRESET,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function mulai(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mapel_id' => ['required', 'integer', Rule::exists('mapel', 'id')->whereNull('deleted_at')],
            'jumlah_soal' => ['required', 'integer', 'min:1', 'max:30'],
            'kompetensi_dasar_id' => [
                'nullable',
                'integer',
                Rule::exists('kompetensi_dasar', 'id')->where('mapel_id', $request->input('mapel_id')),
            ],
            'timer' => ['required', 'string', Rule::in([self::TIMER_STOPWATCH, self::TIMER_COUNTDOWN])],
            'batas_waktu_menit' => [
                'nullable',
                'integer',
                'min:1',
                'max:600',
                'required_if:timer,'.self::TIMER_COUNTDOWN,
            ],
        ], [
            'batas_waktu_menit.required_if' => 'Isi batas waktu (menit) untuk timer hitung mundur.',
        ]);

        $percobaan = $this->percobaan->mulaiLatihan($data, $request->user());

        if ($percobaan === null) {
            throw ValidationException::withMessages([
                'mapel_id' => 'Mapel ini belum punya soal untuk pilihan itu. Pilih mapel atau kompetensi dasar lain.',
            ]);
        }

        return redirect()->route('latihan.kerja', $percobaan);
    }

    public function kerja(Request $request, Percobaan $percobaan): View|RedirectResponse
    {
        $this->sahkanMilik($percobaan, $request);

        if ($percobaan->status === Percobaan::STATUS_SELESAI) {
            return redirect()->route('latihan.hasil', $percobaan);
        }

        // Waktu habis: tutup dulu sebelum halaman sempat menampilkan soal lagi.
        if ($this->percobaan->kadaluarsa($percobaan)) {
            $this->percobaan->akhirkan($percobaan);

            return redirect()->route('latihan.hasil', $percobaan);
        }

        $grup = $this->percobaan->grupAktif($percobaan);

        if ($grup === null) {
            return redirect()->route('latihan.index');
        }

        $soals = Soal::with(['opsiJawaban', 'pernyataanKategori'])
            ->whereIn('id', $grup['soal_ids'])
            ->get()
            ->sortBy(fn (Soal $soal): int => array_search($soal->id, $grup['soal_ids']))
            ->values();

        $jawabanTersimpan = RiwayatPengerjaan::query()
            ->where('percobaan_id', $percobaan->getKey())
            ->whereIn('soal_id', $grup['soal_ids'])
            ->get()
            ->keyBy('soal_id');

        $mapel = Mapel::find($grup['mapel_id']);

        return view('percobaan.kerja', [
            'title' => 'Latihan',
            'judul' => $mapel->nama,
            'subjudul' => "Latihan · {$mapel->nama} · {$soals->count()} soal",
            'action' => route('latihan.jawab', $percobaan),
            'aksiDefault' => 'selesai',
            'labelKirim' => 'Selesai & Lihat Hasil',
            'konfirmasi' => [
                'judul' => 'Selesaikan latihan ini?',
                'isi' => 'Seluruh jawaban akan disimpan dan hasil belajar per kompetensi dasar langsung diperbarui.',
                'tombol' => 'Ya, selesaikan',
            ],
            'simpan' => [
                'label' => 'Simpan & Lanjut Nanti',
                'aksi' => 'simpan',
            ],
            'percobaan' => $percobaan,
            'soals' => $soals,
            'jawabanTersimpan' => $jawabanTersimpan,
        ]);
    }

    /**
     * Menyimpan jawaban lalu menutup latihan. `aksi=simpan` menyimpan saja
     * tanpa menutup, supaya peserta bisa melanjutkan lain waktu.
     */
    public function jawab(Request $request, Percobaan $percobaan): RedirectResponse
    {
        $this->sahkanMilik($percobaan, $request);

        $request->validate([
            'aksi' => ['sometimes', 'string', Rule::in(['simpan', 'selesai'])],
        ]);

        if ($percobaan->status === Percobaan::STATUS_SELESAI) {
            return redirect()->route('latihan.hasil', $percobaan);
        }

        $this->percobaan->simpanJawaban($percobaan, (array) $request->input('jawaban', []));

        // Simpan boleh menahan penutupan, tapi tidak untuk percobaan yang sudah
        // lewat batas — jawaban yang telat tetap masuk lalu latihan ditutup,
        // sama seperti perilaku tryout.
        $akhiri = $request->input('aksi', 'selesai') !== 'simpan'
            || $this->percobaan->kadaluarsa($percobaan);

        if (! $akhiri) {
            return redirect()->route('latihan.index');
        }

        $this->percobaan->akhirkan($percobaan);

        return redirect()->route('latihan.hasil', $percobaan);
    }

    public function hasil(Request $request, Percobaan $percobaan): View|RedirectResponse
    {
        $this->sahkanMilik($percobaan, $request);

        if ($percobaan->status !== Percobaan::STATUS_SELESAI) {
            return redirect()->route('latihan.kerja', $percobaan);
        }

        $riwayat = RiwayatPengerjaan::query()
            ->with(['soal.opsiJawaban', 'soal.pernyataanKategori', 'soal.kompetensiDasar'])
            ->where('percobaan_id', $percobaan->getKey())
            ->get()
            ->sortBy(fn (RiwayatPengerjaan $baris): int => array_search(
                $baris->soal_id,
                $this->urutanSoal($percobaan)
            ))
            ->values();

        return view('latihan.hasil', [
            'percobaan' => $percobaan,
            'mapel' => Mapel::find($percobaan->mapel_id),
            'riwayat' => $riwayat,
            'perKd' => $this->percobaan->ringkasanKompetensi($percobaan),
            'jumlahBenar' => $riwayat->filter(fn (RiwayatPengerjaan $b) => $b->is_benar)->count(),
            'jumlahKosong' => $riwayat->filter(fn (RiwayatPengerjaan $b) => $b->skor_irt === null)->count(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function urutanSoal(Percobaan $percobaan): array
    {
        return collect($percobaan->daftar_soal ?? [])
            ->flatMap(fn (array $grup): array => $grup['soal_ids'])
            ->values()
            ->all();
    }

    /**
     * Percobaan hanya boleh dibuka oleh pesertanya sendiri, dan hanya yang
     * berjenis latihan — percobaan tryout punya pintu masuknya sendiri.
     */
    private function sahkanMilik(Percobaan $percobaan, Request $request): void
    {
        abort_unless($percobaan->jenis === Percobaan::JENIS_LATIHAN, 404);
        abort_unless($percobaan->user_id === $request->user()?->getKey(), 403);
    }
}
