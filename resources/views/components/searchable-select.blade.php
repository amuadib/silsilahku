@props([
    'placeholder' => __('Select...'),
])

@php
    // Build options array from slot — kita ambil dari $slot children via options prop
    // Karena kita perlu data JS, options harus dipass sebagai prop terpisah
    // Komponen ini tetap mendukung pola lama via <select> untuk backward compat,
    // tapi styling-nya kini menggunakan custom Alpine dropdown.
@endphp

<div
    wire:ignore
    class="relative w-full"
    {{ $attributes->whereStartsWith('wire:key') }}
    x-data="{
        open: false,
        search: '',
        selectedLabel: '',
        selectedValue: '',

        init() {
            // Sync initial value from the hidden select
            const sel = this.$refs.hiddenSelect;
            if (sel && sel.value) {
                this.selectedValue = sel.value;
                const opt = [...sel.options].find(o => o.value == sel.value);
                if (opt) this.selectedLabel = opt.text;
            }
        },

        select(value, label) {
            this.selectedValue = value;
            this.selectedLabel = label;
            this.open = false;
            this.search = '';

            // Sync to hidden select and fire events for Livewire
            const sel = this.$refs.hiddenSelect;
            if (sel) {
                sel.value = value;
                sel.dispatchEvent(new Event('input', { bubbles: true }));
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        clear() {
            this.selectedValue = '';
            this.selectedLabel = '';
            this.search = '';
            const sel = this.$refs.hiddenSelect;
            if (sel) {
                sel.value = '';
                sel.dispatchEvent(new Event('input', { bubbles: true }));
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        get filteredOptions() {
            const opts = [...(this.$refs.hiddenSelect?.options || [])].filter(o => o.value !== '');
            if (!this.search) return opts;
            const q = this.search.toLowerCase();
            return opts.filter(o => o.text.toLowerCase().includes(q));
        }
    }"
    @click.outside="open = false"
>
    {{-- Hidden native select untuk Livewire wire:model --}}
    <select
        x-ref="hiddenSelect"
        {{ $attributes->merge(['class' => 'sr-only']) }}
        @change="
            selectedValue = $event.target.value;
            const opt = [...$event.target.options].find(o => o.value == $event.target.value);
            selectedLabel = opt ? opt.text : '';
        "
    >
        {{ $slot }}
    </select>

    {{-- Trigger / Input --}}
    <div class="relative flex items-center">
        {{-- Search icon --}}
        <div class="pointer-events-none absolute left-3 flex h-5 w-5 items-center">
            <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>

        <input
            type="text"
            x-model="search"
            @focus="open = true"
            @input="open = true"
            :placeholder="selectedLabel || '{{ $placeholder }}'"
            class="w-full rounded-lg border border-zinc-300 bg-white py-2.5 pl-9 pr-9 text-sm text-zinc-900 placeholder-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-400 dark:focus:border-blue-400"
        >

        {{-- Clear button --}}
        <button
            x-show="selectedValue"
            @click.stop="clear()"
            type="button"
            class="absolute right-3 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Dropdown --}}
    <div
        x-show="open"
        x-transition
        class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
    >
        <template x-for="opt in filteredOptions" :key="opt.value">
            <div
                @click="select(opt.value, opt.text)"
                class="flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-blue-50 dark:hover:bg-zinc-700"
                :class="{ 'bg-blue-50 dark:bg-zinc-700': selectedValue == opt.value }"
            >
                {{-- Location icon --}}
                <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-zinc-400/20">
                    <svg class="h-3 w-3 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <span class="text-sm text-zinc-800 dark:text-zinc-200" x-text="opt.text"></span>
            </div>
        </template>

        <div x-show="filteredOptions.length === 0" class="px-3 py-2 text-sm text-zinc-400">
            {{ __('No data found.') }}
        </div>
    </div>

    {{-- Selected badge --}}
    <div
        x-show="selectedValue && !open"
        class="mt-2 flex items-center gap-2 rounded-md bg-blue-50 px-3 py-1.5 dark:bg-blue-900/20"
    >
        <div class="h-2 w-2 flex-shrink-0 rounded-full bg-blue-500"></div>
        <span class="truncate text-xs font-medium text-blue-700 dark:text-blue-300" x-text="selectedLabel"></span>
    </div>
</div>
