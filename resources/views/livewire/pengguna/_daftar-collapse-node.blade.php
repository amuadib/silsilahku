@php
    $marriageCount = count($node['marriages'] ?? []);
@endphp

@if ($marriageCount === 0)
    <div class="mb-2 flex items-start">
        <div class="w-5 h-5 mr-2 shrink-0"></div> <!-- Placeholder for align if no children -->
        <span class="font-semibold text-indigo-500 dark:text-indigo-400 mr-2 shrink-0">{{ $prefix }}.</span>
        <span class="text-[15px] font-medium leading-relaxed">
            <a href="{{ route('pengguna.show', $node['id']) }}" wire:navigate
                class="text-zinc-800 hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                {{ $node['name'] }}
            </a>
            @if (!empty($node['wafat']))
                <span class="ml-0.5 text-xs font-normal text-red-500">(Alm)</span>
            @endif
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
            $hasChildren = !empty($marriage['children']);
        @endphp

        <div x-data="{ expanded: false }" 
             @expand-all.window="expanded = true" 
             @collapse-all.window="expanded = false" 
             class="mb-1">
            <div class="flex items-start">
                @if ($hasChildren)
                    <button @click="expanded = !expanded" class="mr-2 shrink-0 text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors focus:outline-none flex items-center justify-center w-5 h-5 mt-0.5 rounded focus:ring-2 focus:ring-indigo-500">
                        <!-- Right Chevron (Collapsed) -->
                        <svg x-show="!expanded" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        <!-- Down Chevron (Expanded) -->
                        <svg x-show="expanded" style="display: none;" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @else
                    <div class="w-5 h-5 mr-2 shrink-0"></div> <!-- Placeholder for alignment -->
                @endif
                
                <span class="font-semibold text-indigo-500 dark:text-indigo-400 mr-2 shrink-0">{{ $marriagePrefix }}.</span>
                <span class="text-[15px] font-medium leading-relaxed">
                    <a href="{{ route('pengguna.show', $node['id']) }}" wire:navigate
                        class="text-zinc-800 hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                        {{ $node['name'] }}
                    </a>
                    @if (!empty($node['wafat']))
                        <span class="ml-0.5 text-xs font-normal text-red-500">(Alm)</span>
                    @endif

                    @if (!empty($marriage['spouse']))
                        <span class="mx-0.5 font-normal text-zinc-500"> &amp; </span>
                        <a href="{{ route('pengguna.show', $marriage['spouse']['id']) }}" wire:navigate
                            class="text-zinc-800 hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                            {{ $marriage['spouse']['name'] }}
                        </a>
                        @if (!empty($marriage['spouse']['wafat']))
                            <span class="ml-0.5 text-xs font-normal text-red-500">(Alm)</span>
                        @endif
                    @endif
                </span>
            </div>

            @if ($hasChildren)
                <div x-show="expanded" style="display: none;"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="pl-6 sm:pl-8 border-l border-dashed border-zinc-200 dark:border-zinc-700 ml-[9px] mt-1 mb-2 py-1">
                    @foreach ($marriage['children'] as $cIndex => $child)
                        @include('livewire.pengguna._daftar-collapse-node', [
                            'node' => $child,
                            'prefix' => $marriagePrefix . '.' . ($cIndex + 1)
                        ])
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
@endif
