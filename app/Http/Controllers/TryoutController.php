<?php

namespace App\Http\Controllers;

use App\Domain\Percobaan\PercobaanService;
use App\Models\HasilTryout;
use App\Models\Mapel;
use App\Models\PaketTryout;
use App\Models\Percobaan;
use App\Models\RiwayatPengerjaan;
use App\Models\Soal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TryoutController extends Controller
{
    public const PESAN_SUDAH_SELESAI = 'Anda sudah menyelesaikan tryout ini. Silakan hubungi admin jika ada masalah teknis.';

    public function __construct(private readonly PercobaanService $percobaan) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $paketTryouts = PaketTryout::query()
            ->with(['wajib1', 'wajib2', 'wajib3', 'pilihan1', 'pilihan2'])
            ->orderBy('nama_paket')
            ->get();

        $berjalan = Percobaan::query()
            ->where('user_id', $user->getKey())
            ->where('status', Percobaan::STATUS_BERJALAN)
            ->pluck('paket_tryout_id')
            ->flip();

        $sudahDinilai = HasilTryout::query()
            ->where('user_id', $user->getKey())
            ->pluck('paket_tryout_id')
            ->flip();

        return view('tryout.index', compact('paketTryouts', 'berjalan', 'sudahDinilai'));
    }

    public function mulai(Request $request, PaketTryout $paketTryout): RedirectResponse
    {
        if ($this->percobaan->mulai($paketTryout, $request->user()) === null) {
            return redirect()->route('tryout.index')->with('error', self::PESAN_SUDAH_SELESAI);
        }

        return redirect()->route('tryout.kerja', $paketTryout);
    }

    /**
     * Mengosongkan jawaban percobaan yang masih berjalan lalu mengulangnya
     * dari mapel pertama (6.4). Tetap ditolak bila paket sudah dinilai.
     */
    public function ulang(Request $request, PaketTryout $paketTryout): RedirectResponse
    {
        if ($this->percobaan->mulai($paketTryout, $request->user(), true) === null) {
            return redirect()->route('tryout.index')->with('error', self::PESAN_SUDAH_SELESAI);
        }

        return redirect()->route('tryout.kerja', $paketTryout);
    }

    public function kerja(Request $request, PaketTryout $paketTryout): View|RedirectResponse
    {
        $percobaan = $this->percobaan->cariAktif($paketTryout, $request->user());

        if ($percobaan === null) {
            return $this->alihkanSetelahTidakAktif($request, $paketTryout);
        }

        // Waktu habis: tutup dulu sebelum halaman sempat menampilkan soal lagi.
        if ($this->percobaan->kadaluarsa($percobaan)) {
            $this->percobaan->akhirkan($percobaan);

            return redirect()->route('tryout.hasil', $paketTryout);
        }

        $grup = $this->percobaan->grupAktif($percobaan);

        if ($grup === null) {
            return redirect()->route('tryout.index');
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
        $posisiMapel = ((int) $percobaan->urutan_mapel) + 1;
        $totalMapel = count($percobaan->daftar_soal ?? []);
        $terakhirMapel = $posisiMapel === $totalMapel;

        return view('percobaan.kerja', [
            'title' => $paketTryout->nama_paket,
            'judul' => $mapel->nama,
            'subjudul' => "{$paketTryout->nama_paket} · Mapel {$posisiMapel} dari {$totalMapel}",
            'action' => route('tryout.jawab', $paketTryout),
            'aksiDefault' => 'lanjut',
            'labelKirim' => $terakhirMapel ? 'Selesai & Lihat Hasil' : 'Lanjut ke Mapel Berikutnya',
            'konfirmasi' => [
                'judul' => $terakhirMapel ? 'Selesaikan mapel terakhir?' : 'Pindah ke mapel berikutnya?',
                'isi' => $terakhirMapel
                    ? 'Semua jawaban tryout akan disimpan dan hasilnya langsung dihitung.'
                    : "Jawaban di mapel {$mapel->nama} akan disimpan dan mapel ini dikunci — peserta tidak bisa kembali lagi. Pastikan semua soal sudah dijawab.",
                'tombol' => $terakhirMapel ? 'Ya, selesaikan' : 'Ya, lanjut',
            ],
            'simpan' => null,
            'percobaan' => $percobaan,
            'soals' => $soals,
            'jawabanTersimpan' => $jawabanTersimpan,
        ]);
    }

    /**
     * Menyimpan jawaban mapel yang sedang dikerjakan lalu maju ke mapel
     * berikutnya. Pada mapel terakhir, atau bila peserta meminta selesai
     * (auto-submit saat waktu habis), percobaan ditutup dan hasil dihitung.
     */
    public function jawab(Request $request, PaketTryout $paketTryout): RedirectResponse
    {
        $request->validate([
            'aksi' => ['sometimes', 'string', Rule::in(['lanjut', 'selesai'])],
        ]);

        $percobaan = $this->percobaan->cariAktif($paketTryout, $request->user());

        if ($percobaan === null) {
            return $this->alihkanSetelahTidakAktif($request, $paketTryout);
        }

        $this->percobaan->simpanJawaban($percobaan, (array) $request->input('jawaban', []));

        // Jawaban yang masuk tetap tersimpan walau terlambat, lalu percobaan
        // ditutup; penilaian memakai jawaban yang sudah ada saja.
        $adaMapelBerikut = ! $this->percobaan->kadaluarsa($percobaan)
            && $request->input('aksi', 'lanjut') === 'lanjut'
            && $this->percobaan->lanjut($percobaan);

        if ($adaMapelBerikut) {
            return redirect()->route('tryout.kerja', $paketTryout);
        }

        $this->percobaan->akhirkan($percobaan);

        return redirect()->route('tryout.hasil', $paketTryout);
    }

    public function hasil(Request $request, PaketTryout $paketTryout): View|RedirectResponse
    {
        $user = $request->user();

        $hasil = HasilTryout::query()
            ->where('user_id', $user->getKey())
            ->where('paket_tryout_id', $paketTryout->getKey())
            ->first();

        if ($hasil === null) {
            return redirect()->route('tryout.index');
        }

        // Percobaan terakhir jadi sumber soal yang dikerjakan peserta pada
        // paket ini; jumlah soal paket sendiri tetap dihitung dari paketnya.
        $percobaan = Percobaan::query()
            ->where('user_id', $user->getKey())
            ->where('paket_tryout_id', $paketTryout->getKey())
            ->latest('id')
            ->first();

        $perKd = $percobaan !== null
            ? $this->percobaan->ringkasanKompetensi($percobaan)
            : [];

        $jumlahSoal = (int) DB::table('detail_paket_soal')
            ->whereIn('paket_soal_id', [
                $paketTryout->paket_soal_wajib_1_id,
                $paketTryout->paket_soal_wajib_2_id,
                $paketTryout->paket_soal_wajib_3_id,
                $paketTryout->paket_soal_pilihan_1_id,
                $paketTryout->paket_soal_pilihan_2_id,
            ])
            ->count();

        return view('tryout.hasil', compact('paketTryout', 'hasil', 'jumlahSoal', 'perKd'));
    }

    /**
     * Peserta sudah tidak punya percobaan berjalan di paket ini: tampilkan
     * hasil bila sudah dinilai, kalau tidak kembali ke daftar tryout.
     */
    private function alihkanSetelahTidakAktif(Request $request, PaketTryout $paketTryout): RedirectResponse
    {
        $sudahDinilai = HasilTryout::query()
            ->where('user_id', $request->user()->getKey())
            ->where('paket_tryout_id', $paketTryout->getKey())
            ->exists();

        return $sudahDinilai
            ? redirect()->route('tryout.hasil', $paketTryout)
            : redirect()->route('tryout.index');
    }
}
