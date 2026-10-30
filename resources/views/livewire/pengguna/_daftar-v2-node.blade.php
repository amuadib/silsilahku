{{-- =====================================================================
     _daftar-v2-node.blade.php
     Node partial untuk halaman daftar keluarga versi 2 (redesign).
     Dipanggil rekursif dari pengguna/daftar-v2.blade.php
     ===================================================================== --}}
@php
    $marriageCount = count($node['marriages'] ?? []);
    $genderColor = $node['jenis_kelamin'] == '1'
        ? 'text-blue-600 dark:text-blue-400'
        : 'text-pink-600 dark:text-pink-400';
    $genderBg = $node['jenis_kelamin'] == '1'
        ? 'bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800'
        : 'bg-pink-50 dark:bg-pink-900/30 border-pink-200 dark:border-pink-800';
    $genderDot = $node['jenis_kelamin'] == '1'
        ? 'bg-blue-400 dark:bg-blue-500'
        : 'bg-pink-400 dark:bg-pink-500';
@endphp

@if ($marriageCount === 0)
    {{-- Anggota tanpa pernikahan --}}
    <div class="group mb-1.5 flex items-center gap-2 rounded-lg px-2 py-1 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
        <span class="min-w-[2.5rem] shrink-0 text-right text-[11px] font-bold tracking-wide text-indigo-400 dark:text-indigo-500 print:text-black">
            {{ $prefix }}.
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full {{ $genderDot }}"></span>
            <a href="{{ route('pengguna.show', $node['id']) }}" wire:navigate
               class="text-[14px] font-medium text-zinc-800 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                {{ $node['name'] }}
            </a>
            @if (!empty($node['wafat']))
                <span class="rounded bg-red-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400 print:hidden">
                    Alm
                </span>
            @endif
            @auth
                @can('update', App\Models\User::find($node['id']))
                    <flux:button icon="plus" size="xs" variant="ghost"
                        class="ml-0.5 cursor-pointer opacity-0 transition-opacity group-hover:opacity-100 print:hidden"
                        wire:click="openAddHubunganModal('{{ $node['id'] }}')"
                        title="{{ __('Add Relationship') }}" />
                @endcan
            @endauth
        </span>
    </div>
@else
    @foreach ($node['marriages'] as $mIndex => $marriage)
        @php
            $marriagePrefix = $prefix;
            if ($marriageCount > 1) {
                $marriagePrefix = $prefix . '.' . chr(65 + $mIndex);
            }
            $spouseGenderDot = !empty($marriage['spouse'])
                ? ($marriage['spouse']['jenis_kelamin'] == '1' ? 'bg-blue-400 dark:bg-blue-500' : 'bg-pink-400 dark:bg-pink-500')
                : '';
        @endphp

        {{-- Baris pasangan --}}
        <div class="group mb-1.5 flex items-center gap-2 rounded-lg px-2 py-1 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50 print:hover:bg-transparent">
            <span class="min-w-[2.5rem] shrink-0 text-right text-[11px] font-bold tracking-wide text-indigo-400 dark:text-indigo-500 print:text-black">
                {{ $marriagePrefix }}.
            </span>
            <span class="flex flex-wrap items-center gap-1.5">
                {{-- Anggota utama --}}
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full {{ $genderDot }}"></span>
                    <a href="{{ route('pengguna.show', $node['id']) }}" wire:navigate
                       class="text-[14px] font-medium text-zinc-800 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                        {{ $node['name'] }}
                    </a>
                    @if (!empty($node['wafat']))
                        <span class="rounded bg-red-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400 print:hidden">
                            Alm
                        </span>
                    @endif
                    @auth
                        @can('update', App\Models\User::find($node['id']))
                            <flux:button icon="plus" size="xs" variant="ghost"
                                class="ml-0.5 cursor-pointer opacity-0 transition-opacity group-hover:opacity-100 print:hidden"
                                wire:click="openAddHubunganModal('{{ $node['id'] }}')"
                                title="{{ __('Add Relationship') }}" />
                        @endcan
                    @endauth
                </span>

                @if (!empty($marriage['spouse']))
                    <span class="font-normal text-zinc-400 dark:text-zinc-500">&amp;</span>
                    {{-- Pasangan --}}
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full {{ $spouseGenderDot }}"></span>
                        <a href="{{ route('pengguna.show', $marriage['spouse']['id']) }}" wire:navigate
                           class="text-[14px] font-medium text-zinc-800 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                            {{ $marriage['spouse']['name'] }}
                        </a>
                        @if (!empty($marriage['spouse']['wafat']))
                            <span class="rounded bg-red-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400 print:hidden">
                                Alm
                            </span>
                        @endif
                        @auth
                            @can('update', App\Models\User::find($marriage['spouse']['id']))
                                <flux:button icon="plus" size="xs" variant="ghost"
                                    class="cursor-pointer opacity-0 transition-opacity group-hover:opacity-100 print:hidden"
                                    wire:click="openAddHubunganModal('{{ $marriage['spouse']['id'] }}')"
                                    title="{{ __('Add Relationship') }}" />
                            @endcan
                        @endauth
                    </span>
                @endif

                @if (!empty($marriage['children']))
                    <span class="ml-1 rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400 print:hidden">
                        {{ count($marriage['children']) }} anak
                    </span>
                @endif
            </span>
        </div>

        @if (!empty($marriage['children']))
            <div class="my-1 ml-[3.25rem] border-l-2 border-dashed border-zinc-200 pl-4 dark:border-zinc-700 print:border-zinc-300">
                @foreach ($marriage['children'] as $cIndex => $child)
                    @include('livewire.pengguna._daftar-v2-node', [
                        'node'   => $child,
                        'prefix' => $marriagePrefix . '.' . ($cIndex + 1),
                    ])
                @endforeach
            </div>
        @endif
    @endforeach
@endif

