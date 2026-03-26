@props(['provinsis', 'kabupatens', 'kecamatans', 'desas'])

<div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-2">
    <select wire:model.live="selectedProvinsi" class="w-full rounded border dark:border-zinc-700 p-2 text-sm bg-gray-50 dark:bg-zinc-800/50 focus:bg-white dark:focus:bg-zinc-700 dark:text-zinc-300 transition-colors">
        <option value="">-- {{ __('Provinsi') }} --</option>
        @foreach ($provinsis as $p)
            <option value="{{ $p->kode }}">{{ $p->nama }}</option>
        @endforeach
    </select>

    <select wire:model.live="selectedKabupaten" class="w-full rounded border dark:border-zinc-700 p-2 text-sm bg-gray-50 dark:bg-zinc-800/50 focus:bg-white dark:focus:bg-zinc-700 dark:text-zinc-300 transition-colors" {{ empty($kabupatens) ? 'disabled' : '' }}>
        <option value="">-- {{ __('Kabupaten/Kota') }} --</option>
        @foreach ($kabupatens as $k)
            <option value="{{ $k->kode }}">{{ $k->nama }}</option>
        @endforeach
    </select>

    <select wire:model.live="selectedKecamatan" class="w-full rounded border dark:border-zinc-700 p-2 text-sm bg-gray-50 dark:bg-zinc-800/50 focus:bg-white dark:focus:bg-zinc-700 dark:text-zinc-300 transition-colors" {{ empty($kecamatans) ? 'disabled' : '' }}>
        <option value="">-- {{ __('Kecamatan') }} --</option>
        @foreach ($kecamatans as $k)
            <option value="{{ $k->kode }}">{{ $k->nama }}</option>
        @endforeach
    </select>

    <select wire:model.live="selectedDesa" class="w-full rounded border dark:border-zinc-700 p-2 text-sm bg-gray-50 dark:bg-zinc-800/50 focus:bg-white dark:focus:bg-zinc-700 dark:text-zinc-300 transition-colors" {{ empty($desas) ? 'disabled' : '' }}>
        <option value="">-- {{ __('Desa/Kelurahan') }} --</option>
        @foreach ($desas as $d)
            <option value="{{ $d->kode }}">{{ $d->nama }}</option>
        @endforeach
    </select>
</div>
