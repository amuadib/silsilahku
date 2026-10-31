<?php

use App\Models\Pernikahan;
use App\Models\User;
use App\Traits\HasToast;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    use HasToast;

    public User $user;

    public ?User $targetUser = null;

    public array $treeData = [];

    public array $deceasedList = [];

    public int $totalMember = 0;

    public ?User $nodeAyah = null;

    public ?User $nodeIbu = null;

    public array $nodeAnak = [];

    // Form properties
    public ?string $activeForm = null;

    public $userLakiLaki;

    public $userPerempuan;

    public $userFromSelection;

    public $userFromInput;

    public $userWithoutParentList;

    public $name;

    public $jenis_kelamin;

    public $pasanganPernikahan;

    public $pasanganPernikahanUser;

    public $ortu_id;

    public $anak_ke;

    public $userExist = false;

    public function mount(string $id)
    {
        $this->loadUserData($id);
    }

    public function switchUser(string $id, bool $pushHistory = true): void
    {
        $this->loadUserData($id);

        if ($pushHistory) {
            $url = route('pengguna.daftar-v2', $id);
            $safeName = addslashes($this->user->name);
            $this->js("window.history.pushState({ userId: '{$id}' }, '', '{$url}'); document.title = 'Bani {$safeName}';");
        }

        $this->dispatch('user-switched');
    }

    private function loadUserData(string $id): void
    {
        $this->user = User::findOrFail($id);
        $this->totalMember = 0;
        $visited = [];
        $this->treeData = $this->buildFamilyTree($this->user, $visited) ?? [];

        $this->deceasedList = !empty($visited)
            ? User::whereIn('id', $visited)
                ->where('wafat', true)
                ->orderBy('tanggal_wafat', 'asc')
                ->orderBy('name')
                ->get(['id', 'name', 'jenis_kelamin', 'foto', 'tanggal_wafat'])
                ->toArray()
            : [];

        // Load node diatasnya (Ayah & Ibu)
        $this->nodeAyah = $this->user->ayah_id ? User::find($this->user->ayah_id) : null;
        $this->nodeIbu = $this->user->ibu_id ? User::find($this->user->ibu_id) : null;
        if ($this->user->ortu_id && (! $this->nodeAyah || ! $this->nodeIbu)) {
            $ortuPernikahan = Pernikahan::with(['suami', 'istri'])->find($this->user->ortu_id);
            if ($ortuPernikahan) {
                if (! $this->nodeAyah && $ortuPernikahan->suami) {
                    $this->nodeAyah = $ortuPernikahan->suami;
                }
                if (! $this->nodeIbu && $ortuPernikahan->istri) {
                    $this->nodeIbu = $ortuPernikahan->istri;
                }
            }
        }

        // Load node dibawahnya (Anak-anak langsung)
        $pernikahanIds = Pernikahan::where('suami_id', $this->user->id)
            ->orWhere('istri_id', $this->user->id)
            ->pluck('id')
            ->all();

        $this->nodeAnak = User::where(function ($q) use ($pernikahanIds) {
                $q->where('ayah_id', $this->user->id)
                  ->orWhere('ibu_id', $this->user->id);
                if (! empty($pernikahanIds)) {
                    $q->orWhereIn('ortu_id', $pernikahanIds);
                }
            })
            ->where('id', '!=', $this->user->id)
            ->orderBy('anak_ke')
            ->orderBy('name')
            ->get(['id', 'name', 'jenis_kelamin', 'wafat'])
            ->toArray();
    }

    private function buildFamilyTree(User $user, array &$visited): ?array
    {
        if (in_array($user->id, $visited)) {
            return null;
        }
        $visited[] = $user->id;
        $this->totalMember++;

        $marriagesMap = [];
        $pernikahans = $user
            ->pernikahan()
            ->with(['istri', 'suami', 'anak'])
            ->get();

        foreach ($pernikahans as $p) {
            $spouse = $user->jenis_kelamin == '1' ? $p->istri : $p->suami;
            $spouseId = $spouse ? $spouse->id : 'unknown_'.$p->id;

            if (! isset($marriagesMap[$spouseId])) {
                $spouseData = null;
                if ($spouse) {
                    $isNewSpouse = ! in_array($spouse->id, $visited);
                    $spouseData = [
                        'id' => $spouse->id,
                        'name' => $spouse->name,
                        'wafat' => $spouse->wafat,
                        'jenis_kelamin' => $spouse->jenis_kelamin,
                    ];

                    if ($isNewSpouse) {
                        $visited[] = $spouse->id;
                        $this->totalMember++;
                    }
                }

                $marriagesMap[$spouseId] = [
                    'spouse' => $spouseData,
                    'children' => [],
                ];
            }

            foreach ($p->anak as $anak) {
                $childTree = $this->buildFamilyTree($anak, $visited);
                if ($childTree) {
                    $marriagesMap[$spouseId]['children'][] = $childTree;
                }
            }
        }

        // Filter out completely empty marriage records
        $marriages = collect($marriagesMap)
            ->filter(function ($m) {
                return $m['spouse'] !== null || count($m['children']) > 0;
            })
            ->values()
            ->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'wafat' => $user->wafat,
            'jenis_kelamin' => $user->jenis_kelamin,
            'marriages' => $marriages,
        ];
    }

    public function loadFormData()
    {
        $this->userLakiLaki = User::where('jenis_kelamin', '1')->orderBy('name')->get();
        $this->userPerempuan = User::where('jenis_kelamin', '2')->orderBy('name')->get();
        $this->userWithoutParentList = User::whereNull('ortu_id')->orderBy('name')->get();

        $this->pasanganPernikahan = Pernikahan::with('suami', 'istri')->get()->mapWithKeys(fn ($p) => [$p->id => "{$p->suami->name} & {$p->istri->name}"])->toArray();

        if ($this->targetUser) {
            $this->pasanganPernikahanUser = Pernikahan::where('suami_id', $this->targetUser->id)
                ->orWhere('istri_id', $this->targetUser->id)
                ->with('suami', 'istri')
                ->get()
                ->mapWithKeys(fn ($p) => [$p->id => "{$p->suami->name} & {$p->istri->name}"])
                ->toArray();
        }
    }

    public function openAddHubunganModal($userId)
    {
        $this->targetUser = User::find($userId);
        $this->loadFormData();
        $this->modal('add-hubungan-modal-v2')->show();
    }

    public function setHubungan($hubungan)
    {
        $this->activeForm = $hubungan;
    }

    public function save()
    {
        $this->authorize('update', $this->targetUser);

        $id = $this->resolveTargetUserId();

        match ($this->activeForm) {
            'ayah' => $this->targetUser->update(['ayah_id' => $id]),
            'ibu' => $this->targetUser->update(['ibu_id' => $id]),
            'suami' => Pernikahan::create(['suami_id' => $id, 'istri_id' => $this->targetUser->id, 'manager_id' => Auth::id()]),
            'istri' => Pernikahan::create(['suami_id' => $this->targetUser->id, 'istri_id' => $id, 'manager_id' => Auth::id()]),
            'orangtua' => $this->targetUser->update(['ortu_id' => $this->ortu_id]),
            'anak' => $this->handleSaveAnak($id),
            'saudara' => $this->handleSaveSaudara($id),
            default => null,
        };

        $this->resetAll();
        $this->modal('add-hubungan-modal-v2')->close();
        $this->toastSuccess(__('Data saved successfully.'));

        // Refresh tree data
        $visited = [];
        $this->totalMember = 0;
        $this->treeData = $this->buildFamilyTree($this->user, $visited) ?? [];
    }

    protected function resolveTargetUserId()
    {
        if ($this->userFromSelection) {
            return $this->userFromSelection;
        }

        if ($this->userFromInput) {
            return User::create([
                'name' => $this->userFromInput,
                'jenis_kelamin' => in_array($this->activeForm, ['ayah', 'suami']) ? '1' : '2',
                'manager_id' => Auth::id(),
            ])->id;
        }

        return null;
    }

    protected function handleSaveAnak($id)
    {
        $pernikahan = Pernikahan::findOrFail($this->ortu_id);

        if (! $id) {
            $id = User::create([
                'name' => $this->name,
                'jenis_kelamin' => $this->jenis_kelamin,
                'manager_id' => Auth::id(),
            ])->id;
        }

        User::find($id)->update([
            'ortu_id' => $this->ortu_id,
            'ayah_id' => $pernikahan->suami_id,
            'ibu_id' => $pernikahan->istri_id,
            'anak_ke' => $this->anak_ke,
        ]);
    }

    protected function handleSaveSaudara($id)
    {
        if (! $this->targetUser->ortu_id) {
            $this->toastError(__('Target user does not have parents set. Please set parents first.'));

            return;
        }

        if (! $id) {
            $id = User::create([
                'name' => $this->name,
                'jenis_kelamin' => $this->jenis_kelamin,
                'manager_id' => Auth::id(),
            ])->id;
        }

        User::find($id)->update([
            'ortu_id' => $this->targetUser->ortu_id,
            'ayah_id' => $this->targetUser->ayah_id,
            'ibu_id' => $this->targetUser->ibu_id,
            'anak_ke' => $this->anak_ke,
        ]);
    }

    private function resetAll()
    {
        $this->reset(['activeForm', 'userFromSelection', 'userFromInput', 'name', 'jenis_kelamin', 'anak_ke', 'ortu_id', 'userExist']);
    }
};
?>
<section class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="w-full">

        {{-- ── Page Header ────────────────────────────────────────────── --}}
        <div class="relative my-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div>
                <div class="flex items-center gap-2">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-sm">
                        <svg class="h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <div>
                        <flux:heading size="xl" level="1">
                            Bani {{ $user->name }}
                        </flux:heading>
                        <div class="mt-0.5 flex items-center gap-2">
                            <flux:subheading size="lg">{{ __('List of Family members') }}</flux:subheading>
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
                @auth
                <flux:button color="zinc" icon="printer" onclick="safePrintPage()" class="cursor-pointer" variant="outline" size="sm">
                    Print
                </flux:button>
                @endauth
                <flux:button color="zinc" href="{{ route('pengguna.bagan', $user->id) }}" wire:navigate class="cursor-pointer" variant="outline" icon="table-cells" size="sm">
                    {{ __('Bagan Keluarga') }}
                </flux:button>
                {{-- <flux:button color="zinc" href="{{ route('pengguna.pohon-v2', $user->id) }}" wire:navigate class="cursor-pointer" variant="outline" icon="share" size="sm">
                    {{ __('Family Tree') }}
                </flux:button>
                <flux:button color="zinc" href="{{ route('pengguna.daftar-collapse', $user->id) }}" wire:navigate class="cursor-pointer" variant="outline" icon="users" size="sm">
                    {{ __('Collapsible') }}
                </flux:button> --}}
                @auth
                    <flux:button color="cyan" href="{{ route('pengguna.hubungan') }}" wire:navigate class="cursor-pointer" variant="outline" icon="arrows-right-left" size="sm">
                        {{ __('Find Relationship') }}
                    </flux:button>
                <flux:button color="blue" href="{{ route('pengguna.show', $user->id) }}" wire:navigate class="cursor-pointer" variant="primary" icon="eye" size="sm">
                    {{ __('Detail') }}
                </flux:button>
                @endauth
            </div>
        </div>

        <flux:separator variant="subtle" />

        {{-- ── Legend ──────────────────────────────────────────────────── --}}
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
        </div>

        {{-- ── Family List Card ────────────────────────────────────────── --}}
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
                    const match = window.location.pathname.match(/\/pengguna\/daftar-v2\/([^\/]+)/);
                    if (match && match[1]) {
                        $wire.switchUser(match[1], false);
                    }
                });
             "
             @user-switched.window="$el.scrollTop = 0"
             class="main-card-fullscreen overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 print:border-none print:shadow-none">

            {{-- Card header --}}
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
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">Silsilah Keluarga {{ $user->name }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Nomor urut mengikuti garis keturunan</p>
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
            @if ($nodeAyah || $nodeIbu || !empty($nodeAnak))
                <div class="border-b border-zinc-100 bg-zinc-50/70 px-4 py-2.5 sm:px-6 dark:border-zinc-800 dark:bg-zinc-800/40 print:hidden">
                    <div class="flex flex-col gap-2">

                        {{-- Node Diatasnya (Ayah / Ibu) --}}
                        @if ($nodeAyah || $nodeIbu)
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-500 dark:text-zinc-400">
                                    <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                                    </svg>
                                    <span>{{ __('Ke Orang Tua') }}:</span>
                                </span>

                                @if ($nodeAyah)
                                    <a href="{{ route('pengguna.daftar-v2', $nodeAyah->id) }}"
                                       wire:click.prevent="switchUser('{{ $nodeAyah->id }}')"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200/90 bg-white px-2.5 py-1 text-xs font-medium text-blue-700 shadow-2xs transition-all hover:border-blue-400 hover:bg-blue-50 dark:border-blue-800/70 dark:bg-zinc-800 dark:text-blue-300 dark:hover:bg-blue-950/40">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                        <span>{{ __('Ayah') }}: <strong>{{ $nodeAyah->name }}</strong></span>
                                        @if ($nodeAyah->wafat)
                                            <span class="rounded bg-red-100 px-1 py-0.5 text-[9px] font-semibold text-red-600 dark:bg-red-900/50 dark:text-red-400">Alm</span>
                                        @endif
                                    </a>
                                @endif

                                @if ($nodeIbu)
                                    <a href="{{ route('pengguna.daftar-v2', $nodeIbu->id) }}"
                                       wire:click.prevent="switchUser('{{ $nodeIbu->id }}')"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-pink-200/90 bg-white px-2.5 py-1 text-xs font-medium text-pink-700 shadow-2xs transition-all hover:border-pink-400 hover:bg-pink-50 dark:border-pink-800/70 dark:bg-zinc-800 dark:text-pink-300 dark:hover:bg-pink-950/40">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-pink-500"></span>
                                        <span>{{ __('Ibu') }}: <strong>{{ $nodeIbu->name }}</strong></span>
                                        @if ($nodeIbu->wafat)
                                            <span class="rounded bg-red-100 px-1 py-0.5 text-[9px] font-semibold text-red-600 dark:bg-red-900/50 dark:text-red-400">Alm</span>
                                        @endif
                                    </a>
                                @endif
                            </div>
                        @endif

                        {{-- Node Dibawahnya (Anak-anak) --}}
                        @if (!empty($nodeAnak))
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="inline-flex items-center gap-1 font-semibold text-zinc-500 dark:text-zinc-400">
                                    <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                                    </svg>
                                    <span>{{ __('Ke Anak') }} ({{ count($nodeAnak) }}):</span>
                                </span>

                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach ($nodeAnak as $index => $anak)
                                        @php
                                            $aDot = $anak['jenis_kelamin'] == '1' ? 'bg-blue-400 dark:bg-blue-500' : 'bg-pink-400 dark:bg-pink-500';
                                        @endphp
                                        <a href="{{ route('pengguna.daftar-v2', $anak['id']) }}"
                                           wire:click.prevent="switchUser('{{ $anak['id'] }}')"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200/90 bg-white px-2 py-0.5 text-zinc-700 shadow-2xs transition-all hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-600 dark:border-zinc-700/80 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:border-indigo-700 dark:hover:bg-zinc-800/80 dark:hover:text-indigo-300">
                                            <span class="text-[10px] font-bold text-zinc-400">{{ $index + 1 }}.</span>
                                            <span class="inline-block h-1.5 w-1.5 rounded-full {{ $aDot }}"></span>
                                            <span class="font-medium">{{ $anak['name'] }}</span>
                                            @if (!empty($anak['wafat']))
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

            {{-- Tree Content --}}
            <div class="flex-1 overflow-x-auto px-4 py-5 sm:px-6 print:p-0"
                 wire:loading.class="opacity-60 transition-opacity duration-200 pointer-events-none">
                <div class="text-zinc-800 dark:text-zinc-200 print:text-black">
                    @if (isset($treeData) && !empty($treeData))
                        @include('livewire.pengguna._daftar-v2-node', ['node' => $treeData, 'prefix' => '1'])
                    @else
                        <div class="flex flex-col items-center gap-3 py-16 text-center">
                            <svg class="h-12 w-12 text-zinc-300 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <p class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('No family data found.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Deceased Members ────────────────────────────────────────── --}}
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
                            <a href="{{ route('pengguna.daftar-v2', $deceased['id']) }}" wire:click.prevent="switchUser('{{ $deceased['id'] }}')"
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

        {{-- ── Modal Tambah Hubungan ──────────────────────────────────── --}}
        <flux:modal name="add-hubungan-modal-v2" class="min-w-[500px]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Tambah Hubungan Keluarga</flux:heading>
                    <flux:subheading>Menambahkan hubungan untuk <span class="font-bold text-zinc-800 dark:text-white">{{ $targetUser?->name }}</span></flux:subheading>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">{{ __('Relationship Type') }}</label>
                    <div class="flex flex-wrap gap-2">
                        @php
                            $hubungans = [
                                'ayah'    => __('Father'),
                                'ibu'     => __('Mother'),
                                'saudara' => __('Sibling'),
                                'anak'    => __('Child'),
                                'suami'   => __('Husband'),
                                'istri'   => __('Wife'),
                            ];
                            if ($targetUser?->jenis_kelamin == '1') unset($hubungans['suami']);
                            if ($targetUser?->jenis_kelamin == '2') unset($hubungans['istri']);
                        @endphp

                        @foreach($hubungans as $key => $label)
                            <flux:button
                                size="sm"
                                wire:click="setHubungan('{{ $key }}')"
                                :variant="$activeForm === $key ? 'primary' : 'outline'"
                                class="cursor-pointer"
                            >
                                {{ $label }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>

                @if ($activeForm)
                    <div class="mt-4 border-t pt-4 dark:border-zinc-700" wire:key="hubungan-form-{{ $activeForm }}">
                        @php
                            $userListToPass = [];
                            if ($activeForm === 'ayah' || $activeForm === 'suami') {
                                $userListToPass = $userLakiLaki;
                            } elseif ($activeForm === 'ibu' || $activeForm === 'istri') {
                                $userListToPass = $userPerempuan;
                            } elseif ($activeForm === 'anak') {
                                $userListToPass = $pasanganPernikahanUser;
                            } elseif ($activeForm === 'saudara') {
                                $userListToPass = $pasanganPernikahan;
                                if ($targetUser->ortu_id && !$ortu_id) {
                                    $this->ortu_id = $targetUser->ortu_id;
                                }
                            }
                        @endphp
                        @include('livewire.pengguna.form', ['userList' => $userListToPass])
                    </div>
                @endif
            </div>
        </flux:modal>

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
            .group {
                break-inside: avoid;
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

