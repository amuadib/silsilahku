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
<section class="mx-auto w-full px-6 lg:px-8 max-w-7xl">
    <div class="w-full">
        <div class="relative my-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">
                    {{ __('List of Family members') }} Bani {{ $user->name }}
                </flux:heading>
                <flux:subheading size="lg" class="mt-1">
                    {{ __('Members Count') }}: <span class="font-bold">{{ $totalMember }}</span>
                </flux:subheading>
            </div>
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <flux:button color="zinc" icon="printer" onclick="window.print()" class="cursor-pointer" variant="outline">
                    Print
                </flux:button>
                <flux:button color="zinc" href="{{ route('pengguna.pohon-v2', $user->id) }}" wire:navigate
                    class="cursor-pointer" variant="outline">
                    {{ __('Family Tree') }}
                </flux:button>
                <flux:button color="zinc" href="{{ route('pengguna.daftar-collapse', $user->id) }}" wire:navigate
                    class="cursor-pointer" variant="outline">
                    {{ __('List') }} (Collapse)
                </flux:button>
                <flux:button color="blue" href="{{ route('pengguna.show', $user->id) }}" wire:navigate
                    class="cursor-pointer" variant="primary">
                    {{ __('Detail') }}
                </flux:button>
            </div>
    </div>

    <flux:separator variant="subtle" />

    <div
        class="mt-6 overflow-hidden rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 print:shadow-none print:border-none print:p-0">
        <div class="text-zinc-800 dark:text-zinc-200 print:text-black">
            @if (isset($treeData) && !empty($treeData))
                @include('livewire.pengguna._daftar-node', ['node' => $treeData, 'prefix' => '1'])
            @endif
        </div>
    </div>

    @if (count($deceasedList) > 0)
        <div class="mt-6 overflow-hidden rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 print:shadow-none print:border-none print:p-0 print:border-t print:pt-6 print:rounded-none">
            <flux:heading size="lg" class="mb-4 text-zinc-800 dark:text-zinc-200 print:text-black">
                {{ __('Deceased Members') }} ({{ count($deceasedList) }})
            </flux:heading>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($deceasedList as $deceased)
                    <div class="flex items-center gap-3 p-3 border border-zinc-100 dark:border-zinc-800 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <flux:avatar size="sm" :src="$deceased['foto'] ? asset('storage/'.$deceased['foto']) : ($deceased['jenis_kelamin'] == '1' ? asset('img/avatar-m.jpg') : asset('img/avatar-f.jpg'))" />
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('pengguna.show', $deceased['id']) }}" wire:navigate class="text-sm font-medium text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 hover:underline truncate block">
                                {{ $deceased['name'] }}
                            </a>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Wafat: {{ !empty($deceased['tanggal_wafat']) ? \Carbon\Carbon::parse($deceased['tanggal_wafat'])->format('d M Y') : '-' }}
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
    header, nav, aside, footer {
        display: none !important;
    }
    
    body, main {
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

@push('scripts')
<script>
    window.addEventListener('beforeprint', () => {
        window.wasDark = document.documentElement.classList.contains('dark');
        document.documentElement.classList.remove('dark');
    });
    window.addEventListener('afterprint', () => {
        if (window.wasDark) document.documentElement.classList.add('dark');
    });
</script>
@endpush
