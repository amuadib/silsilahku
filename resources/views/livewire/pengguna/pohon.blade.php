<?php

use Livewire\Volt\Component;
use App\Models\User;

new class extends Component {
    public User $user;

    public function mount(string $id)
    {
        $this->user = User::findOrFail($id);
    }

    private function buildNode(User $user, &$visited = [], $depth = 0)
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
                'desc' => $desc,
            ],
            'link' => [
                'href' => route('pengguna.pohon', $user->id),
                'target' => '_self',
            ],
            'HTMLclass' => $user->jenis_kelamin == '1' ? 'node-laki' : 'node-perempuan',
            'children' => [],
        ];

        // Fetch children
        $hasChildren = false;
        foreach ($pernikahans as $p) {
            foreach ($p->anak as $anak) {
                $childNode = $this->buildNode($anak, $visited, $depth + 1);
                if ($childNode) {
                    $node['children'][] = $childNode;
                    $hasChildren = true;
                }
            }
        }

        if ($hasChildren) {
            $node['collapsable'] = true;
            if ($depth > 0) {
                $node['collapsed'] = true;
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
            'node' => [
                'collapsable' => true,
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
            zoom: 1,
            isDragging: false, 
            startX: 0, 
            startY: 0, 
            scrollLeft: 0, 
            scrollTop: 0,
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
            },
            startDragging(e) {
                if (e.target.closest('.node') || e.target.closest('.collapse-switch')) return;
                this.isDragging = true;
                const pageX = e.pageX || (e.touches ? e.touches[0].pageX : 0);
                const pageY = e.pageY || (e.touches ? e.touches[0].pageY : 0);
                this.startX = pageX - this.$refs.treeWrapper.offsetLeft;
                this.startY = pageY - this.$refs.treeWrapper.offsetTop;
                this.scrollLeft = this.$refs.treeWrapper.scrollLeft;
                this.scrollTop = this.$refs.treeWrapper.scrollTop;
                this.$refs.treeWrapper.style.cursor = 'grabbing';
                this.$refs.treeWrapper.style.userSelect = 'none';
            },
            stopDragging() {
                this.isDragging = false;
                this.$refs.treeWrapper.style.cursor = 'grab';
                this.$refs.treeWrapper.style.userSelect = 'auto';
            },
            moveDragging(e) {
                if (!this.isDragging) return;
                if (e.touches) e.preventDefault(); 
                const pageX = e.pageX || (e.touches ? e.touches[0].pageX : 0);
                const pageY = e.pageY || (e.touches ? e.touches[0].pageY : 0);
                const x = pageX - this.$refs.treeWrapper.offsetLeft;
                const y = pageY - this.$refs.treeWrapper.offsetTop;
                const walkX = (x - this.startX) * 1.5;
                const walkY = (y - this.startY) * 1.5;
                this.$refs.treeWrapper.scrollLeft = this.scrollLeft - walkX;
                this.$refs.treeWrapper.scrollTop = this.scrollTop - walkY;
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
        <div class="flex items-center gap-2">
            <flux:button.group variant="outline" class="mr-2">
                <flux:button icon="plus" class="cursor-pointer" @click="zoom = Math.min(zoom + 0.1, 2)" />
                <flux:button variant="filled" class="pointer-events-none w-14! px-0! text-xs!">
                    <span x-text="Math.round(zoom * 100) + '%'"></span>
                </flux:button>
                <flux:button icon="minus" class="cursor-pointer" @click="zoom = Math.max(zoom - 0.1, 0.5)" />
            </flux:button.group>

            <flux:button id="btn-fullscreen" @click="toggleFullscreen()" color="zinc" class="cursor-pointer" variant="outline">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="mr-1 h-5 w-5 inline-block">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                </svg>
                {{ __('Full Screen') }}
            </flux:button>
            <flux:button color="blue" href="{{ route('pengguna.show', $user->id) }}" wire:navigate class="cursor-pointer" variant="primary">
                {{ __('Detail') }}
            </flux:button>
        </div>
    </div>

    <div id="tree-wrapper"
        x-ref="treeWrapper"
        @mousedown="startDragging($event)"
        @mousemove="moveDragging($event)"
        @mouseup="stopDragging()"
        @mouseleave="stopDragging()"
        @touchstart="startDragging($event)"
        @touchmove="moveDragging($event)"
        @touchend="stopDragging()"
        class="mt-4 overflow-auto rounded min-h-[600px] bg-white p-0 dark:bg-zinc-900 cursor-grab">
        <div x-ref="treeStructure" id="tree-structure" :style="'zoom: ' + zoom + '; transition: zoom 0.2s;'"></div>
    </div>
</section>

@push('styles')
    <style>
        /* Treant Core CSS (minified base) */
        .Treant {
            position: relative;
            overflow: visible !important; /* Crucial for collapsible switches */
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

        /* Collapsible toggle styles */
        .collapse-switch {
            position: absolute;
            bottom: -15px;
            left: 50%;
            margin-left: -10px;
            width: 20px;
            height: 20px;
            background-color: #fff;
            border: 1px solid #9ca3af;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            line-height: 1;
            color: #4b5563;
            transition: all 0.2s;
        }

        .collapse-switch:hover {
            background-color: #f3f4f6;
            transform: scale(1.1);
        }

        .collapsed .collapse-switch {
            background-color: #4b5563;
            color: #fff;
        }

        .collapse-switch::after {
            content: "−";
        }

        .collapsed .collapse-switch::after {
            content: "+";
        }

        .dark .collapse-switch {
            background-color: #27272a;
            border-color: #52525b;
            color: #d4d4d8;
        }

        #tree-structure {
            padding: 80px; /* Increased padding to prevent clipping at edges */
            box-sizing: border-box;
            min-width: fit-content;
            min-height: fit-content;
        }

        /* Make nodes look interactive */
        .node-laki, .node-perempuan {
            cursor: default;
        }
        
        .Treant .node.collapsable {
            cursor: pointer;
        }
    </style>
@endpush