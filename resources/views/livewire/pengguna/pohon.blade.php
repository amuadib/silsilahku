<?php

use Livewire\Volt\Component;
use App\Models\User;

new class extends Component {
    public User $user;

    public function mount(string $id)
    {
        $this->user = User::findOrFail($id);
    }

    private function buildNode(User $user, &$visited = [])
    {
        // Prevent infinite recursion if data is corrupted
        if (in_array($user->id, $visited)) {
            return null;
        }
        $visited[] = $user->id;

        $spouses = [];
        $pernikahans = $user
            ->pernikahan()
            ->with(['istri', 'suami', 'anak'])
            ->get();
        foreach ($pernikahans as $p) {
            if ($user->jenis_kelamin == '1' && $p->istri) {
                $wafat = $p->istri->wafat == true ? '(Alm)' : '';
                $spouses[] = $p->istri->name . ' ' . $wafat;
                if (!in_array($p->istri->id, $visited)) {
                    $visited[] = $p->istri->id;
                }
            } elseif ($user->jenis_kelamin != '1' && $p->suami) {
                $wafat = $p->suami->wafat == true ? '(Alm)' : '';
                $spouses[] = $p->suami->name . ' ' . $wafat;
                if (!in_array($p->suami->id, $visited)) {
                    $visited[] = $p->suami->id;
                }
            }
        }

        $spousesStr = empty($spouses) ? '' : implode(', ', $spouses);
        $desc = $spousesStr ? ($user->jenis_kelamin == '1' ? 'Istri: ' : 'Suami: ') . $spousesStr : '';
        $wafat = $user->wafat == true ? '(Alm)' : '';
        $node = [
            'text' => [
                'name' => $user->name . ' ' . $wafat,
                // 'title' => $user->jenis_kelamin == '1' ? 'Laki-laki' : 'Perempuan',
                'desc' => $desc,
            ],
            'link' => [
                'href' => route('pengguna.pohon', $user->id),
                'target' => '_self', // or _blank if they want it in a new tab, but usually self is better
            ],
            // HTMLclass will map to our CSS
            'HTMLclass' => $user->jenis_kelamin == '1' ? 'node-laki' : 'node-perempuan',
            'children' => [],
        ];

        // Fetch children
        foreach ($pernikahans as $p) {
            foreach ($p->anak as $anak) {
                $childNode = $this->buildNode($anak, $visited);
                if ($childNode) {
                    $node['children'][] = $childNode;
                }
            }
        }

        return $node;
    }

    public function with(): array
    {
        $config = [
            'container' => '#tree-structure',
            'levelSeparation' => 60,
            'siblingSeparation' => 45,
            'subTeeSeparation' => 45,
            'connectors' => [
                'type' => 'curve',
                'style' => [
                    'stroke-width' => 2,
                    'stroke' => '#9ca3af',
                ],
            ],
        ];

        $visited = [];
        $rootNode = $this->buildNode($this->user, $visited);

        return [
            'treeData' => [
                'chart' => $config,
                'nodeStructure' => $rootNode,
            ],
            'totalMember' => count($visited),
        ];
    }
};
?>
<section x-data="{ 
            initTreant(retryCount = 0) {
                if (typeof Treant !== 'undefined') {
                    // Clear existing content to prevent duplicates or lingering nodes
                    this.$refs.treeStructure.innerHTML = '';
                    new Treant(@js($treeData));
                } else if (retryCount < 10) {
                    // If Treant is not ready, retry in 100ms
                    setTimeout(() => this.initTreant(retryCount + 1), 100);
                }
            },
            toggleFullscreen() {
                let elem = this.$refs.treeWrapper;
                if (!document.fullscreenElement) {
                    if (elem.requestFullscreen) { elem.requestFullscreen(); }
                    else if (elem.webkitRequestFullscreen) { elem.webkitRequestFullscreen(); }
                    else if (elem.msRequestFullscreen) { elem.msRequestFullscreen(); }
                } else {
                    if (document.exitFullscreen) { document.exitFullscreen(); }
                }
            }
        }"
    x-init="initTreant();"
    x-on:livewire:navigated.window="initTreant()"
    class="item-center mx-auto w-full px-6 lg:px-8 [:where(&)]:max-w-7xl">
    <div class="relative my-6 flex w-full items-center justify-between border-b pb-4">
        <div>
            <flux:heading size="xl" level="1">
                {{ __('Family Tree') }} Bani {{ $user->name }}
            </flux:heading>
            <flux:subheading size="lg" class="mt-1">
                {{ __('Members Count') }}: <span class="font-bold">{{ $totalMember }}</span>
            </flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button id="btn-fullscreen" @click="toggleFullscreen()" color="zinc" class="cursor-pointer" variant="outline">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="mr-1 inline-block h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                </svg>
                {{ __('Full Screen') }}
            </flux:button>
            <a href="{{ route('pengguna.show', $user->id) }}">
                <flux:button color="blue" class="cursor-pointer" variant="primary">
                    {{ __('Detail') }}
                </flux:button>
            </a>
        </div>
    </div>

    <div id="tree-wrapper"
        x-ref="treeWrapper"
        class="mt-4 min-h-[600px] overflow-x-auto rounded border bg-white p-0 shadow-sm dark:bg-zinc-900">
        <div x-ref="treeStructure" id="tree-structure" style="width: 100%; height: 100%;"></div>
    </div>
</section>

@push('styles')
    <style>
        /* Treant Core CSS (minified base) */
        .Treant {
            position: relative;
            overflow: hidden;
            padding: 0 !important;
        }

        .Treant>.node,
        .Treant>.pseudo {
            position: absolute;
            display: block;
            visibility: hidden;
        }

        .Treant.Treant-loaded .node,
        .Treant.Treant-loaded .pseudo {
            visibility: visible;
        }

        .Treant>.pseudo {
            width: 0;
            height: 0;
            border: none;
            padding: 0;
        }

        .node-laki,
        .node-perempuan {
            padding: 12px;
            border-radius: 8px;
            width: 160px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            font-family: inherit;
        }

        .node-laki {
            background-color: #e0f2fe;
            border: 2px solid #7dd3fc;
            color: #0c4a6e;
        }

        .node-perempuan {
            background-color: #fce7f3;
            border: 2px solid #f9a8d4;
            color: #831843;
        }

        .Treant .node-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            white-space: normal;
            word-wrap: break-word;
        }

        .Treant .node-desc {
            font-size: 11px;
            opacity: 0.9;
            margin-top: 4px;
            font-style: italic;
        }

        .dark .node-laki {
            background-color: #0c4a6e;
            border-color: #0369a1;
            color: #e0f2fe;
        }

        .dark .node-perempuan {
            background-color: #831843;
            border-color: #be185d;
            color: #fce7f3;
        }

        /* Fullscreen styles */
        #tree-wrapper:fullscreen {
            width: 100vw !important;
            height: 100vh !important;
            background-color: white !important;
            padding: 40px !important;
        }

        .dark #tree-wrapper:fullscreen {
            background-color: #18181b !important;
        }
    </style>
@endpush