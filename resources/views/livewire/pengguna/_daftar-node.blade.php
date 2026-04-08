@php
    $marriageCount = count($node['marriages'] ?? []);
@endphp

@if ($marriageCount === 0)
    <div class="mb-2 flex items-start">
        <span class="font-semibold text-indigo-500 dark:text-indigo-400 mr-2 shrink-0">{{ $prefix }}.</span>
        <span class="text-[15px] font-medium leading-relaxed">
            <a href="{{ route('pengguna.show', $node['id']) }}" wire:navigate
                class="text-zinc-800 hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                {{ $node['name'] }}
            </a>
            @if (!empty($node['wafat']))
                <span class="ml-0.5 text-xs font-normal text-red-500">(Alm)</span>
            @endif

            @auth
                @can('update', App\Models\User::find($node['id']))
                    <flux:button icon="plus" size="xs" variant="ghost" class="ml-1 cursor-pointer"
                        wire:click="openAddHubunganModal('{{ $node['id'] }}')" title="{{ __('Add Relationship') }}" />
                @endcan
            @endauth
        </span>
    </div>
@else
    @foreach ($node['marriages'] as $mIndex => $marriage)
        @php
            $marriagePrefix = $prefix;
            if ($marriageCount > 1) {
                // If the user has multiple marriages, append .A, .B, .C, etc.
                $marriagePrefix = $prefix . '.' . chr(65 + $mIndex);
            }
        @endphp

        <div class="mb-2 flex items-start">
            <span class="font-semibold text-indigo-500 dark:text-indigo-400 mr-2 shrink-0">{{ $marriagePrefix }}.</span>
            <span class="text-[15px] font-medium leading-relaxed">
                <a href="{{ route('pengguna.show', $node['id']) }}" wire:navigate
                    class="text-zinc-800 hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                    {{ $node['name'] }}
                </a>
                @if (!empty($node['wafat']))
                    <span class="ml-0.5 text-xs font-normal text-red-500">(Alm)</span>
                @endif
                @auth
                    @can('update', App\Models\User::find($node['id']))
                        <flux:button icon="plus" size="xs" variant="ghost" class="ml-1 cursor-pointer"
                            wire:click="openAddHubunganModal('{{ $node['id'] }}')" title="{{ __('Add Relationship') }}" />
                    @endcan
                @endauth

                @if (!empty($marriage['spouse']))
                    <span class="mx-0.5 font-normal text-zinc-500"> &amp; </span>
                    <a href="{{ route('pengguna.show', $marriage['spouse']['id']) }}" wire:navigate
                        class="text-zinc-800 hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                        {{ $marriage['spouse']['name'] }}
                    </a>
                    @if (!empty($marriage['spouse']['wafat']))
                        <span class="ml-0.5 text-xs font-normal text-red-500">(Alm)</span>
                    @endif

                    @auth
                        @can('update', App\Models\User::find($marriage['spouse']['id']))
                            <flux:button icon="plus" size="xs" variant="ghost" class="ml-1 cursor-pointer"
                                wire:click="openAddHubunganModal('{{ $marriage['spouse']['id'] }}')"
                                title="{{ __('Add Relationship') }}" />
                        @endcan
                    @endauth
                @endif
            </span>
        </div>

        @if (!empty($marriage['children']))
            <div class="pl-5 sm:pl-7 border-l border-dashed border-zinc-200 dark:border-zinc-700 ml-2.5 my-2 py-1">
                @foreach ($marriage['children'] as $cIndex => $child)
                    @include('livewire.pengguna._daftar-node', [
                        'node' => $child,
                        'prefix' => $marriagePrefix . '.' . ($cIndex + 1)
                    ])
                @endforeach
            </div>
        @endif
    @endforeach
@endif
