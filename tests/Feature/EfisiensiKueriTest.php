<?php

use App\Models\HasilTryout;
use App\Models\KompetensiDasar;
use App\Models\Mapel;
use App\Models\PaketTryout;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Jumlah query yang dijalankan selama satu render halaman.
 *
 * Inilah alat deteksi N+1: saat baris yang ditampilkan berlipat ganda, jumlah
 * query tidak boleh ikut bertambah — kalau bertambah, ada relasi yang diambil
 * per baris.
 */
function jumlahKueriHalaman(Closure $render): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $render();
    } finally {
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    return $jumlah;
}

it('daftar soal admin tidak menambah query seiring banyaknya soal', function () {
    $admin = User::factory()->admin()->create();
    $mapel = Mapel::factory()->create();
    $kd = KompetensiDasar::factory()->create(['mapel_id' => $mapel->getKey()]);

    Soal::factory()->count(3)->create(['kompetensi_dasar_id' => $kd->getKey()]);

    $render = fn (): mixed => $this->actingAs($admin)
        ->get(route('admin.mapel.soal.index', $mapel))
        ->assertOk();

    // Render pertama sekedar memanaskan cache bawaan aplikasi.
    $render();

    $awal = jumlahKueriHalaman($render);

    Soal::factory()->count(6)->create(['kompetensi_dasar_id' => $kd->getKey()]);

    expect(jumlahKueriHalaman($render))->toBe($awal);
});

it('halaman analisis tidak menambah query seiring banyaknya kompetensi dasar', function () {
    $peserta = User::factory()->peserta()->create();
    $mapel = Mapel::factory()->create();

    KompetensiDasar::factory()->count(3)->create(['mapel_id' => $mapel->getKey()]);

    $render = fn (): mixed => $this->actingAs($peserta)
        ->get(route('analisis.index', ['mapel_id' => $mapel->getKey()]))
        ->assertOk();

    $render();

    $awal = jumlahKueriHalaman($render);

    KompetensiDasar::factory()->count(6)->create(['mapel_id' => $mapel->getKey()]);

    expect(jumlahKueriHalaman($render))->toBe($awal);
});

it('leaderboard tidak menambah query seiring banyaknya peserta', function () {
    $peserta = User::factory()->peserta()->create();
    $paketTryout = PaketTryout::factory()->create();

    HasilTryout::factory()->count(3)->create(['paket_tryout_id' => $paketTryout->getKey()]);

    $render = fn (): mixed => $this->actingAs($peserta)
        ->get(route('tryout.leaderboard', $paketTryout))
        ->assertOk();

    $render();

    $awal = jumlahKueriHalaman($render);

    HasilTryout::factory()->count(6)->create(['paket_tryout_id' => $paketTryout->getKey()]);

    expect(jumlahKueriHalaman($render))->toBe($awal);
});
