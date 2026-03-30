<?php

namespace App\Services;

use App\Models\User;
use App\Models\Pernikahan;

class FamilyRelationshipFinder
{
    const MAX_HOPS = 15;

    /**
     * Find the shortest relationship path between two users (BFS).
     * Returns an array of steps, or null if no path found within MAX_HOPS.
     *
     * Each step: ['user' => [...], 'relation' => string, 'relation_label' => string]
     * The first step has no 'relation' (it's the starting person).
     */
    public function find(User $from, User $to): ?array
    {
        if ($from->id === $to->id) {
            return [
                [
                    'user'           => $this->userArray($from),
                    'relation'       => null,
                    'relation_label' => null,
                ],
            ];
        }

        // BFS: each queue item = [userId, path_so_far]
        // path_so_far = array of ['user_id', 'relation', 'relation_label']
        $queue   = [];
        $visited = [$from->id => true];

        // Seed the queue with neighbours of $from
        foreach ($this->getNeighbours($from) as $neighbour) {
            if (!isset($visited[$neighbour['user_id']])) {
                $queue[] = [
                    'current_id' => $neighbour['user_id'],
                    'path'       => [
                        [
                            'user_id'        => $from->id,
                            'relation'       => null,
                            'relation_label' => null,
                        ],
                        [
                            'user_id'        => $neighbour['user_id'],
                            'relation'       => $neighbour['relation'],
                            'relation_label' => $neighbour['relation_label'],
                        ],
                    ],
                ];
                $visited[$neighbour['user_id']] = true;
            }
        }

        while (!empty($queue)) {
            $item       = array_shift($queue);
            $currentId  = $item['current_id'];
            $path       = $item['path'];

            // Hop limit: path already includes start node, so hops = count - 1
            if (count($path) - 1 >= self::MAX_HOPS) {
                continue;
            }

            if ($currentId === $to->id) {
                // Resolve full user objects for each step in path
                return $this->resolvePath($path);
            }

            $currentUser = User::find($currentId);
            if (!$currentUser) {
                continue;
            }

            foreach ($this->getNeighbours($currentUser) as $neighbour) {
                if (!isset($visited[$neighbour['user_id']])) {
                    $newPath   = $path;
                    $newPath[] = [
                        'user_id'        => $neighbour['user_id'],
                        'relation'       => $neighbour['relation'],
                        'relation_label' => $neighbour['relation_label'],
                    ];
                    $queue[]                               = [
                        'current_id' => $neighbour['user_id'],
                        'path'       => $newPath,
                    ];
                    $visited[$neighbour['user_id']] = true;
                }
            }
        }

        return null; // not found within MAX_HOPS
    }

    /**
     * Get all direct relatives of a user as a flat list of neighbours.
     * Returns: array of ['user_id', 'relation', 'relation_label']
     *
     * Relations from perspective of $user:
     *   - ayah    → parent going up
     *   - ibu     → parent going up
     *   - anak    → child going down (from each marriage)
     *   - pasangan → spouse (lateral)
     *   - saudara → sibling (lateral via shared ortu_id)
     */
    private function getNeighbours(User $user): array
    {
        $neighbours = [];

        // 1. Father
        if ($user->ayah_id) {
            $neighbours[] = [
                'user_id'        => $user->ayah_id,
                'relation'       => 'child_of_father', // user is child, neighbour is father
                'relation_label' => null, // resolved later
            ];
        }

        // 2. Mother
        if ($user->ibu_id) {
            $neighbours[] = [
                'user_id'        => $user->ibu_id,
                'relation'       => 'child_of_mother',
                'relation_label' => null,
            ];
        }

        // 3. Marriages: spouses and children
        $pernikahans = Pernikahan::where('suami_id', $user->id)
            ->orWhere('istri_id', $user->id)
            ->with(['suami', 'istri', 'anak'])
            ->get();

        foreach ($pernikahans as $p) {
            // Spouse
            $spouseId = $user->id === $p->suami_id ? $p->istri_id : $p->suami_id;
            if ($spouseId) {
                $userIsHusband = $user->id === $p->suami_id;
                $neighbours[]  = [
                    'user_id'        => $spouseId,
                    'relation'       => $userIsHusband ? 'husband_of' : 'wife_of',
                    'relation_label' => null,
                ];
            }

            // Children
            foreach ($p->anak as $anak) {
                $neighbours[] = [
                    'user_id'        => $anak->id,
                    'relation'       => $user->jenis_kelamin == '1' ? 'father_of' : 'mother_of',
                    'relation_label' => null,
                ];
            }
        }

        // 4. Siblings (same ortu_id)
        if ($user->ortu_id) {
            $siblings = User::where('ortu_id', $user->ortu_id)
                ->where('id', '!=', $user->id)
                ->pluck('id');
            foreach ($siblings as $sibId) {
                $neighbours[] = [
                    'user_id'        => $sibId,
                    'relation'       => 'sibling_of',
                    'relation_label' => null,
                ];
            }
        }

        return $neighbours;
    }

    /**
     * Resolve the compact path (user_ids) into full user data arrays.
     */
    private function resolvePath(array $path): array
    {
        $userIds = array_column($path, 'user_id');
        // Use get()->find() to ensure returned items are proper Eloquent User instances
        $usersCollection = User::whereIn('id', $userIds)->get();

        $resolved = [];
        for ($i = 0; $i < count($path); $i++) {
            $step   = $path[$i];
            $userId = $step['user_id'];
            /** @var User|null $user */
            $user   = $usersCollection->firstWhere('id', $userId);

            if (!$user) {
                continue;
            }

            $relationLabel = null;
            if ($i > 0 && $step['relation']) {
                /** @var User|null $prevUser */
                $prevUser      = $usersCollection->firstWhere('id', $path[$i - 1]['user_id']);
                $relationLabel = $this->buildRelationLabel($step['relation'], $prevUser, $user);
            }

            $resolved[] = [
                'user'           => $this->userArray($user),
                'relation'       => $step['relation'],
                'relation_label' => $relationLabel,
            ];
        }

        return $resolved;
    }

    /**
     * Build human-readable label.
     * e.g. "Ahmad adalah Ayah dari Budi"
     * but stored as: previous node → relation → current node
     *
     * We store: label from perspective of the PREVIOUS person toward CURRENT person.
     */
    private function buildRelationLabel(string $relation, ?User $from, ?User $to): string
    {
        $fromName = $from?->name ?? '?';
        $toName   = $to?->name ?? '?';

        return match ($relation) {
            'child_of_father'  => "{$fromName} → Anak dari → {$toName}",
            'child_of_mother'  => "{$fromName} → Anak dari → {$toName}",
            'father_of'        => "{$fromName} → Ayah dari → {$toName}",
            'mother_of'        => "{$fromName} → Ibu dari → {$toName}",
            'husband_of'       => "{$fromName} → Suami dari → {$toName}",
            'wife_of'          => "{$fromName} → Istri dari → {$toName}",
            'sibling_of'       => "{$fromName} → Saudara dari → {$toName}",
            default            => "{$fromName} → ? → {$toName}",
        };
    }

    /**
     * Convert User model to a compact array for the view.
     */
    private function userArray(User $user): array
    {
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'wafat'        => $user->wafat,
            'jenis_kelamin' => $user->jenis_kelamin,
            'foto'         => $user->foto,
        ];
    }

    /**
     * Build chart nodes for the found path (for family-chart library).
     * Returns array of nodes in f3 format.
     */
    public function buildChartNodes(array $path): array
    {
        if (empty($path)) {
            return [];
        }

        $nodesMap = [];
        $userIds  = array_column($path, null);
        // Extract actual user data
        $users = collect($path)->pluck('user')->keyBy('id');

        foreach ($path as $i => $step) {
            $userData = $step['user'];
            $id       = $userData['id'];
            $gender   = $userData['jenis_kelamin'] == '1' ? 'M' : 'F';
            $wafat    = $userData['wafat'] ? ' (Alm)' : '';

            $node = [
                'id'   => (string) $id,
                'data' => [
                    'label'    => $userData['name'] . $wafat,
                    'gender'   => $gender,
                    'url'      => route('pengguna.show', $id),
                    'foto_url' => $userData['foto'] ? asset('storage/' . $userData['foto']) : null,
                    'highlight' => ($i === 0 || $i === count($path) - 1),
                ],
                'rels' => [
                    'spouses'  => [],
                    'children' => [],
                ],
            ];

            // Wire up parent/child relationships
            $relation = $step['relation'] ?? null;

            if ($i > 0) {
                $prevId       = $path[$i - 1]['user']['id'];
                $prevUserData = $path[$i - 1]['user'];

                switch ($relation) {
                    case 'child_of_father':
                        $node['rels']['father'] = (string) $prevId;
                        if (isset($nodesMap[$prevId])) {
                            $nodesMap[$prevId]['rels']['children'][] = (string) $id;
                        }
                        break;
                    case 'child_of_mother':
                        $node['rels']['mother'] = (string) $prevId;
                        if (isset($nodesMap[$prevId])) {
                            $nodesMap[$prevId]['rels']['children'][] = (string) $id;
                        }
                        break;
                    case 'father_of':
                    case 'mother_of':
                        if (isset($nodesMap[$prevId])) {
                            $nodesMap[$prevId]['rels']['children'][] = (string) $id;
                        }
                        if ($relation === 'father_of') {
                            $node['rels']['father'] = (string) $prevId;
                        } else {
                            $node['rels']['mother'] = (string) $prevId;
                        }
                        break;
                    case 'husband_of':
                    case 'wife_of':
                        if (isset($nodesMap[$prevId])) {
                            $nodesMap[$prevId]['rels']['spouses'][] = (string) $id;
                        }
                        $node['rels']['spouses'][] = (string) $prevId;
                        break;
                    case 'sibling_of':
                        // siblings share the same parents – just show them side by side
                        // no parent link in this simplified chart
                        break;
                }
            }

            $nodesMap[$id] = $node;
        }

        // Clean up empty arrays
        foreach ($nodesMap as &$mapNode) {
            $mapNode['rels']['children'] = array_values(array_unique($mapNode['rels']['children']));
            $mapNode['rels']['spouses']  = array_values(array_unique($mapNode['rels']['spouses']));
            if (empty($mapNode['rels']['children'])) {
                unset($mapNode['rels']['children']);
            }
            if (empty($mapNode['rels']['spouses'])) {
                unset($mapNode['rels']['spouses']);
            }
        }

        return array_values($nodesMap);
    }
}
