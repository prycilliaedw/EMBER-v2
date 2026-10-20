<div class="mb-6 border border-slate-200 bg-white p-5 shadow-sm">
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.8fr)] lg:items-end">
        <div>
            <p class="text-sm font-bold text-slate-900">Import titik lokasi dari CSV</p>
            <p class="mt-1 text-sm leading-6 text-slate-600">
                Unduh template, isi data tanpa mengubah header, lalu unggah kembali. Latitude dan longitude wajib diisi; kolom lainnya boleh kosong.
            </p>
            <p class="mt-2 font-mono text-xs text-slate-500">provinsi, kabupaten_kota, kecamatan, desa, latitude, longitude, date, confidence</p>
            <p class="mt-1 text-xs text-slate-500">Tanggal menerima HH/BB/TT (01/05/26) dan format ekspor B/H/TTTT (1/16/2021). Format koordinat spreadsheet Indonesia seperti 976.189 atau 1.010.151 akan dinormalisasi otomatis. Baris tanpa koordinat akan dilewati. Maksimal 5 MB atau 10.000 baris.</p>
        </div>

        <form wire:submit="importCsv" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="csv_file" class="block text-xs font-bold uppercase tracking-wider text-slate-500">File CSV</label>
                <input id="csv_file" wire:model="csvFile" type="file" accept=".csv,text/csv" required class="mt-2 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-slate-700">
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="csvFile,importCsv" class="shrink-0 border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-700 disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="importCsv">Import CSV</span>
                <span wire:loading wire:target="importCsv">Mengimpor...</span>
            </button>
        </form>
    </div>

    @error('csvFile')
        <p class="mt-4 border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $message }}</p>
    @enderror

    @if ($importErrors !== [])
        <div class="mt-3 border border-red-200 bg-red-50 px-4 py-3">
            <p class="text-sm font-bold text-red-800">Periksa data berikut:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
                @foreach ($importErrors as $importError)
                    <li>{{ $importError }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
