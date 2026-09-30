<x-layouts.app :title="'Kelola Soal · '.$mapel->nama">
    <a href="{{ route('admin.mapel.index') }}" class="link text-sm">&larr; Kembali ke Manajemen Mapel</a>

    <h1 class="page-title mt-2">Manajemen Soal</h1>
    <p class="mt-1 text-sm text-slate-600">
        Mapel <span class="font-semibold text-ink">{{ $mapel->nama }}</span> ({{ $mapel->kode }})
    </p>

    <x-alert />

    <div class="mt-5 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('admin.mapel.soal.index', $mapel) }}" class="flex flex-wrap items-end gap-3">
            <x-select label="Kompetensi Dasar" name="kompetensi_dasar_id" :value="request('kompetensi_dasar_id')"
                empty-option="Semua"
                :options="$kompetensiDasars->mapWithKeys(fn ($kd) => [$kd->id => $kd->kode_kompetensi])
                onchange="this.form.submit()" />

            <x-select label="Tipe Soal" name="tipe_soal" :value="request('tipe_soal')" empty-option="Semua"
                :options="['pg' => 'Pilihan Ganda', 'pg_kompleks' => 'PG Kompleks', 'pg_kategori' => 'PG Kategori']"
                onchange="this.form.submit()" />
        </form>

        <a href="{{ route('admin.mapel.soal.create', $mapel) }}" class="btn btn-primary">+ Tambah Soal</a>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        <form method="POST" action="{{ route('admin.mapel.soal.bulk-delete', $mapel) }}" id="bulk-delete-soal"
            onsubmit="return confirm('Hapus soal yang dipilih?')">
            @csrf
            <button type="submit" class="btn btn-danger px-2.5 py-1 text-xs" id="bulk-delete-btn" disabled>Hapus Terpilih</button>
        </form>
        <form method="POST" action="{{ route('admin.mapel.soal.bulk-delete', $mapel) }}"
            onsubmit="return confirm('Hapus semua soal yang tampil?')">
            @csrf
            <input type="hidden" name="all" value="1">
            @if (request('kompetensi_dasar_id'))
                <input type="hidden" name="kompetensi_dasar_id" value="{{ request('kompetensi_dasar_id') }}">
            @endif
            @if (request('tipe_soal'))
                <input type="hidden" name="tipe_soal" value="{{ request('tipe_soal') }}">
            @endif
            <button type="submit" class="btn btn-ghost px-2.5 py-1 text-xs">Hapus Semua</button>
        </form>
    </div>

    <p class="mt-3 text-sm text-slate-600">Menampilkan {{ $jumlahSoal }} soal.</p>

    <div class="card mt-5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs font-semibold text-slate-500">
                    <th class="w-10 px-4 py-3">
                        <input type="checkbox" class="rounded border-slate-300 accent-ink" id="pilih-semua-soal"
                            onchange="toggleSemuaSoal(this)">
                    </th>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">KD</th>
                    <th class="px-4 py-3">Pertanyaan</th>
                    <th class="px-4 py-3">Tipe</th>
                    <th class="px-4 py-3">Opsi</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($soals as $soal)
                    <tr>
                        <td class="px-4 py-3">
                            <input type="checkbox" name="ids[]" form="bulk-delete-soal" value="{{ $soal->id }}"
                                class="row-check-soal rounded border-slate-300 accent-ink" onchange="updateBulkSoal()">
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 font-mono text-xs">
                            @php($kd = $soal->kompetensiDasar)
                            <abbr class="cursor-help text-slate-600 underline decoration-dotted underline-offset-2"
                                title="{{ $kd->deskripsi }}{{ $kd->materi_pokok ? ' — Materi: '.$kd->materi_pokok : '' }}">
                                {{ $kd->kode_kompetensi }}
                            </abbr>
                        </td>
                        <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit(strip_tags($soal->pertanyaan), 50) }}</td>
                        <td class="px-4 py-3">
                            @switch($soal->tipe_soal)
                                @case('pg')
                                    Pilihan Ganda
                                    @break
                                @case('pg_kompleks')
                                    PG Kompleks
                                    @break
                                @case('pg_kategori')
                                    PG Kategori
                                    @break
                            @endswitch
                        </td>
                        <td class="px-4 py-3">{{ $soal->tipe_soal === 'pg_kategori' ? $soal->pernyataanKategori()->count().' pernyataan' : $soal->opsiJawaban()->count().' opsi' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.mapel.soal.edit', [$mapel, $soal]) }}" class="btn btn-ghost px-2.5 py-1 text-xs">Edit</a>
                                <form method="POST" action="{{ route('admin.mapel.soal.destroy', [$mapel, $soal]) }}"
                                    onsubmit="return confirm('Hapus soal ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger px-2.5 py-1 text-xs">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-slate-400">Belum ada soal.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        function toggleSemuaSoal(cb) {
            document.querySelectorAll('.row-check-soal').forEach(c => c.checked = cb.checked);
            updateBulkSoal();
        }

        function updateBulkSoal() {
            const dipilih = document.querySelectorAll('.row-check-soal:checked').length > 0;
            document.getElementById('bulk-delete-btn').disabled = !dipilih;
        }
    </script>
</x-layouts.app>