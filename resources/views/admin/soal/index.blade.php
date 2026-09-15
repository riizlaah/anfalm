<x-layouts.app title="Manajemen Soal">
    <h1 class="page-title">Manajemen Soal</h1>

    <x-alert />

    <div class="mt-5 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('admin.soal.index') }}" class="flex flex-wrap items-end gap-3">
            <x-select label="Kompetensi Dasar" name="kompetensi_dasar_id" :value="request('kompetensi_dasar_id')"
                empty-option="Semua"
                :options="$kompetensiDasars->mapWithKeys(fn ($kd) => [$kd->id => $kd->mapel->kode.' · '.$kd->kode_kompetensi])"
                onchange="this.form.submit()" />

            <x-select label="Tipe Soal" name="tipe_soal" :value="request('tipe_soal')" empty-option="Semua"
                :options="['pg' => 'Pilihan Ganda', 'pg_kompleks' => 'PG Kompleks', 'pg_kategori' => 'PG Kategori']"
                onchange="this.form.submit()" />
        </form>

        <a href="{{ route('admin.soal.create') }}" class="btn btn-primary">+ Tambah Soal</a>
    </div>

    <div class="card mt-5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs font-semibold text-slate-500">
                    <th class="px-4 py-3">ID</th>
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
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $soal->id }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $soal->kompetensiDasar->kode_kompetensi }}</td>
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
                                <a href="{{ route('admin.soal.edit', $soal) }}" class="btn btn-ghost px-2.5 py-1 text-xs">Edit</a>
                                <form method="POST" action="{{ route('admin.soal.destroy', $soal) }}"
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
                        <td colspan="6" class="px-4 py-12 text-center text-slate-400">Belum ada soal.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>