<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Services\FamilyRelationshipFinder;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public ?string $userId1 = null;
    public ?string $userId2 = null;
    public array $result = [];
    public array $chartData = [];
    public bool $found = false;
    public bool $searched = false;
    public string $errorMessage = '';

    public function mount(): void
    {
        // Pre-seed with current user if logged in
        $this->userId1 = Auth::id();
    }

    public function cari(): void
    {
        $this->result = [];
        $this->chartData = [];
        $this->found = false;
        $this->searched = false;
        $this->errorMessage = '';

        if (!$this->userId1 || !$this->userId2) {
            $this->errorMessage = __('Please select both persons.');
            return;
        }

        $user1 = User::find($this->userId1);
        $user2 = User::find($this->userId2);

        if (!$user1 || !$user2) {
            $this->errorMessage = __('One of the selected persons was not found.');
            return;
        }

        $this->searched = true;

        if ($user1->id === $user2->id) {
            $this->found = true;
            $this->result = [
                [
                    'user' => [
                        'id' => $user1->id,
                        'name' => $user1->name,
                        'wafat' => $user1->wafat,
                        'jenis_kelamin' => $user1->jenis_kelamin,
                        'foto' => $user1->foto,
                    ],
                    'relation' => null,
                    'relation_label' => null,
                ],
            ];
            $this->errorMessage = __('They are the same person.');
            return;
        }

        $finder = new FamilyRelationshipFinder();
        $path = $finder->find($user1, $user2);

        if ($path === null) {
            $this->found = false;
            $this->errorMessage = __('No relationship found within :hops hops.', ['hops' => FamilyRelationshipFinder::MAX_HOPS]);
            return;
        }

        $this->found = true;
        $this->result = $path;
        $this->chartData = [];

        // Dispatch path steps so JS can lay them out directionally
        $steps = array_map(
            fn($step) => [
                'user' => $step['user'],
                'relation' => $step['relation'],
                'relation_label' => $step['relation_label'],
            ],
            $path,
        );
        $this->dispatch('chart-ready', steps: $steps);
    }

    public function resetForm(): void
    {
        $this->userId1 = Auth::id();
        $this->userId2 = null;
        $this->result = [];
        $this->chartData = [];
        $this->found = false;
        $this->searched = false;
        $this->errorMessage = '';
    }

    public function with(): array
    {
        return [
            'allUsers' => User::orderBy('name')->get(['id', 'name', 'wafat', 'jenis_kelamin', 'foto']),
        ];
    }
};
?>

<section class="mx-auto w-full max-w-7xl px-6 lg:px-8">
    <div class="w-full">
        {{-- Header --}}
        <div class="relative my-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl" level="1">{{ __('Find Relationship') }}</flux:heading>
                <flux:subheading size="lg" class="mt-1">
                    {{ __('Find family relationship path between two people') }}</flux:subheading>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button color="zinc" href="{{ route('dashboard') }}" wire:navigate variant="outline">
                    {{ __('Dashboard') }}
                </flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />

        {{-- Search Form --}}
        <div
            class="mt-6 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div
                class="border-b border-zinc-100 bg-gradient-to-r from-blue-50 to-indigo-50 p-6 dark:border-zinc-800 dark:from-zinc-800/60 dark:to-zinc-800/40">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-600 shadow-sm">
                        <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Select Two People') }}</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('The system will find the shortest family path between them (max :hops hops)', ['hops' => \App\Services\FamilyRelationshipFinder::MAX_HOPS]) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {{-- Person 1 --}}
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                            <span
                                class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">1</span>
                            {{ __('Person 1') }}
                        </label>
                        <div class="relative" x-data="{
                            open: false,
                            search: '',
                            selected: null,
                            userId: @entangle('userId1'),
                            users: {{ Js::from($allUsers->map(fn($u) => ['id' => $u->id, 'name' => $u->name . ($u->wafat ? ' (Alm)' : ''), 'jenis_kelamin' => $u->jenis_kelamin])->values()) }},
                            get filtered() {
                                if (!this.search) return this.users;
                                const q = this.search.toLowerCase();
                                return this.users.filter(u => u.name.toLowerCase().includes(q));
                            },
                            init() {
                                if (this.userId) {
                                    this.selected = this.users.find(u => u.id === this.userId) || null;
                                    if (this.selected) this.search = this.selected.name;
                                }
                                this.$watch('userId', val => {
                                    this.selected = this.users.find(u => u.id === val) || null;
                                    if (this.selected) this.search = this.selected.name;
                                    else this.search = '';
                                });
                            },
                            select(u) {
                                this.selected = u;
                                this.userId = u.id;
                                this.search = u.name;
                                this.open = false;
                            },
                            clear() {
                                this.selected = null;
                                this.userId = null;
                                this.search = '';
                            }
                        }" @click.outside="open = false">
                            <div class="relative flex items-center">
                                <div class="pointer-events-none absolute left-3 flex h-5 w-5 items-center">
                                    <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input type="text" x-model="search" @focus="open = true" @input="open = true"
                                    placeholder="{{ __('Search Name') }}..."
                                    class="w-full rounded-lg border border-zinc-300 bg-white py-2.5 pl-9 pr-9 text-sm text-zinc-900 placeholder-zinc-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-blue-400">
                                <button x-show="selected" @click.stop="clear()" type="button"
                                    class="absolute right-3 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                            <div x-show="open && filtered.length > 0" x-transition
                                class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                <template x-for="u in filtered" :key="u.id">
                                    <div @click="select(u)"
                                        class="flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-blue-50 dark:hover:bg-zinc-700"
                                        :class="{ 'bg-blue-50 dark:bg-zinc-700': selected && selected.id === u.id }">
                                        <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                            :style="u.jenis_kelamin == '1' ? 'background-color:#7ea4b3' :
                                                'background-color:#ce969b'">
                                            <template x-if="u.jenis_kelamin == '1'">
                                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path
                                                        d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                                </svg>
                                            </template>
                                            <template x-if="u.jenis_kelamin != '1'">
                                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </template>
                                        </div>
                                        <span class="text-sm text-zinc-800 dark:text-zinc-200" x-text="u.name"></span>
                                    </div>
                                </template>
                                <div x-show="filtered.length === 0" class="px-3 py-2 text-sm text-zinc-400">
                                    {{ __('No data found.') }}</div>
                            </div>

                            {{-- Selected badge --}}
                            <div x-show="selected"
                                class="mt-2 flex items-center gap-2 rounded-md bg-blue-50 px-3 py-1.5 dark:bg-blue-900/20">
                                <div class="h-2 w-2 flex-shrink-0 rounded-full bg-blue-500"></div>
                                <span class="text-xs font-medium text-blue-700 dark:text-blue-300"
                                    x-text="selected?.name"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Person 2 --}}
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                            <span
                                class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">2</span>
                            {{ __('Person 2') }}
                        </label>
                        <div class="relative" x-data="{
                            open: false,
                            search: '',
                            selected: null,
                            userId: @entangle('userId2'),
                            users: {{ Js::from($allUsers->map(fn($u) => ['id' => $u->id, 'name' => $u->name . ($u->wafat ? ' (Alm)' : ''), 'jenis_kelamin' => $u->jenis_kelamin])->values()) }},
                            get filtered() {
                                if (!this.search) return this.users;
                                const q = this.search.toLowerCase();
                                return this.users.filter(u => u.name.toLowerCase().includes(q));
                            },
                            init() {
                                if (this.userId) {
                                    this.selected = this.users.find(u => u.id === this.userId) || null;
                                    if (this.selected) this.search = this.selected.name;
                                }
                                this.$watch('userId', val => {
                                    this.selected = this.users.find(u => u.id === val) || null;
                                    if (this.selected) this.search = this.selected.name;
                                    else this.search = '';
                                });
                            },
                            select(u) {
                                this.selected = u;
                                this.userId = u.id;
                                this.search = u.name;
                                this.open = false;
                            },
                            clear() {
                                this.selected = null;
                                this.userId = null;
                                this.search = '';
                            }
                        }" @click.outside="open = false">
                            <div class="relative flex items-center">
                                <div class="pointer-events-none absolute left-3 flex h-5 w-5 items-center">
                                    <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input type="text" x-model="search" @focus="open = true" @input="open = true"
                                    placeholder="{{ __('Search Name') }}..."
                                    class="w-full rounded-lg border border-zinc-300 bg-white py-2.5 pl-9 pr-9 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-indigo-400">
                                <button x-show="selected" @click.stop="clear()" type="button"
                                    class="absolute right-3 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                            <div x-show="open && filtered.length > 0" x-transition
                                class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                <template x-for="u in filtered" :key="u.id">
                                    <div @click="select(u)"
                                        class="flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-indigo-50 dark:hover:bg-zinc-700"
                                        :class="{ 'bg-indigo-50 dark:bg-zinc-700': selected && selected.id === u.id }">
                                        <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                            :style="u.jenis_kelamin == '1' ? 'background-color:#7ea4b3' :
                                                'background-color:#ce969b'">
                                            <template x-if="u.jenis_kelamin == '1'">
                                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path
                                                        d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                                </svg>
                                            </template>
                                            <template x-if="u.jenis_kelamin != '1'">
                                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </template>
                                        </div>
                                        <span class="text-sm text-zinc-800 dark:text-zinc-200" x-text="u.name"></span>
                                    </div>
                                </template>
                                <div x-show="filtered.length === 0" class="px-3 py-2 text-sm text-zinc-400">
                                    {{ __('No data found.') }}</div>
                            </div>

                            {{-- Selected badge --}}
                            <div x-show="selected"
                                class="mt-2 flex items-center gap-2 rounded-md bg-indigo-50 px-3 py-1.5 dark:bg-indigo-900/20">
                                <div class="h-2 w-2 flex-shrink-0 rounded-full bg-indigo-500"></div>
                                <span class="text-xs font-medium text-indigo-700 dark:text-indigo-300"
                                    x-text="selected?.name"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="mt-6 flex items-center gap-3">
                    <flux:button wire:click="cari" color="blue" variant="primary" class="cursor-pointer"
                        wire:loading.attr="disabled" icon="magnifying-glass">
                        <span wire:loading.remove>{{ __('Find') }}</span>
                        <span wire:loading>{{ __('Searching') }}...</span>
                    </flux:button>
                    @if ($searched || $errorMessage)
                        <flux:button wire:click="resetForm" color="zinc" variant="ghost" class="cursor-pointer"
                            icon="arrow-path">
                            {{ __('Reset') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Error / Not-found message --}}
        @if ($errorMessage)
            <div
                class="mt-6 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/40 dark:bg-amber-900/20">
                <svg class="h-5 w-5 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.072 16.5C2.302 17.333 3.264 19 4.804 19z" />
                </svg>
                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">{{ $errorMessage }}</p>
            </div>
        @endif

        {{-- Results --}}
        @if ($found && count($result) > 0)
            @php
                $person1 = $result[0]['user'];
                $person2 = $result[count($result) - 1]['user'];
                $hopCount = count($result) - 1;
            @endphp

            {{-- Summary Banner --}}
            <div
                class="mt-6 overflow-hidden rounded-xl border border-green-200 bg-gradient-to-r from-green-50 to-emerald-50 p-5 dark:border-green-800/40 dark:from-green-900/20 dark:to-emerald-900/20">
                <div class="flex flex-wrap items-center gap-3">
                    <svg class="h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="font-semibold text-green-800 dark:text-green-300">
                            {{ __('Relationship Found!') }}
                        </p>
                        <p class="text-sm text-green-700 dark:text-green-400">
                            <strong>{{ $person1['name'] }}</strong>
                            {{ __('and') }}
                            <strong>{{ $person2['name'] }}</strong>

                            @if ($hopCount === 0)
                                {{ __('They are the same person.') }}
                            @else
                                {{ trans_choice('{1} :count step apart|[2,*] :count steps apart', $hopCount, ['count' => $hopCount]) }}
                            @endif
                        </p>
                    </div>
                    <flux:badge color="green" class="ml-auto">
                        {{ $hopCount }} {{ __('hops') }}
                    </flux:badge>
                </div>
            </div>

            {{-- Full relationship chain text --}}
            <div
                class="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div
                    class="flex items-center gap-2 border-b border-zinc-100 bg-gray-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/50">
                    <svg class="h-4 w-4 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <flux:heading size="lg">{{ __('Full Relationship Chain') }}</flux:heading>
                </div>
                <div class="p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($result as $idx => $step)
                            <a href="{{ route('pengguna.show', $step['user']['id']) }}" wire:navigate
                                class="@if ($idx === 0) bg-blue-600 text-white hover:bg-blue-700 @elseif($idx === count($result) - 1) bg-indigo-600 text-white hover:bg-indigo-700 @else bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700 @endif inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium shadow-sm transition-all hover:scale-105">
                                {{ $step['user']['name'] }} {{ $step['user']['wafat'] ? '(Alm)' : '' }}
                            </a>
                            @if ($idx < count($result) - 1)
                                <svg class="h-4 w-4 flex-shrink-0 text-zinc-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-1 gap-6">
                {{-- Relationship Chart (Custom directional) --}}
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    x-data="{ ready: false, zoom: 1 }"
                    @chart-ready.window="
                        ready = false;
                        $nextTick(() => {
                            if (window.initRelationChart) window.initRelationChart($event.detail.steps);
                            ready = true;
                        })
                    ">
                    <div
                        class="flex items-center gap-2 border-b border-zinc-100 bg-gray-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <svg class="h-4 w-4 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        <flux:heading size="lg">{{ __('Relationship Chart') }}</flux:heading>
                        <div class="ml-auto flex items-center gap-2">
                            <flux:button.group variant="outline" size="sm">
                                <flux:button icon="plus" class="cursor-pointer"
                                    @click="zoom = Math.min(zoom + 0.1, 2.5)" />
                                <flux:button variant="filled" class="w-14! px-0! text-xs! pointer-events-none">
                                    <span x-text="Math.round(zoom * 100) + '%'"></span>
                                </flux:button>
                                <flux:button icon="minus" class="cursor-pointer"
                                    @click="zoom = Math.max(zoom - 0.1, 0.3)" />
                            </flux:button.group>
                        </div>
                    </div>
                    <div class="relative min-h-[460px] w-full overflow-auto bg-zinc-50 dark:bg-zinc-900/50">
                        <div id="relationship-chart-inner" class="inline-block"
                            :style="'transform-origin:top left;transform:scale(' + zoom + ');transition:transform 0.15s;'">
                        </div>
                        <div x-show="!ready"
                            class="absolute inset-0 flex items-center justify-center bg-zinc-50/80 dark:bg-zinc-900/80">
                            <div class="text-center">
                                <svg class="mx-auto mb-3 h-8 w-8 animate-spin text-blue-500" fill="none"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <p class="text-sm text-zinc-400">{{ __('Loading chart...') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($found && count($chartData) > 0)
            <script type="application/json" id="relationship-chart-data">
                {!! json_encode($chartData) !!}
            </script>
        @endif

    </div>
</section>

{{-- SVG Relationship Chart – matching pohon-v2 card style --}}
@push('styles')
    <style>
        #relationship-chart-inner {
            padding: 50px 40px 40px;
        }

        .rc-node {
            width: 220px;
            height: 70px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: box-shadow 0.2s, transform 0.2s;
            text-decoration: none;
        }

        .rc-node:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.22);
        }

        .rc-node-avatar {
            width: 70px;
            height: 70px;
            flex-shrink: 0;
            background-color: rgba(0, 0, 0, 0.15);
            border-right: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rc-node-avatar img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            display: block;
        }

        .rc-node-body {
            flex: 1;
            padding: 6px 10px;
            min-width: 0;
            font-size: 13px;
            font-weight: 500;
            line-height: 1.35;
            color: #fff;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            word-wrap: break-word;
        }

        .rc-node-info {
            flex-shrink: 0;
            width: 40px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-left: 1px solid rgba(0, 0, 0, 0.1);
            transition: background-color 0.15s;
            cursor: pointer;
        }

        .rc-node-info:hover {
            background-color: rgba(0, 0, 0, 0.2);
        }

        .rc-node-badge {
            display: block;
            margin-bottom: 4px;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 10px;
            border-radius: 99px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
            width: fit-content;
            white-space: nowrap;
        }
    </style>
@endpush

@push('scripts')
    <script>
        window.initRelationChart = function(steps) {
            const container = document.getElementById('relationship-chart-inner');
            if (!container || !steps || steps.length === 0) return;

            const H_RELS = new Set(['sibling_of', 'husband_of', 'wife_of']);
            const NW = 220,
                NH = 70;
            const HG = 60,
                VG = 56;
            const PAD_X = 40,
                PAD_Y = 50;
            const isDark = document.documentElement.classList.contains('dark');

            // ── Same bg colours as pohon-v2 ───────────────────────────────────────
            const BG = {
                m: '#7ea4b3',
                f: '#ce969b'
            };

            // ── Connector stroke per relation ──────────────────────────────────────
            const CONN = {
                child_of_father: '#64748b',
                child_of_mother: '#64748b',
                father_of: '#64748b',
                mother_of: '#64748b',
                husband_of: '#f59e0b',
                wife_of: '#f59e0b',
                sibling_of: '#10b981',
            };
            const defaultStroke = isDark ? '#52525b' : '#94a3b8';

            // ── Labels ────────────────────────────────────────────────────────────
            const REL_LABEL = {
                child_of_father: 'Anak dari',
                child_of_mother: 'Anak dari',
                father_of: 'Ayah dari',
                mother_of: 'Ibu dari',
                husband_of: 'Suami dari',
                wife_of: 'Istri dari',
                sibling_of: 'Saudara dari',
            };

            // ── Default silhouette (same as pohon-v2) ─────────────────────────────
            const silhouette =
                `<svg viewBox="0 0 24 24" fill="white" width="40" height="40" style="margin:auto;opacity:0.9;"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd"/></svg>`;

            // ── Info icon (same as pohon-v2) ──────────────────────────────────────
            const infoIcon =
                `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="white" style="width:20px;height:20px;opacity:0.9;"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>`;

            // ── Layout ────────────────────────────────────────────────────────────
            let cx = PAD_X,
                cy = PAD_Y;
            const nodes = steps.map((step, i) => {
                const n = {
                    ...step,
                    x: cx,
                    y: cy
                };
                if (i < steps.length - 1)
                    H_RELS.has(steps[i + 1].relation) ? (cx += NW + HG) : (cy += NH + VG);
                return n;
            });

            const totalW = Math.max(...nodes.map(n => n.x)) + NW + PAD_X;
            const totalH = Math.max(...nodes.map(n => n.y)) + NH + PAD_X;

            // ── Arrow markers ──────────────────────────────────────────────────────
            const uid = 'rca' + Date.now();
            const usedColors = new Set(steps.slice(1).map(s => CONN[s.relation] || defaultStroke));
            let defs = '<defs>';
            usedColors.forEach(c => {
                const mid = uid + c.replace('#', '');
                defs +=
                    `<marker id="${mid}" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 Z" fill="${c}"/></marker>`;
            });
            defs += '</defs>';

            // ── Paths ─────────────────────────────────────────────────────────────
            let paths = '';
            for (let i = 0; i < nodes.length - 1; i++) {
                const a = nodes[i],
                    b = nodes[i + 1];
                const rel = b.relation;
                const isH = H_RELS.has(rel);
                const stroke = CONN[rel] || defaultStroke;
                const mid = uid + stroke.replace('#', '');
                const label = REL_LABEL[rel] || '';
                const isDash = rel === 'sibling_of';

                let x1, y1, x2, y2, cp1x, cp1y, cp2x, cp2y, lx, ly, lAnchor;
                if (isH) {
                    x1 = a.x + NW;
                    y1 = a.y + NH / 2;
                    x2 = b.x;
                    y2 = b.y + NH / 2;
                    const mx = (x1 + x2) / 2;
                    cp1x = mx;
                    cp1y = y1;
                    cp2x = mx;
                    cp2y = y2;
                    lx = mx;
                    ly = Math.min(y1, y2) - 14;
                    lAnchor = 'middle';
                } else {
                    x1 = a.x + NW / 2;
                    y1 = a.y + NH;
                    x2 = b.x + NW / 2;
                    y2 = b.y;
                    const my = (y1 + y2) / 2;
                    cp1x = x1;
                    cp1y = my;
                    cp2x = x2;
                    cp2y = my;
                    lx = x1 + 11;
                    ly = my + 4;
                    lAnchor = 'start';
                }

                paths +=
                    `<path d="M${x1},${y1} C${cp1x},${cp1y} ${cp2x},${cp2y} ${x2},${y2}" fill="none" stroke="${stroke}" stroke-width="2" stroke-dasharray="${isDash?'7,4':'none'}" marker-end="url(#${mid})"/>`;

                if (label) {
                    const lw = label.length * 5.5 + 20,
                        lh = 17;
                    const bgX = lAnchor === 'middle' ? lx - lw / 2 : lx - 5;
                    const bgFill = isDark ? '#27272a' : '#f1f5f9';
                    const txtFill = isDark ? '#d4d4d8' : '#475569';
                    paths +=
                        `<rect x="${bgX}" y="${ly-lh+3}" width="${lw}" height="${lh}" rx="9" fill="${bgFill}" stroke="${stroke}" stroke-width="1" opacity="0.95"/>`;
                    paths +=
                        `<text x="${lx}" y="${ly-lh+14}" text-anchor="${lAnchor}" dominant-baseline="auto" font-size="10" font-style="italic" fill="${txtFill}" pointer-events="none">${label}</text>`;
                }
            }

            const svgEl =
                `<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;pointer-events:none;overflow:visible;z-index:1;" width="${totalW}" height="${totalH}">${defs}${paths}</svg>`;

            // ── Node cards ────────────────────────────────────────────────────────
            let cards = '';
            nodes.forEach((node, i) => {
                const u = node.user;
                const isM = u.jenis_kelamin == '1';
                const isFirst = i === 0,
                    isLast = i === nodes.length - 1;
                const bg = isM ? BG.m : BG.f;
                const name = (u.name || '') + (u.wafat ? ' (Alm)' : '');
                const foto = u.foto ? `/storage/${u.foto}` : null;
                const href = `/pengguna/${u.id}`;
                const defImg = isM ? '/img/avatar-m.jpg' : '/img/avatar-f.jpg';

                const avatarHtml = foto ?
                    `<img src="${foto}" style="width:70px;height:70px;object-fit:cover;" onerror="this.src='${defImg}'">` :
                    silhouette;

                const outline = isFirst ?
                    'box-shadow:0 0 0 3px #facc15,0 4px 6px rgba(0,0,0,0.15);' :
                    isLast ?
                    'box-shadow:0 0 0 3px #a78bfa,0 4px 6px rgba(0,0,0,0.15);' :
                    '';

                const badgeStyle = isFirst ?
                    'background:#facc15;color:#713f12;' :
                    'background:#a78bfa;color:#fff;';
                const badgeLabel = isFirst ? 'Orang 1 ▶' : 'Orang 2 ◀';
                const badge = (isFirst || isLast) ?
                    `<span class="rc-node-badge" style="${badgeStyle}">${badgeLabel}</span>` :
                    '';

                cards += `<div style="position:absolute;left:${node.x}px;top:${node.y - (isFirst||isLast?22:0)}px;z-index:2;">
            ${badge}
            <div class="rc-node" style="background-color:${bg};${outline}">
                <div class="rc-node-avatar">${avatarHtml}</div>
                <div class="rc-node-body">${name}</div>
                <div class="rc-node-info" onclick="event.stopPropagation();window.Livewire&&Livewire.navigate('${href}');" title="Detail">${infoIcon}</div>
            </div>
        </div>`;
            });

            container.style.position = 'relative';
            container.style.width = totalW + 'px';
            container.style.height = (totalH + 30) + 'px';
            container.innerHTML = svgEl + cards;
        };
    </script>
@endpush
