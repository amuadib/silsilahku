<?php

use Livewire\Volt\Component;
use App\Models\User;

new class extends Component {
    public User $user;
    public array $treeData = [];
    public array $deceasedList = [];
    public int $totalMember = 0;

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
