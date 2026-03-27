<?php

use Livewire\Volt\Component;
use App\Models\User;

new class extends Component {
    public User $user;

    public function mount(string $id)
    {
        $this->user = User::findOrFail($id);
    }

    private function getFamilyData(User $rootUser)
    {
        $nodesMap = [];
        $visited = [];
        $queue = [
            [
                'user' => $rootUser,
                'father_id' => null,
                'mother_id' => null,
                'force_gender' => null,
            ],
        ];

        while (count($queue) > 0) {
            $current = array_shift($queue);
            $user = $current['user'];

            if (in_array($user->id, $visited)) {
                continue;
            }
            $visited[] = $user->id;

            // Strict gender assignment preventing f3 "two mothers" errors
            $gender = $current['force_gender'] ?? ($user->jenis_kelamin == '1' ? 'M' : 'F');
            $wafat = $user->wafat ? ' (Alm)' : '';

            $node = [
                'id' => (string) $user->id,
                'data' => [
                    'label' => $user->name . $wafat,
                    'gender' => $gender,
                    'url' => route('pengguna.show', $user->id),
                    'foto_url' => $user->foto ? asset('storage/' . $user->foto) : null,
                ],
                'rels' => [
                    'spouses' => [],
                    'children' => [],
                ],
            ];

            if ($current['father_id']) {
                $node['rels']['father'] = (string) $current['father_id'];
                if (isset($nodesMap[$current['father_id']])) {
                    $nodesMap[$current['father_id']]['rels']['children'][] = (string) $user->id;
                }
            }
            if ($current['mother_id']) {
                $node['rels']['mother'] = (string) $current['mother_id'];
                if (isset($nodesMap[$current['mother_id']])) {
                    $nodesMap[$current['mother_id']]['rels']['children'][] = (string) $user->id;
                }
            }

            $pernikahans = $user
                ->pernikahan()
                ->with(['istri', 'suami', 'anak'])
                ->get();

            foreach ($pernikahans as $p) {
                $spouse = $user->jenis_kelamin == '1' ? $p->istri : $p->suami;

                if ($spouse) {
                    $node['rels']['spouses'][] = (string) $spouse->id;
                    $forced_spouse_gender = $user->jenis_kelamin == '1' ? 'F' : 'M';

                    if (!in_array($spouse->id, $visited)) {
                        $queue[] = [
                            'user' => $spouse,
                            'father_id' => null,
                            'mother_id' => null,
                            'force_gender' => $forced_spouse_gender,
                        ];
                    }

                    // Strict biological routing for children
                    $father_id = $user->jenis_kelamin == '1' ? $user->id : $spouse->id;
                    $mother_id = $user->jenis_kelamin == '1' ? $spouse->id : $user->id;

                    foreach ($p->anak as $anak) {
                        if (!in_array($anak->id, $visited)) {
                            // First pair that processes this child assigns it
                            $queue[] = [
                                'user' => $anak,
                                'father_id' => $father_id,
                                'mother_id' => $mother_id,
                                'force_gender' => null,
                            ];
                        }
                    }
                } else {
                    // Marriage registered but spouse deleted or null
                    $father_id = $user->jenis_kelamin == '1' ? $user->id : null;
                    $mother_id = $user->jenis_kelamin == '1' ? null : $user->id;

                    foreach ($p->anak as $anak) {
                        if (!in_array($anak->id, $visited)) {
                            $queue[] = [
                                'user' => $anak,
                                'father_id' => $father_id,
                                'mother_id' => $mother_id,
                                'force_gender' => null,
                            ];
                        }
                    }
                }
            }

            $node['rels']['spouses'] = array_values(array_unique($node['rels']['spouses']));
            $nodesMap[$user->id] = $node;
        }

        // Clean up empty rels arrays to save space and match f3 expectations
        foreach ($nodesMap as &$mapNode) {
            $mapNode['rels']['children'] = array_values(array_unique($mapNode['rels']['children']));
            if (empty($mapNode['rels']['children'])) {
                unset($mapNode['rels']['children']);
            }
            if (empty($mapNode['rels']['spouses'])) {
                unset($mapNode['rels']['spouses']);
            }
        }

        return array_values($nodesMap);
    }

    public function with(): array
    {
        $data = $this->getFamilyData($this->user);
        return [
            'treeData' => $data,
            'totalMember' => count($data),
        ];
    }
};
?>
<section class="item-center mx-auto w-full px-6 lg:px-8 [:where(&)]:max-w-7xl">
    <div class="relative my-6 flex w-full items-center justify-between border-b pb-4">
        <div>
            <flux:heading size="xl" level="1">
                {{ __('Family Tree') }} Bani {{ $user->name }} (V2)
            </flux:heading>
            <flux:subheading size="lg" class="mt-1">
                {{ __('Members Count') }}: <span class="font-bold">{{ $totalMember }}</span>
            </flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button id="btn-fullscreen" onclick="toggleFullscreen()" color="zinc" class="cursor-pointer"
                variant="outline">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="mr-1 inline-block h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                </svg>
                {{ __('Full Screen') }}
            </flux:button>
            <flux:button color="zinc" href="{{ route('pengguna.pohon', $user->id) }}" wire:navigate
                class="cursor-pointer" variant="outline">
                {{ __('V1') }}
            </flux:button>
            <flux:button color="blue" href="{{ route('pengguna.show', $user->id) }}" wire:navigate
                class="cursor-pointer" variant="primary">
                {{ __('Detail') }}
            </flux:button>
        </div>
    </div>

    <div id="tree-wrapper"
        class="relative mt-4 h-[75vh] min-h-[700px] w-full overflow-hidden rounded bg-white p-0 dark:bg-zinc-900">
        <div id="FamilyChart" class="absolute inset-0 h-full w-full"></div>
    </div>
</section>

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/family-chart@0.9.0/dist/styles/family-chart.css">
    <style>
        /* Fullscreen styles */
        #tree-wrapper {
            width: 100%;
            height: 75vh;
            min-height: 700px;
        }

        #tree-wrapper:fullscreen {
            width: 100vw !important;
            height: 100vh !important;
            background-color: white !important;
        }

        .dark #tree-wrapper:fullscreen {
            background-color: #18181b !important;
        }

        /* Essential SVG line overrides for Tailwind Preflight capturing native DOM elements */
        path.link,
        .f3-link {
            fill: none !important;
            stroke: #cbd5e1 !important;
            /* Tailwind slate-300 */
            stroke-width: 1.5px !important;
        }

        .dark path.link,
        .dark .f3-link {
            stroke: #52525b !important;
            /* Tailwind zinc-600 */
        }

        /* Force-hide ALL native SVG node avatars/shapes drawn by f3 behind/above our HTML card */
        .f3-widget-card path,
        .f3-widget-card rect,
        .f3-widget-card circle,
        g.card>path,
        g.card>rect,
        g.card>circle,
        g.node>path,
        g.node>rect,
        g.node>circle {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }

        /* Protect our inner SVG avatar inside the HTML layout! */
        foreignObject path,
        foreignObject rect,
        foreignObject circle {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        /* Destroy the native HTML wrapper base64 avatars and native injection nodes by family-chart.css */
        .card,
        .card-male,
        .card-female,
        .card-inner,
        .card_cont {
            background-image: none !important;
            background-color: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        /* Specifically eradicate the person-icon <div> that family-chart dynamically injects adjacent to our HTML wrapper */
        .person-icon {
            display: none !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://unpkg.com/d3@7.9.0/dist/d3.min.js"></script>
    <script src="https://unpkg.com/family-chart@0.9.0/dist/family-chart.min.js"></script>
    <script>
        document.addEventListener('livewire:navigated', initChart);

        function initChart() {
            const container = document.getElementById('FamilyChart');
            if (!container) return;

            if (typeof d3 === 'undefined' || typeof f3 === 'undefined') {
                setTimeout(initChart, 100);
                return;
            }

            // Livewire 'wire:navigate' restores the exact HTML from cache when navigating back, 
            // but the f3 SVG nodes lose their internal Javascript drag/pan/zoom event listeners!
            // We MUST clear the frozen DOM elements and let createChart rebuild the interactive SVG!
            container.innerHTML = '';

            let data = @js($treeData);

            const chart = f3.createChart('#FamilyChart', data);
            chart.setTransitionTime(1000);
            chart.setCardYSpacing(120);

            // INCREASED X SPACING TO PREVENT OVERLAPPING (220 width requires at least 260 spacing)
            chart.setCardXSpacing(260);

            // Silence the default SVG card renderer so it doesn't overlap the CardHtml
            if (chart.setCardSvg) {
                chart.setCardSvg().setCardDisplay(() => '');
            }

            // Using CardHtml with tailored visuals replicating typical library styles
            const cardHtml = chart.setCardHtml();
            cardHtml.setCardDim({
                w: 220,
                h: 70
            });

            // Default Silhouette SVG (Width/Height rigidly defined inside SVG tags to prevent scaling bleed!)
            const defaultAvatar =
                `<svg viewBox="0 0 24 24" fill="white" width="40" height="40" style="margin: 0 auto; opacity: 0.9;"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" /></svg>`;

            cardHtml.setCardDisplay([
                (d) => {
                    const isMale = d.data.gender === 'M';
                    // Replicated exact colors from f3 screenshot
                    const bgColor = isMale ? '#7ea4b3' : '#ce969b';

                    const avatarHtml = d.data.foto_url ?
                        `<img src="${d.data.foto_url}" class="w-full h-full object-cover" />` :
                        defaultAvatar;

                    return `<div 
                        class="overflow-hidden rounded-md shadow-md transition-shadow cursor-pointer"
                        style="width: 220px; height: 70px; display: flex; background-color: ${bgColor};"
                    >
                        <!-- Avatar Section (Left) -->
                        <div class="flex-shrink-0 flex items-center justify-center border-r border-black/10" style="width: 70px; height: 70px; background-color: rgba(0,0,0,0.15);">
                            ${avatarHtml}
                        </div>
                        
                        <!-- Details Section (Center) -->
                        <div class="flex-1 flex items-center px-3" style="width: 110px;">
                            <span class="text-white font-medium text-sm leading-tight truncate-multiline">
                                ${d.data.label || ''}
                            </span>
                        </div>
                        
                        <!-- Detail Info Button (Right) -->
                        <div 
                            onclick="event.stopPropagation(); window.Livewire.navigate('${d.data.url}');" 
                            class="flex-shrink-0 flex items-center justify-center cursor-pointer hover:bg-black/20 transition-colors"
                            style="width: 40px; height: 70px; border-left: 1px solid rgba(0,0,0,0.1);"
                            title="Detail Profil"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="white" class="w-5 h-5 opacity-90">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                        </div>
                    </div>`;
                }
            ]);

            const rootNode = data.find(d => d.id === '{{ $user->id }}');

            // Wait a little bit for DOM to render then place tree
            setTimeout(() => {
                // Resize SVG boundaries securely to full screen FIRST before calculating center
                const svgElement = container.querySelector('svg');
                if (svgElement) {
                    svgElement.style.width = '100%';
                    svgElement.style.height = '100%';
                }

                chart.updateTree({
                    initial: true,
                    tree_position: rootNode ? 'main_to_middle' : 'fit'
                });
            }, 100);
        }

        window.toggleFullscreen = function() {
            let elem = document.getElementById('tree-wrapper');
            if (!document.fullscreenElement) {
                if (elem.requestFullscreen) {
                    elem.requestFullscreen();
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen();
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        };
    </script>
@endpush
