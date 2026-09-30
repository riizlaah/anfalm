<x-layouts.app title="Manajemen Mapel">
    @php
        $openDialog = session('open_dialog');
        $formInput = session('form_input', []);

        // Isian gagal validasi hanya dipakai oleh dialog yang sedang dibuka kembali,
        // sehingga dialog lain tetap memuat data barisnya masing-masing.
        $isi = fn (string $dialogId, string $field, $default = null) => $openDialog === $dialogId
            ? ($formInput[$field] ?? null)
            : $default;
    @endphp

    <h1 class="page-title">Manajemen Mapel</h1>

    <x-alert />

    <div class="mt-5 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('admin.mapel.index') }}" class="flex flex-wrap items-end gap-3">
            <x-select label="Tingkat" name="tingkat" :value="request('tingkat')" empty-option="Semua"
                :options="['SD' => 'SD', 'SMP' => 'SMP', 'SMA' => 'SMA', 'SMK' => 'SMK', 'all' => 'Semua']"
                onchange="this.form.submit()" />

            <x-select label="Jenis" name="jenis" :value="request('jenis')" empty-option="Semua"
                :options="['wajib' => 'Wajib', 'pilihan_umum' => 'Pilihan Umum', 'pilihan_kejuruan' => 'Pilihan Kejuruan']"
                onchange="this.form.submit()" />
        </form>

        <button type="button" class="btn btn-primary" data-dialog-open="create-mapel">+ Tambah Mapel</button>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        <form method="POST" action="{{ route('admin.mapel.bulk-delete') }}" id="bulk-delete-mapel"
            onsubmit="return confirm('Hapus mapel yang dipilih?')">
            @csrf
            <button type="submit" class="btn btn-danger px-2.5 py-1 text-xs" id="bulk-delete-btn" disabled>Hapus Terpilih</button>
        </form>
        <form method="POST" action="{{ route('admin.mapel.bulk-delete') }}"
            onsubmit="return confirm('Hapus semua mapel yang tampil?')">
            @csrf
            <input type="hidden" name="all" value="1">
            @if (request('tingkat'))
                <input type="hidden" name="tingkat" value="{{ request('tingkat') }}">
            @endif
            @if (request('jenis'))
                <input type="hidden" name="jenis" value="{{ request('jenis') }}">
            @endif
            <button type="submit" class="btn btn-ghost px-2.5 py-1 text-xs">Hapus Semua</button>
        </form>
    </div>

    <div class="card mt-5 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs font-semibold text-slate-500">
                    <th class="w-10 px-4 py-3">
                        <input type="checkbox" class="rounded border-slate-300 accent-ink" id="pilih-semua-mapel"
                            onchange="toggleSemuaMapel(this)">
                    </th>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Tingkat</th>
                    <th class="px-4 py-3">Jenis</th>
                    <th class="px-4 py-3">PKK</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($mapels as $mapel)
                    <tr>
                        <td class="px-4 py-3">
                            <input type="checkbox" name="ids[]" form="bulk-delete-mapel" value="{{ $mapel->id }}"
                                class="row-check-mapel rounded border-slate-300 accent-ink" onchange="updateBulkMapel()">
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $mapel->kode }}</td>
                        <td class="px-4 py-3 font-medium text-ink">{{ $mapel->nama }}</td>
                        <td class="px-4 py-3">{{ $mapel->tingkat === 'all' ? 'Semua' : $mapel->tingkat }}</td>
                        <td class="px-4 py-3">{{ ucwords(str_replace('_', ' ', $mapel->jenis)) }}</td>
                        <td class="px-4 py-3">{{ $mapel->is_pkk ? 'Ya' : 'Tidak' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.mapel.kompetensi-dasar.index', $mapel) }}"
                                    class="btn btn-ghost px-2.5 py-1 text-xs">Kelola KD ({{ $mapel->jumlah_kd }})</a>
                                <a href="{{ route('admin.mapel.soal.index', $mapel) }}"
                                    class="btn btn-ghost px-2.5 py-1 text-xs">Kelola Soal ({{ $mapel->jumlah_soal }})</a>
                                <button type="button" class="btn btn-ghost px-2.5 py-1 text-xs"
                                    data-dialog-open="edit-mapel-{{ $mapel->id }}">Edit</button>
                                <form method="POST" action="{{ route('admin.mapel.destroy', $mapel) }}"
                                    onsubmit="return confirm('Hapus mapel ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger px-2.5 py-1 text-xs">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-slate-400">Belum ada mapel.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-dialog id="create-mapel" title="Tambah Mapel">
        <form method="POST" action="{{ route('admin.mapel.store') }}" class="space-y-4">
            @csrf

            @if ($openDialog === 'create-mapel')
                <x-errors />
            @endif

            <x-input label="Kode" name="kode" :value="$isi('create-mapel', 'kode')" required maxlength="20" />

            <x-input label="Nama" name="nama" :value="$isi('create-mapel', 'nama')" required maxlength="100" />

            <x-select label="Tingkat" name="tingkat" :value="$isi('create-mapel', 'tingkat')" required
                :options="['SD' => 'SD', 'SMP' => 'SMP', 'SMA' => 'SMA', 'SMK' => 'SMK', 'all' => 'Semua']" />

            <x-select label="Jenis" name="jenis" :value="$isi('create-mapel', 'jenis')" required
                :options="['wajib' => 'Wajib', 'pilihan_umum' => 'Pilihan Umum', 'pilihan_kejuruan' => 'Pilihan Kejuruan']" />

            <x-checkbox name="is_pkk" label="Proyek Kreatif & Kewirausahaan (PKK)"
                :checked="(bool) $isi('create-mapel', 'is_pkk')" />

            <div class="flex items-center gap-3 pt-1">
                <x-button>Simpan</x-button>
                <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
            </div>
        </form>
    </x-dialog>

    @foreach ($mapels as $mapel)
        @php($dialogId = 'edit-mapel-'.$mapel->id)

        <x-dialog :id="$dialogId" :title="'Edit · '.$mapel->nama">
            <form method="POST" action="{{ route('admin.mapel.update', $mapel) }}" class="space-y-4">
                @csrf
                @method('PUT')

                @if ($openDialog === $dialogId)
                    <x-errors />
                @endif

                <x-input label="Kode" name="kode" :value="$isi($dialogId, 'kode', $mapel->kode)" required maxlength="20" />

                <x-input label="Nama" name="nama" :value="$isi($dialogId, 'nama', $mapel->nama)" required maxlength="100" />

                <x-select label="Tingkat" name="tingkat" :value="$isi($dialogId, 'tingkat', $mapel->tingkat)" required
                    :options="['SD' => 'SD', 'SMP' => 'SMP', 'SMA' => 'SMA', 'SMK' => 'SMK', 'all' => 'Semua']" />

                <x-select label="Jenis" name="jenis" :value="$isi($dialogId, 'jenis', $mapel->jenis)" required
                    :options="['wajib' => 'Wajib', 'pilihan_umum' => 'Pilihan Umum', 'pilihan_kejuruan' => 'Pilihan Kejuruan']" />

                <x-checkbox name="is_pkk" label="Proyek Kreatif & Kewirausahaan (PKK)"
                    :checked="(bool) $isi($dialogId, 'is_pkk', $mapel->is_pkk)" />

                <div class="flex items-center gap-3 pt-1">
                    <x-button>Simpan</x-button>
                    <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
                </div>
            </form>
        </x-dialog>
    @endforeach

    <script>
        function toggleSemuaMapel(cb) {
            document.querySelectorAll('.row-check-mapel').forEach(c => c.checked = cb.checked);
            updateBulkMapel();
        }

        function updateBulkMapel() {
            const dipilih = document.querySelectorAll('.row-check-mapel:checked').length > 0;
            document.getElementById('bulk-delete-btn').disabled = !dipilih;
        }

        @if ($openDialog)
            document.getElementById(@json($openDialog))?.showModal();
        @endif
    </script>
</x-layouts.app>
