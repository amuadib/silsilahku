<?php

use Livewire\Volt\Component;
use App\Models\User;

new class extends Component {
    public User $user;
    public array $treeData = [];
    public int $totalMember = 0;

    public function mount(string $id)
    {
        $this->user = User::findOrFail($id);
        $visited = [];
        $this->treeData = $this->buildFamilyTree($this->user, $visited) ?? [];
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
            <div class="flex flex-wrap items-center gap-2">
            <flux:button color="zinc" href="{{ route('pengguna.pohon-v2', $user->id) }}" wire:navigate
                class="cursor-pointer" variant="outline">
                {{ __('Family Tree') }}
            </flux:button>
            <flux:button color="blue" href="{{ route('pengguna.show', $user->id) }}" wire:navigate
                class="cursor-pointer" variant="primary">
                {{ __('Detail') }}
            </flux:button>
        </div>
    </div>

    <flux:separator variant="subtle" />

    <div
        class="mt-6 overflow-hidden rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="text-zinc-800 dark:text-zinc-200">
            @if (isset($treeData) && !empty($treeData))
                @include('livewire.pengguna._daftar-node', ['node' => $treeData, 'prefix' => '1'])
            @endif
        </div>
    </div>
    </div>
</section>
