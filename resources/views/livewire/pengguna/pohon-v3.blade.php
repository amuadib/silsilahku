<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Pernikahan;

new class extends Component {
    public User $user;
    public ?User $ayah = null;
    public ?User $ibu = null;
    public ?User $kakekAyah = null;
    public ?User $nenekAyah = null;
    public ?User $kakekIbu = null;
    public ?User $nenekIbu = null;
    public array $spouses = [];
    public array $childrenData = [];
    public array $siblingsData = [];
    public array $deceasedList = [];
    public int $totalMember = 0;

    public function mount(string $id)
    {
        $this->loadUserData($id);
    }

    public function switchUser(string $id, bool $pushHistory = true): void
    {
        $this->loadUserData($id);

        if ($pushHistory) {
            $url = route('pengguna.pohon-v3', $id);
            $safeName = addslashes($this->user->name);
            $this->js("window.history.pushState({ userId: '{$id}' }, '', '{$url}'); document.title = 'Bani {$safeName}';");
        }

        $this->dispatch('user-switched');
    }

    private function loadUserData(string $id): void
    {
        $this->user = User::findOrFail($id);
        $this->ayah = null;
        $this->ibu = null;
        $this->kakekAyah = null;
        $this->nenekAyah = null;
        $this->kakekIbu = null;
        $this->nenekIbu = null;
        $this->spouses = [];
        $this->childrenData = [];
        $this->siblingsData = [];
        $this->deceasedList = [];
        $this->totalMember = 0;

        $this->loadFamilyGrid();
        $this->calculateTotalMembersAndDeceased();
    }

    private function calculateTotalMembersAndDeceased(): void
    {
        $visited = [];
        $this->walkLineage($this->user, $visited);
        $this->totalMember = count($visited);

        if (!empty($visited)) {
            $this->deceasedList = User::whereIn('id', $visited)
                ->where('wafat', true)
                ->orderBy('tanggal_wafat', 'asc')
                ->orderBy('name')
                ->get(['id', 'name', 'jenis_kelamin', 'foto', 'tanggal_wafat'])
                ->toArray();
        }
    }

    private function walkLineage(User $user, array &$visited): void
    {
        if (in_array($user->id, $visited)) {
            return;
        }
        $visited[] = $user->id;

        $pernikahans = $user
            ->pernikahan()
            ->with(['istri', 'suami', 'anak'])
            ->get();

        foreach ($pernikahans as $p) {
            $spouse = $user->jenis_kelamin == '1' ? $p->istri : $p->suami;
            if ($spouse && !in_array($spouse->id, $visited)) {
                $visited[] = $spouse->id;
            }

            foreach ($p->anak as $anak) {
                $this->walkLineage($anak, $visited);
            }
        }
    }

    private function loadFamilyGrid(): void
    {
        // 1. Ayah & Kakek/Nenek dari Ayah
        if ($this->user->ayah_id) {
            $this->ayah = User::with(['ayah', 'ibu'])->find($this->user->ayah_id);
            if ($this->ayah) {
                $this->kakekAyah = $this->ayah->ayah;
                $this->nenekAyah = $this->ayah->ibu;
            }
        }

        // 2. Ibu & Kakek/Nenek dari Ibu
        if ($this->user->ibu_id) {
            $this->ibu = User::with(['ayah', 'ibu'])->find($this->user->ibu_id);
            if ($this->ibu) {
                $this->kakekIbu = $this->ibu->ayah;
                $this->nenekIbu = $this->ibu->ibu;
            }
        }

        // 3. Pasangan Pengguna
        if ($this->user->jenis_kelamin == '1') {
            $this->spouses = $this->user->istri()->get()->all();
        } else {
            $this->spouses = $this->user->suami()->get()->all();
        }

        // 4. Anak-anak & Cucu-cucu
        $this->childrenData = [];
        $pernikahans = Pernikahan::where(function ($q) {
            $q->where('suami_id', $this->user->id)
              ->orWhere('istri_id', $this->user->id);
        })->with(['suami', 'istri', 'anak' => function ($q) {
            $q->orderBy('anak_ke')->orderBy('name');
        }])->get();

        $processedChildIds = [];
        foreach ($pernikahans as $p) {
            $spouse = ($this->user->jenis_kelamin == '1') ? $p->istri : $p->suami;
            foreach ($p->anak as $anak) {
                if (in_array($anak->id, $processedChildIds)) {
                    continue;
                }
                $processedChildIds[] = $anak->id;

                // Ambil cucu dari anak ini
                $cucuList = [];
                $anakPernikahan = Pernikahan::where('suami_id', $anak->id)
                    ->orWhere('istri_id', $anak->id)
                    ->with(['anak' => fn($q) => $q->orderBy('anak_ke')->orderBy('name')])
                    ->get();

                foreach ($anakPernikahan as $ap) {
                    foreach ($ap->anak as $cucu) {
                        $cucuList[] = $cucu;
                    }
                }

                $this->childrenData[] = [
                    'child' => $anak,
                    'spouse' => $spouse,
                    'grandchildren' => $cucuList,
                ];
            }
        }

        // 5. Saudara, Keponakan, & Cucu-cucu
        $this->siblingsData = [];
        $siblingQuery = User::where('id', '!=', $this->user->id);

        if ($this->user->ortu_id) {
            $siblingQuery->where('ortu_id', $this->user->ortu_id);
        } elseif ($this->user->ayah_id || $this->user->ibu_id) {
            $siblingQuery->where(function ($q) {
                if ($this->user->ayah_id) {
                    $q->where('ayah_id', $this->user->ayah_id);
                }
                if ($this->user->ibu_id) {
                    $q->orWhere('ibu_id', $this->user->ibu_id);
                }
            });
        } else {
            $siblingQuery->whereRaw('1 = 0');
        }

        $siblings = $siblingQuery->orderBy('anak_ke')->orderBy('name')->get();

        foreach ($siblings as $saudara) {
            $saudaraSpouses = ($saudara->jenis_kelamin == '1')
                ? $saudara->istri()->get()
                : $saudara->suami()->get();

            $keponakanList = [];
            $saudaraPernikahans = Pernikahan::where('suami_id', $saudara->id)
                ->orWhere('istri_id', $saudara->id)
                ->with(['anak' => fn($q) => $q->orderBy('anak_ke')->orderBy('name')])
                ->get();

            foreach ($saudaraPernikahans as $sp) {
                foreach ($sp->anak as $keponakan) {
                    $cucuKeponakan = Pernikahan::where('suami_id', $keponakan->id)
                        ->orWhere('istri_id', $keponakan->id)
                        ->with(['anak' => fn($q) => $q->orderBy('anak_ke')->orderBy('name')])
                        ->get()
                        ->flatMap->anak
                        ->all();

                    $keponakanList[] = [
                        'keponakan' => $keponakan,
                        'cucu' => $cucuKeponakan,
                    ];
                }
            }

            $this->siblingsData[] = [
                'sibling' => $saudara,
                'spouses' => $saudaraSpouses,
                'nephews' => $keponakanList,
            ];
        }
    }
};
?>

<section class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="w-full">

        {{-- ── Page Header (Identik dengan daftar-v2) ─────────────────── --}}
        <div class="relative my-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div>
                <div class="flex items-center gap-2">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-sm">
                        <svg class="h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V18ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                    </div>
                    <div>
                        <flux:heading size="xl" level="1">
                            Bani {{ $user->name }}
                        </flux:heading>
                        <div class="mt-0.5 flex items-center gap-2">
                            <flux:subheading size="lg">{{ __('Bagan Keluarga') }}</flux:subheading>
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM6 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM1.49 15.326a.78.78 0 0 1-.358-.442 3 3 0 0 1 4.308-3.516 6.484 6.484 0 0 0-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 0 1-2.07-.655ZM16.44 15.98a4.97 4.97 0 0 0 2.07-.654.78.78 0 0 0 .357-.442 3 3 0 0 0-4.308-3.517 6.484 6.484 0 0 1 1.907 3.96 2.32 2.32 0 0 1-.026.654ZM18 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM5.304 16.19a.844.844 0 0 1-.277-.71 5 5 0 0 1 9.947 0 .843.843 0 0 1-.277.71A6.975 6.975 0 0 1 10 18a6.974 6.974 0 0 1-4.696-1.81Z"/></svg>
                                {{ $totalMember }} {{ __('Members') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <flux:button color="zinc" icon="printer" onclick="safePrintPage()" class="cursor-pointer" variant="outline" size="sm">
                    Print
                </flux:button>
                <flux:button color="zinc" href="{{ route('pengguna.daftar-v2', $user->id) }}" wire:navigate class="cursor-pointer" variant="outline" icon="users" size="sm">
                    {{ __('List of Family members') }}
                </flux:button>
                {{-- <flux:button color="zinc" href="{{ route('pengguna.pohon-v2', $user->id) }}" wire:navigate class="cursor-pointer" variant="outline" icon="share" size="sm">
                    {{ __('Family Tree') }}
                </flux:button>
                <flux:button color="zinc" href="{{ route('pengguna.daftar-collapse', $user->id) }}" wire:navigate class="cursor-pointer" variant="outline" icon="share" size="sm">
                    {{ __('Collapsible') }}
                </flux:button> --}}
                @auth
                    <flux:button color="cyan" href="{{ route('pengguna.hubungan') }}" wire:navigate class="cursor-pointer" variant="outline" icon="arrows-right-left" size="sm">
                        {{ __('Find Relationship') }}
                    </flux:button>
                @endauth
                <flux:button color="blue" href="{{ route('pengguna.show', $user->id) }}" wire:navigate class="cursor-pointer" variant="primary" icon="eye" size="sm">
                    {{ __('Detail') }}
                </flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />

        {{-- ── Legend (Identik dengan daftar-v2) ───────────────────────── --}}
        <div class="my-4 flex flex-wrap items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400 print:hidden">
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-2 w-2 rounded-full bg-blue-400"></span> Laki-laki
            </span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-2 w-2 rounded-full bg-pink-400"></span> Perempuan
            </span>
            <span class="flex items-center gap-1.5">
                <span class="rounded bg-red-100 px-1 py-0.5 text-[10px] font-semibold uppercase text-red-600 dark:bg-red-900/40 dark:text-red-400">Alm</span>
                Almarhum/ah
            </span>
            <span class="text-zinc-300 dark:text-zinc-600">|</span>
            <span class="text-zinc-400 dark:text-zinc-500">Klik nama untuk memindahkan fokus bagan keluarga</span>
        </div>

        {{-- ── Main Card (Identik dengan Card di daftar-v2) ────────────── --}}
        <div id="main-family-card"
             wire:key="main-family-card"
             x-data="{
                isFullscreen: false,
                toggleFullscreen() {
                    const isFs = document.fullscreenElement || document.webkitFullscreenElement;
                    if (!isFs) {
                        if ($el.requestFullscreen) {
                            $el.requestFullscreen();
                        } else if ($el.webkitRequestFullscreen) {
                            $el.webkitRequestFullscreen();
                        }
                    } else {
                        if (document.exitFullscreen) {
                            document.exitFullscreen();
                        } else if (document.webkitExitFullscreen) {
                            document.webkitExitFullscreen();
                        }
                    }
                }
             }"
             x-init="
                const onFsChange = () => {
                    const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
                    isFullscreen = (fsEl === $el);
                };
                document.addEventListener('fullscreenchange', onFsChange);
                document.addEventListener('webkitfullscreenchange', onFsChange);

                window.addEventListener('popstate', (e) => {
                    const match = window.location.pathname.match(/\/pengguna\/(?:bagan|pohon-v3)\/([^\/]+)/);
                    if (match && match[1]) {
                        $wire.switchUser(match[1], false);
                    }
                });
             "
             @user-switched.window="$el.scrollTop = 0"
             class="main-card-fullscreen overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 print:border-none print:shadow-none">

            {{-- Card Header dengan Gradient --}}
            <div class="card-header-sticky relative border-b border-zinc-100 bg-gradient-to-r from-indigo-50 via-purple-50 to-blue-50 px-6 py-4 dark:border-zinc-800 dark:from-indigo-900/20 dark:via-purple-900/10 dark:to-blue-900/20 print:hidden">
                {{-- Floating Loading Indicator di Tengah Atas (Tidak Menggeser Layout) --}}
                <div wire:loading.flex class="pointer-events-none absolute left-1/2 top-1/2 z-30 -translate-x-1/2 -translate-y-1/2 items-center gap-2 rounded-full border border-indigo-200/90 bg-white/95 px-3.5 py-1 text-xs font-medium text-indigo-700 shadow-sm backdrop-blur-md dark:border-indigo-800/80 dark:bg-zinc-800/95 dark:text-indigo-300">
                    <svg class="h-3.5 w-3.5 animate-spin text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Memuat...</span>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/70 shadow-sm dark:bg-zinc-800/70">
                            <svg class="h-4 w-4 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V18ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">Bagan Silsilah Keluarga</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Hubungan kakek/nenek, orang tua, pasangan, anak/cucu, dan saudara</p>
                        </div>
                    </div>

                    {{-- Tombol Fullscreen Main Card --}}
                    <div class="flex items-center">
                        <button type="button"
                                @click="toggleFullscreen()"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200/80 bg-white/90 px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-sm backdrop-blur-sm transition-all hover:border-indigo-300 hover:bg-white hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800/90 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-indigo-400 cursor-pointer"
                                :title="isFullscreen ? 'Keluar Fullscreen (Esc)' : 'Tampilkan Fullscreen'">
                            <svg x-show="!isFullscreen" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                            </svg>
                            <svg x-show="isFullscreen" style="display: none;" class="h-4 w-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25" />
                            </svg>
                            <span x-text="isFullscreen ? 'Keluar Fullscreen' : 'Fullscreen'" class="hidden sm:inline"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── Quick Navigation (Node Diatasnya / Dibawahnya) ── --}}
            @if ($ayah || $ibu || !empty($childrenData))
                <div class="border-b border-zinc-100 bg-zinc-50/70 px-4 py-2.5 sm:px-6 dark:border-zinc-800 dark:bg-zinc-800/40 print:hidden">
                    <div class="flex flex-col gap-2">

                        {{-- Node Diatasnya (Ayah / Ibu) --}}
                        @if ($ayah || $ibu)
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-500 dark:text-zinc-400">
                                    <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                                    </svg>
                                    <span>{{ __('Ke Orang Tua') }}:</span>
                                </span>

                                @if ($ayah)
                                    <a href="{{ route('pengguna.pohon-v3', $ayah->id) }}"
                                       wire:click.prevent="switchUser('{{ $ayah->id }}')"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200/90 bg-white px-2.5 py-1 text-xs font-medium text-blue-700 shadow-2xs transition-all hover:border-blue-400 hover:bg-blue-50 dark:border-blue-800/70 dark:bg-zinc-800 dark:text-blue-300 dark:hover:bg-blue-950/40">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                        <span>{{ __('Ayah') }}: <strong>{{ $ayah->name }}</strong></span>
                                        @if ($ayah->wafat)
                                            <span class="rounded bg-red-100 px-1 py-0.5 text-[9px] font-semibold text-red-600 dark:bg-red-900/50 dark:text-red-400">Alm</span>
                                        @endif
                                    </a>
                                @endif

                                @if ($ibu)
                                    <a href="{{ route('pengguna.pohon-v3', $ibu->id) }}"
                                       wire:click.prevent="switchUser('{{ $ibu->id }}')"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-pink-200/90 bg-white px-2.5 py-1 text-xs font-medium text-pink-700 shadow-2xs transition-all hover:border-pink-400 hover:bg-pink-50 dark:border-pink-800/70 dark:bg-zinc-800 dark:text-pink-300 dark:hover:bg-pink-950/40">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-pink-500"></span>
                                        <span>{{ __('Ibu') }}: <strong>{{ $ibu->name }}</strong></span>
                                        @if ($ibu->wafat)
                                            <span class="rounded bg-red-100 px-1 py-0.5 text-[9px] font-semibold text-red-600 dark:bg-red-900/50 dark:text-red-400">Alm</span>
                                        @endif
                                    </a>
                                @endif
                            </div>
                        @endif

                        {{-- Node Dibawahnya (Anak-anak) --}}
                        @if (!empty($childrenData))
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-500 dark:text-zinc-400">
                                    <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                                    </svg>
                                    <span>{{ __('Ke Anak') }} ({{ count($childrenData) }}):</span>
                                </span>

                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach ($childrenData as $index => $item)
                                        @php
                                            $child = $item['child'];
                                            $aDot = $child->jenis_kelamin == '1' ? 'bg-blue-400 dark:bg-blue-500' : 'bg-pink-400 dark:bg-pink-500';
                                        @endphp
                                        <a href="{{ route('pengguna.pohon-v3', $child->id) }}"
                                           wire:click.prevent="switchUser('{{ $child->id }}')"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200/90 bg-white px-2 py-0.5 text-zinc-700 shadow-2xs transition-all hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-600 dark:border-zinc-700/80 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:border-indigo-700 dark:hover:bg-zinc-800/80 dark:hover:text-indigo-300">
                                            <span class="text-[10px] font-bold text-zinc-400">{{ $index + 1 }}.</span>
                                            <span class="inline-block h-1.5 w-1.5 rounded-full {{ $aDot }}"></span>
                                            <span class="font-medium">{{ $child->name }}</span>
                                            @if ($child->wafat)
                                                <span class="rounded bg-red-100 px-1 py-0.5 text-[9px] font-semibold text-red-600 dark:bg-red-900/50 dark:text-red-400">Alm</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            @endif

            {{-- Table Content --}}
            <div class="flex-1 overflow-x-auto p-4 sm:p-6 print:p-0"
                 wire:loading.class="opacity-60 transition-opacity duration-200 pointer-events-none">
                <table class="w-full min-w-[760px] border-collapse border border-zinc-200 text-sm dark:border-zinc-700/80">
                    <tbody>

                        {{-- ── BARIS 1: Kakek & Nenek ────────────────── --}}
                        <tr class="border-b border-zinc-200 dark:border-zinc-700/80">
                            <th class="w-48 border-r border-zinc-200 bg-zinc-50/80 p-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:border-zinc-700/80 dark:bg-zinc-800/50 dark:text-zinc-400 align-middle">
                                {{ __('Kakek & Nenek') }}
                            </th>

                            {{-- Kakek dari Ayah --}}
                            <td class="w-1/4 border-r border-zinc-200 p-3.5 text-center align-middle dark:border-zinc-700/80">
                                @if ($kakekAyah)
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-blue-400 dark:bg-blue-500"></span>
                                        <a href="{{ route('pengguna.pohon-v3', $kakekAyah->id) }}" wire:click.prevent="switchUser('{{ $kakekAyah->id }}')"
                                           class="text-[14px] font-medium text-zinc-800 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                                            {{ $kakekAyah->name }}
                                        </a>
                                        @if ($kakekAyah->wafat)
                                            <span class="rounded bg-red-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400 print:hidden">Alm</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">?</span>
                                @endif
                            </td>

                            {{-- Nenek dari Ayah --}}
                            <td class="w-1/4 border-r border-zinc-200 p-3.5 text-center align-middle dark:border-zinc-700/80">
                                @if ($nenekAyah)
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-pink-400 dark:bg-pink-500"></span>
                                        <a href="{{ route('pengguna.pohon-v3', $nenekAyah->id) }}" wire:click.prevent="switchUser('{{ $nenekAyah->id }}')"
                                           class="text-[14px] font-medium text-zinc-800 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                                            {{ $nenekAyah->name }}
                                        </a>
                                        @if ($nenekAyah->wafat)
                                            <span class="rounded bg-red-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400 print:hidden">Alm</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">?</span>
                                @endif
                            </td>

                            {{-- Kakek dari Ibu --}}
                            <td class="w-1/4 border-r border-zinc-200 p-3.5 text-center align-middle dark:border-zinc-700/80">
                                @if ($kakekIbu)
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-blue-400 dark:bg-blue-500"></span>
                                        <a href="{{ route('pengguna.pohon-v3', $kakekIbu->id) }}" wire:click.prevent="switchUser('{{ $kakekIbu->id }}')"
                                           class="text-[14px] font-medium text-zinc-800 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                                            {{ $kakekIbu->name }}
                                        </a>
                                        @if ($kakekIbu->wafat)
                                            <span class="rounded bg-red-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400 print:hidden">Alm</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">?</span>
                                @endif
                            </td>

                            {{-- Nenek dari Ibu --}}
                            <td class="w-1/4 p-3.5 text-center align-middle">
                                @if ($nenekIbu)
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-pink-400 dark:bg-pink-500"></span>
                                        <a href="{{ route('pengguna.pohon-v3', $nenekIbu->id) }}" wire:click.prevent="switchUser('{{ $nenekIbu->id }}')"
                                           class="text-[14px] font-medium text-zinc-800 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                                            {{ $nenekIbu->name }}
                                        </a>
                                        @if ($nenekIbu->wafat)
                                            <span class="rounded bg-red-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400 print:hidden">Alm</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">?</span>
                                @endif
                            </td>
                        </tr>

                        {{-- ── BARIS 2: Ayah & Ibu ────────────────────── --}}
                        <tr class="border-b border-zinc-200 dark:border-zinc-700/80">
                            <th class="w-48 border-r border-zinc-200 bg-zinc-50/80 p-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:border-zinc-700/80 dark:bg-zinc-800/50 dark:text-zinc-400 align-middle">
                                {{ __('Ayah & Ibu') }}
                            </th>

                            {{-- Ayah (colspan 2 di bawah Kakek-Nenek Ayah) --}}
                            <td colspan="2" class="border-r border-zinc-200 p-4 text-center align-middle dark:border-zinc-700/80">
                                @if ($ayah)
                                    <div class="inline-flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-blue-400 dark:bg-blue-500"></span>
                                        <a href="{{ route('pengguna.pohon-v3', $ayah->id) }}" wire:click.prevent="switchUser('{{ $ayah->id }}')"
                                           class="text-[15px] font-semibold text-zinc-800 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                                            {{ $ayah->name }}
                                        </a>
                                        @if ($ayah->wafat)
                                            <span class="rounded bg-red-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400 print:hidden">Alm</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">?</span>
                                @endif
                            </td>

                            {{-- Ibu (colspan 2 di bawah Kakek-Nenek Ibu) --}}
                            <td colspan="2" class="p-4 text-center align-middle">
                                @if ($ibu)
                                    <div class="inline-flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-pink-400 dark:bg-pink-500"></span>
                                        <a href="{{ route('pengguna.pohon-v3', $ibu->id) }}" wire:click.prevent="switchUser('{{ $ibu->id }}')"
                                           class="text-[15px] font-semibold text-zinc-800 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                                            {{ $ibu->name }}
                                        </a>
                                        @if ($ibu->wafat)
                                            <span class="rounded bg-red-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400 print:hidden">Alm</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">?</span>
                                @endif
                            </td>
                        </tr>

                        {{-- ── BARIS 3: Pengguna Utama & Pasangan (Fokus) ── --}}
                        <tr class="border-b-2 border-indigo-200 bg-gradient-to-r from-indigo-50/70 via-purple-50/50 to-blue-50/70 dark:border-indigo-900/50 dark:from-indigo-950/30 dark:via-purple-950/20 dark:to-blue-950/30">
                            <th class="w-48 border-r border-zinc-200 bg-zinc-50/80 p-4 text-left text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:border-zinc-700/80 dark:bg-zinc-800/50 dark:text-indigo-400 align-middle">
                                {{ __('Fokus') }}
                            </th>
                            <td colspan="4" class="p-5 text-center align-middle">
                                <div class="inline-flex flex-wrap items-center justify-center gap-2.5">
                                    {{-- Pengguna Utama --}}
                                    <div class="inline-flex items-center gap-2 rounded-lg bg-white/80 px-3 py-1.5 shadow-xs dark:bg-zinc-800/80">
                                        <span class="inline-block h-2 w-2 shrink-0 rounded-full {{ $user->jenis_kelamin == '1' ? 'bg-blue-500' : 'bg-pink-500' }}"></span>
                                        <span class="text-base font-bold text-zinc-900 dark:text-zinc-100 sm:text-lg">
                                            {{ $user->name }}
                                        </span>
                                        @if ($user->wafat)
                                            <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400">Alm</span>
                                        @endif
                                    </div>

                                    {{-- Pasangan jika ada --}}
                                    @if (!empty($spouses))
                                        @foreach ($spouses as $sp)
                                            <span class="text-zinc-400 dark:text-zinc-500 font-semibold">&amp;</span>
                                            <a href="{{ route('pengguna.pohon-v3', $sp->id) }}" wire:click.prevent="switchUser('{{ $sp->id }}')"
                                               class="inline-flex items-center gap-2 rounded-lg bg-white/80 px-3 py-1.5 shadow-xs transition-colors hover:bg-white dark:bg-zinc-800/80 dark:hover:bg-zinc-800">
                                                <span class="inline-block h-2 w-2 shrink-0 rounded-full {{ $sp->jenis_kelamin == '1' ? 'bg-blue-500' : 'bg-pink-500' }}"></span>
                                                <span class="text-base font-semibold text-zinc-800 hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400">
                                                    {{ $sp->name }}
                                                </span>
                                                @if ($sp->wafat)
                                                    <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400">Alm</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- ── BARIS 4: Anak-anak & Cucu-cucu ─────────── --}}
                        <tr class="border-b border-zinc-200 dark:border-zinc-700/80">
                            <th class="w-48 border-r border-zinc-200 bg-zinc-50/80 p-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:border-zinc-700/80 dark:bg-zinc-800/50 dark:text-zinc-400 align-top">
                                <div>{{ __('Anak-anak & Cucu-cucu') }}</div>
                                @if(!empty($childrenData))
                                    <div class="mt-1 text-[11px] font-normal normal-case text-zinc-400">
                                        {{ count($childrenData) }} {{ __('anak') }}
                                    </div>
                                @endif
                            </th>
                            <td colspan="4" class="p-3.5 align-top">
                                @if (!empty($childrenData))
                                    <div class="overflow-x-auto">
                                        <table class="w-full border-collapse border border-zinc-200 text-xs dark:border-zinc-700/80">
                                            <thead>
                                                <tr class="bg-zinc-50/70 dark:bg-zinc-800/60">
                                                    @foreach ($childrenData as $index => $item)
                                                        @php
                                                            $cDot = $item['child']->jenis_kelamin == '1' ? 'bg-blue-400 dark:bg-blue-500' : 'bg-pink-400 dark:bg-pink-500';
                                                        @endphp
                                                        <th class="border border-zinc-200 p-2.5 text-left font-medium text-zinc-800 dark:border-zinc-700/80 dark:text-zinc-200 min-w-[170px]">
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="text-xs font-bold text-indigo-500 dark:text-indigo-400">{{ $index + 1 }}.</span>
                                                                <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full {{ $cDot }}"></span>
                                                                <a href="{{ route('pengguna.pohon-v3', $item['child']->id) }}" wire:click.prevent="switchUser('{{ $item['child']->id }}')"
                                                                   class="text-[13px] font-semibold text-zinc-800 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400 truncate">
                                                                    {{ $item['child']->name }}
                                                                </a>
                                                                @if ($item['child']->wafat)
                                                                    <span class="rounded bg-red-100 px-1 py-0.5 text-[9px] font-semibold uppercase text-red-600 dark:bg-red-900/40 dark:text-red-400">Alm</span>
                                                                @endif
                                                            </div>
                                                        </th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    @foreach ($childrenData as $item)
                                                        <td class="border border-zinc-200 p-2.5 align-top dark:border-zinc-700/80">
                                                            @if (!empty($item['grandchildren']))
                                                                <div class="space-y-1.5">
                                                                    <div class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Cucu:</div>
                                                                    <div class="space-y-1">
                                                                        @foreach ($item['grandchildren'] as $cIndex => $cucu)
                                                                            @php
                                                                                $gDot = $cucu->jenis_kelamin == '1' ? 'bg-blue-400 dark:bg-blue-500' : 'bg-pink-400 dark:bg-pink-500';
                                                                            @endphp
                                                                            <div class="flex items-center gap-1.5 text-[12px]">
                                                                                <span class="text-[11px] font-bold text-zinc-400">{{ $cIndex + 1 }}.</span>
                                                                                <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full {{ $gDot }}"></span>
                                                                                <a href="{{ route('pengguna.pohon-v3', $cucu->id) }}" wire:click.prevent="switchUser('{{ $cucu->id }}')"
                                                                                   class="text-zinc-700 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-300 dark:hover:text-blue-400 truncate">
                                                                                    {{ $cucu->name }}
                                                                                </a>
                                                                                @if ($cucu->wafat)
                                                                                    <span class="rounded bg-red-100 px-1 py-0.5 text-[9px] font-semibold uppercase text-red-600 dark:bg-red-900/40 dark:text-red-400">Alm</span>
                                                                                @endif
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <div class="py-2 text-center text-xs text-zinc-300 dark:text-zinc-600">—</div>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="p-2 text-xs italic text-zinc-400 dark:text-zinc-500">
                                        {{ __('Belum ada data anak') }}
                                    </div>
                                @endif
                            </td>
                        </tr>

                        {{-- ── BARIS 5: Saudara, Keponakan, & Cucu-cucu ── --}}
                        <tr>
                            <th class="w-48 border-r border-zinc-200 bg-zinc-50/80 p-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:border-zinc-700/80 dark:bg-zinc-800/50 dark:text-zinc-400 align-top">
                                <div>{{ __('Saudara & Keponakan') }}</div>
                                @if(!empty($siblingsData))
                                    <div class="mt-1 text-[11px] font-normal normal-case text-zinc-400">
                                        {{ count($siblingsData) }} {{ __('saudara') }}
                                    </div>
                                @endif
                            </th>
                            <td colspan="4" class="p-3.5 align-top">
                                @if (!empty($siblingsData))
                                    <div class="overflow-x-auto">
                                        <table class="w-full border-collapse border border-zinc-200 text-xs dark:border-zinc-700/80">
                                            <thead>
                                                <tr class="bg-zinc-50/70 dark:bg-zinc-800/60">
                                                    @foreach ($siblingsData as $index => $item)
                                                        @php
                                                            $sDot = $item['sibling']->jenis_kelamin == '1' ? 'bg-blue-400 dark:bg-blue-500' : 'bg-pink-400 dark:bg-pink-500';
                                                        @endphp
                                                        <th class="border border-zinc-200 p-2.5 text-left font-medium text-zinc-800 dark:border-zinc-700/80 dark:text-zinc-200 min-w-[170px]">
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="text-xs font-bold text-indigo-500 dark:text-indigo-400">{{ $index + 1 }}.</span>
                                                                <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full {{ $sDot }}"></span>
                                                                <a href="{{ route('pengguna.pohon-v3', $item['sibling']->id) }}" wire:click.prevent="switchUser('{{ $item['sibling']->id }}')"
                                                                   class="text-[13px] font-semibold text-zinc-800 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-200 dark:hover:text-blue-400 truncate">
                                                                    {{ $item['sibling']->name }}
                                                                </a>
                                                                @if ($item['sibling']->wafat)
                                                                    <span class="rounded bg-red-100 px-1 py-0.5 text-[9px] font-semibold uppercase text-red-600 dark:bg-red-900/40 dark:text-red-400">Alm</span>
                                                                @endif
                                                            </div>
                                                        </th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    @foreach ($siblingsData as $item)
                                                        <td class="border border-zinc-200 p-2.5 align-top dark:border-zinc-700/80">
                                                            @if (!empty($item['nephews']))
                                                                <div class="space-y-1.5">
                                                                    <div class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Keponakan:</div>
                                                                    <div class="space-y-1.5">
                                                                        @foreach ($item['nephews'] as $nIndex => $nephewItem)
                                                                            @php
                                                                                $kDot = $nephewItem['keponakan']->jenis_kelamin == '1' ? 'bg-blue-400 dark:bg-blue-500' : 'bg-pink-400 dark:bg-pink-500';
                                                                            @endphp
                                                                            <div>
                                                                                <div class="flex items-center gap-1.5 text-[12px]">
                                                                                    <span class="text-[11px] font-bold text-zinc-400">{{ $nIndex + 1 }}.</span>
                                                                                    <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full {{ $kDot }}"></span>
                                                                                    <a href="{{ route('pengguna.pohon-v3', $nephewItem['keponakan']->id) }}" wire:click.prevent="switchUser('{{ $nephewItem['keponakan']->id }}')"
                                                                                       class="text-zinc-700 transition-colors hover:text-blue-600 hover:underline dark:text-zinc-300 dark:hover:text-blue-400 truncate">
                                                                                        {{ $nephewItem['keponakan']->name }}
                                                                                    </a>
                                                                                    @if ($nephewItem['keponakan']->wafat)
                                                                                        <span class="rounded bg-red-100 px-1 py-0.5 text-[9px] font-semibold uppercase text-red-600 dark:bg-red-900/40 dark:text-red-400">Alm</span>
                                                                                    @endif
                                                                                </div>

                                                                                @if (!empty($nephewItem['cucu']))
                                                                                    <div class="ml-4 mt-0.5 space-y-0.5 border-l border-dashed border-zinc-200 pl-2 text-[11px] text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                                                                        @foreach ($nephewItem['cucu'] as $cucuK)
                                                                                            <div>
                                                                                                <a href="{{ route('pengguna.pohon-v3', $cucuK->id) }}" wire:click.prevent="switchUser('{{ $cucuK->id }}')"
                                                                                                   class="hover:underline hover:text-blue-600 dark:hover:text-blue-400">
                                                                                                    • {{ $cucuK->name }}
                                                                                                </a>
                                                                                            </div>
                                                                                        @endforeach
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <div class="py-2 text-center text-xs text-zinc-300 dark:text-zinc-600">—</div>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="p-2 text-xs italic text-zinc-400 dark:text-zinc-500">
                                        {{ __('Belum ada data saudara') }}
                                    </div>
                                @endif
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Deceased Members (Identik dengan daftar-v2) ────────────── --}}
        @if (count($deceasedList) > 0)
            <div class="mt-6 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 print:mt-8 print:border-none print:shadow-none">

                <div class="border-b border-zinc-100 bg-gradient-to-r from-zinc-50 to-zinc-100 px-6 py-4 dark:border-zinc-800 dark:from-zinc-800/60 dark:to-zinc-800/40 print:hidden">
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-zinc-200 dark:bg-zinc-700">
                            <svg class="h-4 w-4 text-zinc-600 dark:text-zinc-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                            </svg>
                        </div>
                        <flux:heading size="md" class="text-zinc-700 dark:text-zinc-200">
                            {{ __('Deceased Members') }}
                            <span class="ml-1 text-sm font-normal text-zinc-400">({{ count($deceasedList) }})</span>
                        </flux:heading>
                    </div>
                </div>

                <div class="p-6 print:p-0 print:pt-4">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($deceasedList as $deceased)
                            <a href="{{ route('pengguna.pohon-v3', $deceased['id']) }}" wire:click.prevent="switchUser('{{ $deceased['id'] }}')"
                               class="group flex items-center gap-3 rounded-xl border border-zinc-100 bg-zinc-50/50 p-3 transition-all hover:border-zinc-200 hover:bg-white hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-800/30 dark:hover:border-zinc-700 dark:hover:bg-zinc-800 print:border-zinc-200 print:bg-transparent">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    @if($deceased['foto'])
                                        <img src="{{ asset('storage/'.$deceased['foto']) }}" alt="{{ $deceased['name'] }}" class="h-10 w-10 rounded-full object-cover" />
                                    @else
                                        <svg class="h-5 w-5 text-zinc-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM6 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM1.49 15.326a.78.78 0 0 1-.358-.442 3 3 0 0 1 4.308-3.516 6.484 6.484 0 0 0-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 0 1-2.07-.655ZM16.44 15.98a4.97 4.97 0 0 0 2.07-.654.78.78 0 0 0 .357-.442 3 3 0 0 0-4.308-3.517 6.484 6.484 0 0 1 1.907 3.96 2.32 2.32 0 0 1-.026.654ZM18 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM5.304 16.19a.844.844 0 0 1-.277-.71 5 5 0 0 1 9.947 0 .843.843 0 0 1-.277.71A6.975 6.975 0 0 1 10 18a6.974 6.974 0 0 1-4.696-1.81Z"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-zinc-800 transition-colors group-hover:text-blue-600 dark:text-zinc-200 dark:group-hover:text-blue-400 print:text-black">
                                        {{ $deceased['name'] }}
                                        <span class="text-xs font-normal text-zinc-400">
                                            {{ $deceased['jenis_kelamin'] == '1' ? '(L)' : '(P)' }}
                                        </span>
                                    </p>
                                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">
                                        Wafat: {{ !empty($deceased['tanggal_wafat']) ? \Carbon\Carbon::parse($deceased['tanggal_wafat'])->format('d M Y') : '—' }}
                                    </p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

    </div>
</section>

@push('styles')
    <style>
        /* Fullscreen styles for Main Card */
        .main-card-fullscreen:fullscreen,
        .main-card-fullscreen:-webkit-full-screen {
            background-color: #ffffff !important;
            width: 100vw !important;
            height: 100vh !important;
            max-width: 100vw !important;
            max-height: 100vh !important;
            border-radius: 0 !important;
            border: none !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow-y: auto !important;
            overflow-x: auto !important;
            box-sizing: border-box !important;
            display: flex !important;
            flex-direction: column !important;
            z-index: 99999 !important;
        }

        :is(.dark) .main-card-fullscreen:fullscreen,
        :is(.dark) .main-card-fullscreen:-webkit-full-screen,
        .dark .main-card-fullscreen:fullscreen,
        .dark .main-card-fullscreen:-webkit-full-screen {
            background-color: #18181b !important;
            color: #f4f4f5 !important;
        }

        .main-card-fullscreen:fullscreen .card-header-sticky,
        .main-card-fullscreen:-webkit-full-screen .card-header-sticky {
            position: sticky;
            top: 0;
            z-index: 30;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        :is(.dark) .main-card-fullscreen:fullscreen .card-header-sticky,
        :is(.dark) .main-card-fullscreen:-webkit-full-screen .card-header-sticky,
        .dark .main-card-fullscreen:fullscreen .card-header-sticky,
        .dark .main-card-fullscreen:-webkit-full-screen .card-header-sticky {
            background-color: #18181b;
            border-bottom: 1px solid #27272a;
        }

        @media print {
            header, nav, aside, footer, [data-flux-header], [data-flux-sidebar], .print\:hidden {
                display: none !important;
            }
            html, body, main {
                background: #ffffff !important;
                background-color: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                min-width: 100% !important;
            }
            html.dark, .dark, .dark * {
                background-color: transparent !important;
                color: #000000 !important;
                border-color: #e5e5e5 !important;
                box-shadow: none !important;
            }
            a {
                text-decoration: none !important;
                color: #000000 !important;
            }
            table, tr, td, th {
                border-color: #cbd5e1 !important;
                page-break-inside: avoid;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        function safePrintPage() {
            const wasDark = document.documentElement.classList.contains('dark');
            document.documentElement.classList.remove('dark');
            setTimeout(() => {
                window.print();
                if (wasDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }, 50);
        }
    </script>
@endpush
