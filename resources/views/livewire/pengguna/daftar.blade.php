<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Pernikahan;
use App\Traits\HasToast;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use HasToast;

    public User $user;
    public ?User $targetUser = null;
    public array $treeData = [];
    public array $deceasedList = [];
    public int $totalMember = 0;

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
        $this->user = User::findOrFail($id);
        $visited = [];
        $this->treeData = $this->buildFamilyTree($this->user, $visited) ?? [];

        $this->deceasedList = User::whereIn('id', $visited)
            ->where('wafat', true)
            ->orderBy('tanggal_wafat', 'asc')
            ->orderBy('name')
            ->get(['id', 'name', 'jenis_kelamin', 'foto', 'tanggal_wafat'])
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
            $spouseId = $spouse ? $spouse->id : 'unknown_' . $p->id;

            if (!isset($marriagesMap[$spouseId])) {
                $spouseData = null;
                if ($spouse) {
                    $isNewSpouse = !in_array($spouse->id, $visited);
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

        $this->pasanganPernikahan = Pernikahan::with('suami', 'istri')->get()->mapWithKeys(fn($p) => [$p->id => "{$p->suami->name} & {$p->istri->name}"])->toArray();

        if ($this->targetUser) {
            $this->pasanganPernikahanUser = Pernikahan::where('suami_id', $this->targetUser->id)
                ->orWhere('istri_id', $this->targetUser->id)
                ->with('suami', 'istri')
                ->get()
                ->mapWithKeys(fn($p) => [$p->id => "{$p->suami->name} & {$p->istri->name}"])
                ->toArray();
        }
    }

    public function openAddHubunganModal($userId)
    {
        $this->targetUser = User::find($userId);
        $this->loadFormData();
        $this->modal('add-hubungan-modal')->show();
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
        $this->modal('add-hubungan-modal')->close();
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

        if (!$id) {
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
        if (!$this->targetUser->ortu_id) {
            $this->toastError(__('Target user does not have parents set. Please set parents first.'));
            return;
        }

        if (!$id) {
            $id = User::create([
                'name' => $this->name,
                'jenis_kelamin' => $this->jenis_kelamin,
                'manager_id' => Auth::id(),
            ])->id;
        }

        $pernikahan = Pernikahan::find($this->targetUser->ortu_id);

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
<section class="mx-auto w-full max-w-7xl px-6 lg:px-8">
    <div class="w-full">
        <div class="relative my-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl" level="1">
                    {{ __('List of Family members') }} Bani {{ $user->name }}
                </flux:heading>
                <flux:subheading size="lg" class="mt-1">
                    {{ __('Members Count') }}: <span class="font-bold">{{ $totalMember }}</span>
                </flux:subheading>
            </div>
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <flux:button color="zinc" icon="printer" onclick="window.print()" class="cursor-pointer"
                    variant="outline">
                    Print
                </flux:button>
                <flux:button color="zinc" href="{{ route('pengguna.pohon-v2', $user->id) }}" wire:navigate
                    class="cursor-pointer" variant="outline" icon="share">
                    {{ __('Family Tree') }}
                </flux:button>
                <flux:button color="zinc" href="{{ route('pengguna.daftar-collapse', $user->id) }}" wire:navigate
                    class="cursor-pointer" variant="outline" icon="users">
                    {{ __('List') }} (Collapse)
                </flux:button>
                @auth
                    <flux:button color="cyan" href="{{ route('pengguna.hubungan') }}" wire:navigate class="cursor-pointer"
                        variant="outline" icon="arrows-right-left">
                        {{ __('Find Relationship') }}
                    </flux:button>
                @endauth
                <flux:button color="blue" href="{{ route('pengguna.show', $user->id) }}" wire:navigate
                    class="cursor-pointer" variant="primary" icon="eye">
                    {{ __('Detail') }}
                </flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <div
            class="mt-6 overflow-hidden rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 print:border-none print:p-0 print:shadow-none">
            <div class="text-zinc-800 dark:text-zinc-200 print:text-black">
                @if (isset($treeData) && !empty($treeData))
                    @include('livewire.pengguna._daftar-node', ['node' => $treeData, 'prefix' => '1'])
                @endif
            </div>
        </div>

        @if (count($deceasedList) > 0)
            <div
                class="mt-6 overflow-hidden rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 print:rounded-none print:border-t print:border-none print:p-0 print:pt-6 print:shadow-none">
                <flux:heading size="lg" class="mb-4 text-zinc-800 dark:text-zinc-200 print:text-black">
                    {{ __('Deceased Members') }} ({{ count($deceasedList) }})
                </flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($deceasedList as $deceased)
                        <div
                            class="flex items-center gap-3 rounded-lg border border-zinc-100 p-3 transition-colors hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50">
                            <flux:avatar size="sm"
                                :src="$deceased['foto'] ? asset('storage/'.$deceased['foto']) : ($deceased['jenis_kelamin'] ==
                                    '1' ? asset('img/avatar-m.jpg') : asset('img/avatar-f.jpg'))" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('pengguna.show', $deceased['id']) }}" wire:navigate
                                    class="block truncate text-sm font-medium text-zinc-900 hover:text-blue-600 hover:underline dark:text-zinc-100 dark:hover:text-blue-400">
                                    {{ $deceased['name'] }}
                                </a>
                                <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    Wafat:
                                    {{ !empty($deceased['tanggal_wafat']) ? \Carbon\Carbon::parse($deceased['tanggal_wafat'])->format('d M Y') : '-' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <flux:modal name="add-hubungan-modal" class="min-w-[500px]">
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
                                'ayah' => __('Father'),
                                'ibu' => __('Mother'),
                                'saudara' => __('Sibling'),
                                'anak' => __('Child'),
                                'suami' => __('Husband'),
                                'istri' => __('Wife'),
                            ];
                            // Filter based on gender
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
                                // If target user has parents, we might want to default to them
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
        @media print {

            /* Hide specific layout elements globally */
            header,
            nav,
            aside,
            footer {
                display: none !important;
            }

            body,
            main {
                background: white !important;
                color: black !important;
                margin: 0 !important;
                padding: 0 !important;
                min-width: 100% !important;
            }

            a {
                text-decoration: none !important;
                color: black !important;
            }

            /* Prevent page breaks inside items */
            .flex.items-start {
                page-break-inside: avoid;
            }
        }
    </style>
@endpush
