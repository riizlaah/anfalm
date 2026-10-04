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
                Dua mapel pilihan menjadi fokusmu: merekalah yang ditawarkan di
                halaman Analisis dan pada form Latihan, di samping seluruh mapel
                wajib. Tryout dan seluruh catatan nilai tetap mencakup semua mapel.
            </p>

            <h3 class="label mt-5">Mapel wajib</h3>

            <p class="mt-1 max-w-2xl text-sm text-slate-600">
                Tidak perlu dipilih — seluruh mapel ini selalu ditampilkan
                karena dipakai semua peserta.
            </p>

            <ul class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-700">
                @foreach ($wajib as $mapel)
                    <li>{{ $mapel->nama }}</li>
                @endforeach
            </ul>

            <h3 class="label mt-5">Pilih maksimal {{ $maksPilihan }} mapel</h3>

            @php($terpilihSekarang = array_map('intval', old('mapel_pilihan', $terpilih->all())))

            <div class="mt-2 grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($pilihan as $mapel)
                    <label class="check">
                        <input type="checkbox" name="mapel_pilihan[]" value="{{ $mapel->getKey() }}"
                            class="peer h-4 w-4 rounded border-slate-300 accent-ink"
                            @checked(in_array($mapel->getKey(), $terpilihSekarang, true))>
                        <span class="peer-disabled:text-slate-400">{{ $mapel->nama }}</span>
                    </label>
                @empty
                    <p class="text-sm text-slate-400">Belum ada mapel pilihan yang tersedia.</p>
                @endforelse
            </div>

            <p class="hint">
                Belum memilih satu pun berarti seluruh mapel tetap ditampilkan.
                Setelah {{ $maksPilihan }} kotak tercentang, kotak sisanya nonaktif
                sampai salah satu dilepas.
            </p>

            {{--
                Pembatasan jumlah dipakai di dua lapis. Di sini ia membuat
                pilihan yang tidak bisa disimpan justru tidak bisa dibentuk —
                peserta tidak perlu menekan Simpan untuk mengetahui batasnya.
                Server tetap memeriksa hal yang sama lewat `max:{{ $maksPilihan }}`
                karena keadaan klien bisa dilewati, dan keduanya membaca angka
                yang sama dari `$maksPilihan`. Vanilla JS disengaja: satu
                pengamatan berderet tidak membenarkan membawa Alpine ke halaman
                ini.
            --}}
            <script>
                (function () {
                    var maks = {{ $maksPilihan }};
                    var kotak = document.querySelectorAll('input[name="mapel_pilihan[]"]');

                    function batasi() {
                        var tercentang = 0;
                        kotak.forEach(function (k) { if (k.checked) { tercentang++; } });
                        // Yang tercentang tidak pernah dinonaktifkan — tanpa itu
                        // peserta tidak bisa melepas pilihannya lagi begitu batas
                        // tercapai, karena sudah tidak ada kotak yang bisa ditekan.
                        kotak.forEach(function (k) {
                            k.disabled = !k.checked && tercentang >= maks;
                        });
                    }

                    kotak.forEach(function (k) { k.addEventListener('change', batasi); });
                    batasi();
                })();
            </script>
        </section>

        {{-- Dibatasi kunci form ini saja; galat form kata sandi di bawah
             ditampilkan di tempatnya masing-masing, bukan dua kali. --}}
        <x-errors :only="['nama_lengkap', 'sekolah', 'tingkat', 'jurusan', 'mapel_pilihan']" />

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Simpan Profil</button>
        </div>
    </form>

    {{--
        Form kedua, terpisah dari "Simpan Profil": tidak ada satu pun alasan
        untuk memperbarui profil sambil mengganti kata sandi, dan menggabungkan
        keduanya berarti menekan satu tombol bisa berbuat dua hal yang akibatnya
        berbeda. Sengaja setelah form utama — ganti kata sandi adalah tindakan
        yang jarang dan menyebabkan perangkat lain keluar, jadi ia tidak boleh
        berdiri di depan pilihan mapel yang justru dikerjakan setiap hari.
    --}}
    <form method="POST" action="{{ route('profil.password') }}" class="mt-5 space-y-5">
        @csrf
        @method('PUT')

        <section class="card p-6">
            <h2 class="card-title">Kata sandi</h2>

            <p class="mt-1.5 text-sm text-slate-600">
                Mengganti kata sandi mengeluarkan seluruh perangkat lain dari
                akunmu. Perangkat yang sedang kamu pakai tetap masuk.
            </p>

            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                <x-input label="Kata sandi lama" name="password_lama" type="password" required />

                <div class="space-y-5">
                    <x-input label="Kata sandi baru (minimal 8 karakter)" name="password" type="password" required />
                    <x-input label="Ulangi kata sandi baru" name="password_confirmation" type="password" required />
                </div>
            </div>

            <x-errors :only="['password_lama', 'password', 'password_confirmation']" />

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">Ubah Kata Sandi</button>
            </div>
        </section>
    </form>
</x-layouts.app>
