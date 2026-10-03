<x-layouts.app title="Profil">
    <h1 class="page-title">Profil</h1>

    <p class="mt-1.5 text-slate-600">
        Atur identitas kamu dan pilih mapel yang jadi fokus belajarmu.
    </p>

    <x-alert />

    <form method="POST" action="{{ route('profil.update') }}" class="mt-5 space-y-5">
        @csrf
        @method('PUT')

        <section class="card p-6">
            <h2 class="card-title">Identitas</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-input label="Nama lengkap" name="nama_lengkap" required
                    :value="$peserta->nama_lengkap" />

                <div>
                    <span class="label">Email</span>
                    <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                        {{ $peserta->email }}
                    </p>
                    <p class="hint">Email tidak bisa diubah sendiri — hubungi admin bila perlu diganti.</p>
                </div>

                <x-input label="Sekolah" name="sekolah" :value="$peserta->sekolah" />

                <x-select label="Tingkat" name="tingkat" empty-option="Pilih tingkat…"
                    :value="$peserta->tingkat"
                    :options="collect($tingkatOpsi)->mapWithKeys(fn (string $tingkat): array => [$tingkat => $tingkat])" />

                <x-input label="Jurusan" name="jurusan" :value="$peserta->jurusan" />
            </div>
        </section>

        <section class="card p-6">
            <h2 class="card-title">Mapel pilihan</h2>

            <p class="mt-1 max-w-2xl text-sm text-slate-600">
                Mapel yang dicentang menjadi fokusmu: hanya mapel itulah yang
                ditawarkan di halaman Analisis dan pada form Latihan. Tryout dan
                seluruh catatan nilai tetap mencakup semua mapel.
            </p>

            @php($terpilihSekarang = array_map('intval', old('mapel_pilihan', $terpilih->all())))

            <div class="mt-4 grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($mapels as $mapel)
                    <label class="check">
                        <input type="checkbox" name="mapel_pilihan[]" value="{{ $mapel->getKey() }}"
                            class="h-4 w-4 rounded border-slate-300 accent-ink"
                            @checked(in_array($mapel->getKey(), $terpilihSekarang, true))>
                        <span>{{ $mapel->nama }}</span>
                    </label>
                @empty
                    <p class="text-sm text-slate-400">Belum ada mapel yang tersedia.</p>
                @endforelse
            </div>

            <p class="hint">
                Belum memilih satu pun berarti seluruh mapel tetap ditampilkan.
            </p>
        </section>

        <x-errors />

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Simpan Profil</button>
        </div>
    </form>
</x-layouts.app>
