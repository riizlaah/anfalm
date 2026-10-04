<x-layouts.app :title="'Kelola KD · '.$mapel->nama">
    @php
        $openDialog = session('open_dialog');
        $formInput = session('form_input', []);
        $levelOpsi = \App\Models\KompetensiDasar::LEVEL_KOGNITIF;

        // Isian gagal validasi hanya dipakai oleh dialog yang sedang dibuka kembali,
        // sehingga dialog lain tetap memuat data barisnya masing-masing.
        $isi = fn (string $dialogId, string $field, $default = null) => $openDialog === $dialogId
            ? ($formInput[$field] ?? null)
            : $default;
    @endphp

    <a href="{{ route('admin.mapel.index') }}" class="link text-sm">&larr; Kembali ke Manajemen Mapel</a>

    <h1 class="page-title mt-2">Manajemen Kompetensi Dasar</h1>
    <p class="mt-1 text-sm text-slate-600">
        Mapel <span class="font-semibold text-ink">{{ $mapel->nama }}</span> ({{ $mapel->kode }})
    </p>

    <x-alert />

    <div class="mt-5 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('admin.mapel.kompetensi-dasar.index', $mapel) }}" class="flex flex-wrap items-end gap-3">
            <x-select label="Level Kognitif" name="level_kognitif" :value="request('level_kognitif')" empty-option="Semua"
                :options="$levelOpsi" onchange="this.form.submit()" />
        </form>

        <button type="button" class="btn btn-primary" data-dialog-open="create-kd">+ Tambah KD</button>
    </div>

    {{-- "Hapus Semua" dihapus bersama ketiga halaman admin; lihat catatan di admin/mapel/index. --}}
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <form method="POST" action="{{ route('admin.mapel.kompetensi-dasar.bulk-delete', $mapel) }}" id="bulk-delete-kd"
            onsubmit="return confirm('Hapus kompetensi dasar yang dipilih?')">
            @csrf
            <button type="submit" class="btn btn-danger px-2.5 py-1 text-xs" id="bulk-delete-btn" disabled>Hapus Terpilih</button>
        </form>
    </div>

    <div class="card mt-5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs font-semibold text-slate-500">
                    <th class="w-10 px-4 py-3">
                        <input type="checkbox" class="rounded border-slate-300 accent-ink" id="pilih-semua-kd"
                            onchange="toggleSemuaKd(this)">
                    </th>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Deskripsi</th>
                    <th class="px-4 py-3">Level</th>
                    <th class="px-4 py-3">Batasan</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($kompetensiDasars as $kd)
                    <tr>
                        <td class="px-4 py-3">
                            <input type="checkbox" name="ids[]" form="bulk-delete-kd" value="{{ $kd->id }}"
                                class="row-check-kd rounded border-slate-300 accent-ink" onchange="updateBulkKd()">
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $kd->kode_kompetensi }}</td>
                        <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit($kd->deskripsi, 60) }}</td>
                        <td class="px-4 py-3">{{ $kd->level_kognitif_label }}</td>
                        <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit($kd->batasan, 30) ?: '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn btn-ghost px-2.5 py-1 text-xs"
                                    data-dialog-open="edit-kd-{{ $kd->id }}">Edit</button>
                                <form method="POST" action="{{ route('admin.mapel.kompetensi-dasar.destroy', [$mapel, $kd]) }}"
                                    onsubmit="return confirm('Hapus KD ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger px-2.5 py-1 text-xs">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-slate-400">Belum ada kompetensi dasar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-dialog id="create-kd" title="Tambah Kompetensi Dasar">
        <form method="POST" action="{{ route('admin.mapel.kompetensi-dasar.store', $mapel) }}" class="space-y-4">
            @csrf

            @if ($openDialog === 'create-kd')
                <x-errors />
            @endif

            <x-input label="Kode Kompetensi" name="kode_kompetensi" :value="$isi('create-kd', 'kode_kompetensi')"
                required maxlength="50" />

            <x-textarea label="Deskripsi" name="deskripsi" :value="$isi('create-kd', 'deskripsi')" required rows="3" />

            <x-input label="Materi Pokok" name="materi_pokok" :value="$isi('create-kd', 'materi_pokok')" maxlength="255" />

            <x-select label="Level Kognitif" name="level_kognitif" :value="$isi('create-kd', 'level_kognitif')" required
                :options="$levelOpsi" />

            <x-textarea label="Batasan (opsional)" name="batasan" :value="$isi('create-kd', 'batasan')" rows="2" />

            <div class="flex items-center gap-3 pt-1">
                <x-button>Simpan</x-button>
                <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
            </div>
        </form>
    </x-dialog>

    @foreach ($kompetensiDasars as $kd)
        @php($dialogId = 'edit-kd-'.$kd->id)

        <x-dialog :id="$dialogId" :title="'Edit KD · '.$kd->kode_kompetensi">
            <form method="POST" action="{{ route('admin.mapel.kompetensi-dasar.update', [$mapel, $kd]) }}" class="space-y-4">
                @csrf
                @method('PUT')

                @if ($openDialog === $dialogId)
                    <x-errors />
                @endif

                <x-input label="Kode Kompetensi" name="kode_kompetensi"
                    :value="$isi($dialogId, 'kode_kompetensi', $kd->kode_kompetensi)" required maxlength="50" />

                <x-textarea label="Deskripsi" name="deskripsi" :value="$isi($dialogId, 'deskripsi', $kd->deskripsi)"
                    required rows="3" />

                <x-input label="Materi Pokok" name="materi_pokok"
                    :value="$isi($dialogId, 'materi_pokok', $kd->materi_pokok)" maxlength="255" />

                <x-select label="Level Kognitif" name="level_kognitif"
                    :value="$isi($dialogId, 'level_kognitif', $kd->level_kognitif)" required
                    :options="$levelOpsi" />

                <x-textarea label="Batasan (opsional)" name="batasan" :value="$isi($dialogId, 'batasan', $kd->batasan)"
                    rows="2" />

                <div class="flex items-center gap-3 pt-1">
                    <x-button>Simpan</x-button>
                    <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
                </div>
            </form>
        </x-dialog>
    @endforeach

    <script>
        function toggleSemuaKd(cb) {
            document.querySelectorAll('.row-check-kd').forEach(c => c.checked = cb.checked);
            updateBulkKd();
        }

        function updateBulkKd() {
            const dipilih = document.querySelectorAll('.row-check-kd:checked').length > 0;
            document.getElementById('bulk-delete-btn').disabled = !dipilih;
        }

        @if ($openDialog)
            document.getElementById(@json($openDialog))?.showModal();
        @endif
    </script>
</x-layouts.app>
