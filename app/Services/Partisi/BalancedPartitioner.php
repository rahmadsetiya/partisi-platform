<?php

namespace App\Services\Partisi;

/**
 * Balanced Connected Graph Partitioning (port PHP dari partitioner.py, mode touching-only).
 *
 * Membagi node (SubSLS, berbobot muatan) menjadi n grup yang:
 *  - CONNECTED (tiap grup nyambung di graph adjacency), dan
 *  - SEIMBANG (minimisasi gap muatan max–min antar grup).
 *
 * Pipeline tiap restart: seed k-means++ (jarak graph/BFS) → region growing
 * (selalu perbesar grup termuat-ringan) → local search (tukar node batas).
 * Ambil hasil dgn gap terkecil dari beberapa restart.
 */
class BalancedPartitioner
{
    /** @var list<int> */
    private array $nodes;

    private int $nNodes;

    private float $totalLoad;

    /**
     * @param  array<int,float>  $loads  id => muatan
     * @param  array<int,array<int,bool>>  $adjacency  id => [tetanggaId => true]
     * @param  array<int,?string>  $desaMap  id => nama desa (opsional, soft-constraint)
     */
    public function __construct(
        private array $loads,
        private array $adjacency,
        private int $nGroups,
        private int $restarts = 6,
        private int $maxLocalSearchPasses = 50,
        private array $desaMap = [],
        private float $desaPenalty = 0.0,
    ) {
        $this->nodes = array_keys($loads);
        $this->nNodes = count($this->nodes);
        $this->totalLoad = array_sum($loads);

        if ($nGroups < 1) {
            throw new \InvalidArgumentException('Jumlah grup harus ≥ 1.');
        }
        if ($nGroups > $this->nNodes) {
            throw new \InvalidArgumentException('Jumlah grup tidak boleh melebihi jumlah SubSLS.');
        }
    }

    /**
     * @return array{partition:array<int,int>, cv:float, gap:float}
     */
    public function run(): array
    {
        $best = null;
        $bestScore = INF;

        for ($r = 0; $r < $this->restarts; $r++) {
            mt_srand($r * 31 + 13);
            $seeds = $this->selectSeeds();
            $partition = $this->regionGrowing($seeds);
            $partition = $this->localSearch($partition);

            $score = $this->score($partition);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $partition;
            }
        }

        $best ??= $this->fallbackPartition();

        return [
            'partition' => $best,
            'cv' => $this->cv($best),
            'gap' => $this->gap($best),
        ];
    }

    // ---------------------------------------------------------------- seeds

    /** @return list<int> */
    private function selectSeeds(): array
    {
        $seeds = [$this->nodes[mt_rand(0, $this->nNodes - 1)]];
        $minDist = $this->bfsDistances($seeds[0]);

        while (count($seeds) < $this->nGroups) {
            $chosen = null;
            $bestVal = -1.0;
            foreach ($this->nodes as $node) {
                if (in_array($node, $seeds, true)) {
                    continue;
                }
                $d = $minDist[$node] ?? 1e9;
                $noisy = $d * (0.85 + 0.30 * (mt_rand() / mt_getrandmax()));
                if ($noisy > $bestVal) {
                    $bestVal = $noisy;
                    $chosen = $node;
                }
            }
            if ($chosen === null) {
                break;
            }
            $seeds[] = $chosen;
            // Perbarui minDist dengan jarak ke seed baru.
            $dNew = $this->bfsDistances($chosen);
            foreach ($this->nodes as $node) {
                $minDist[$node] = min($minDist[$node] ?? 1e9, $dNew[$node] ?? 1e9);
            }
        }

        return $seeds;
    }

    /**
     * Jarak hop BFS dari source ke semua node (graph tak berbobot).
     *
     * @return array<int,float>
     */
    private function bfsDistances(int $source): array
    {
        $dist = [$source => 0.0];
        $queue = [$source];
        $head = 0;
        while ($head < count($queue)) {
            $cur = $queue[$head++];
            foreach (array_keys($this->adjacency[$cur] ?? []) as $nb) {
                if (! isset($dist[$nb])) {
                    $dist[$nb] = $dist[$cur] + 1.0;
                    $queue[] = $nb;
                }
            }
        }

        return $dist;
    }

    // ------------------------------------------------------- region growing

    /**
     * @param  list<int>  $seeds
     * @return array<int,int>
     */
    private function regionGrowing(array $seeds): array
    {
        $partition = [];
        $groupLoads = array_fill(0, $this->nGroups, 0.0);
        $unassigned = array_fill_keys($this->nodes, true);

        // FIFO frontier per grup.
        $frontiers = array_fill(0, $this->nGroups, []);
        $fhead = array_fill(0, $this->nGroups, 0);

        foreach ($seeds as $g => $seed) {
            $partition[$seed] = $g;
            $groupLoads[$g] += $this->loads[$seed];
            unset($unassigned[$seed]);
            foreach (array_keys($this->adjacency[$seed] ?? []) as $nb) {
                if (isset($unassigned[$nb])) {
                    $frontiers[$g][] = $nb;
                }
            }
        }

        $remaining = count($unassigned);
        while ($remaining > 0) {
            // Pilih grup termuat-ringan yang frontiernya masih punya kandidat.
            $chosen = -1;
            $chosenLoad = INF;
            for ($g = 0; $g < $this->nGroups; $g++) {
                // Buang entri basi.
                while ($fhead[$g] < count($frontiers[$g]) && ! isset($unassigned[$frontiers[$g][$fhead[$g]]])) {
                    $fhead[$g]++;
                }
                if ($fhead[$g] < count($frontiers[$g]) && $groupLoads[$g] < $chosenLoad) {
                    $chosenLoad = $groupLoads[$g];
                    $chosen = $g;
                }
            }
            if ($chosen === -1) {
                break; // ada komponen tak terjangkau
            }

            $cand = $frontiers[$chosen][$fhead[$chosen]++];
            if (! isset($unassigned[$cand])) {
                continue;
            }
            $partition[$cand] = $chosen;
            $groupLoads[$chosen] += $this->loads[$cand];
            unset($unassigned[$cand]);
            $remaining--;
            foreach (array_keys($this->adjacency[$cand] ?? []) as $nb) {
                if (isset($unassigned[$nb])) {
                    $frontiers[$chosen][] = $nb;
                }
            }
        }

        // Node tak terjangkau → grup termuat-ringan.
        if ($remaining > 0) {
            foreach (array_keys($unassigned) as $node) {
                $lightest = $this->argMinLoad($groupLoads);
                $partition[$node] = $lightest;
                $groupLoads[$lightest] += $this->loads[$node];
            }
        }

        return $partition;
    }

    // --------------------------------------------------------- local search

    /**
     * @param  array<int,int>  $partition
     * @return array<int,int>
     */
    private function localSearch(array $partition): array
    {
        $groupLoads = $this->computeGroupLoads($partition);

        for ($pass = 0; $pass < $this->maxLocalSearchPasses; $pass++) {
            $improved = false;
            $boundary = $this->boundaryNodes($partition);
            shuffle($boundary);

            foreach ($boundary as $node) {
                $src = $partition[$node];
                $nodeLoad = $this->loads[$node];

                $neighborGroups = [];
                foreach (array_keys($this->adjacency[$node] ?? []) as $nb) {
                    if ($partition[$nb] !== $src) {
                        $neighborGroups[$partition[$nb]] = true;
                    }
                }
                if (empty($neighborGroups)) {
                    continue;
                }
                if (! $this->isRemovable($node, $src, $partition)) {
                    continue;
                }

                $curGap = max($groupLoads) - min($groupLoads);
                $curScore = $this->desaActive()
                    ? $curGap + $this->desaPenalty * $this->desaViolations($partition)
                    : $curGap;

                $bestTarget = null;
                $bestScore = $curScore - 1e-9;
                $srcLoad = $groupLoads[$src];

                foreach (array_keys($neighborGroups) as $tgt) {
                    $tgtLoad = $groupLoads[$tgt];
                    $groupLoads[$src] = $srcLoad - $nodeLoad;
                    $groupLoads[$tgt] = $tgtLoad + $nodeLoad;
                    $newGap = max($groupLoads) - min($groupLoads);
                    $groupLoads[$src] = $srcLoad;
                    $groupLoads[$tgt] = $tgtLoad;

                    $newScore = $newGap;
                    if ($this->desaActive()) {
                        $partition[$node] = $tgt;
                        $newScore = $newGap + $this->desaPenalty * $this->desaViolations($partition);
                        $partition[$node] = $src;
                    }

                    if ($newScore < $bestScore) {
                        $bestScore = $newScore;
                        $bestTarget = $tgt;
                    }
                }

                if ($bestTarget !== null) {
                    $groupLoads[$src] -= $nodeLoad;
                    $groupLoads[$bestTarget] += $nodeLoad;
                    $partition[$node] = $bestTarget;
                    $improved = true;
                }
            }

            if (! $improved) {
                break;
            }
        }

        return $partition;
    }

    /**
     * Apakah node bisa dipindah tanpa memutus konektivitas grup asal.
     *
     * @param  array<int,int>  $partition
     */
    private function isRemovable(int $node, int $group, array $partition): bool
    {
        $groupNodes = [];
        foreach ($partition as $n => $g) {
            if ($g === $group && $n !== $node) {
                $groupNodes[$n] = true;
            }
        }
        $count = count($groupNodes);
        if ($count <= 1) {
            return true;
        }

        // BFS dalam subgraf grup (tanpa $node) — cek semua terjangkau.
        $start = array_key_first($groupNodes);
        $seen = [$start => true];
        $queue = [$start];
        $head = 0;
        while ($head < count($queue)) {
            $cur = $queue[$head++];
            foreach (array_keys($this->adjacency[$cur] ?? []) as $nb) {
                if (isset($groupNodes[$nb]) && ! isset($seen[$nb])) {
                    $seen[$nb] = true;
                    $queue[] = $nb;
                }
            }
        }

        return count($seen) === $count;
    }

    /**
     * @param  array<int,int>  $partition
     * @return list<int>
     */
    private function boundaryNodes(array $partition): array
    {
        $boundary = [];
        foreach ($this->nodes as $node) {
            $g = $partition[$node];
            foreach (array_keys($this->adjacency[$node] ?? []) as $nb) {
                if ($partition[$nb] !== $g) {
                    $boundary[] = $node;
                    break;
                }
            }
        }

        return $boundary;
    }

    // -------------------------------------------------------------- scoring

    /**
     * @param  array<int,int>  $partition
     * @return array<int,float>
     */
    private function computeGroupLoads(array $partition): array
    {
        $loads = array_fill(0, $this->nGroups, 0.0);
        foreach ($partition as $node => $g) {
            $loads[$g] += $this->loads[$node];
        }

        return $loads;
    }

    /** @param array<int,int> $partition */
    private function gap(array $partition): float
    {
        $loads = $this->computeGroupLoads($partition);

        return max($loads) - min($loads);
    }

    /** @param array<int,int> $partition */
    private function cv(array $partition): float
    {
        $loads = array_values($this->computeGroupLoads($partition));
        $mean = array_sum($loads) / count($loads);
        if ($mean <= 0) {
            return 0.0;
        }
        $var = 0.0;
        foreach ($loads as $l) {
            $var += ($l - $mean) ** 2;
        }
        $var /= count($loads);

        return round(sqrt($var) / $mean, 4);
    }

    private function desaActive(): bool
    {
        return $this->desaPenalty > 0 && ! empty($this->desaMap);
    }

    /** @param array<int,int> $partition */
    private function desaViolations(array $partition): int
    {
        if (! $this->desaActive()) {
            return 0;
        }
        $perGroup = [];
        foreach ($partition as $node => $g) {
            $desa = $this->desaMap[$node] ?? null;
            if ($desa !== null) {
                $perGroup[$g][$desa] = true;
            }
        }
        $violations = 0;
        foreach ($perGroup as $desaSet) {
            if (count($desaSet) > 1) {
                $violations++;
            }
        }

        return $violations;
    }

    /** @param array<int,int> $partition */
    private function score(array $partition): float
    {
        $gap = $this->gap($partition);

        return $this->desaActive()
            ? $gap + $this->desaPenalty * $this->desaViolations($partition)
            : $gap;
    }

    /** @param array<int,float> $groupLoads */
    private function argMinLoad(array $groupLoads): int
    {
        $min = INF;
        $idx = 0;
        foreach ($groupLoads as $g => $l) {
            if ($l < $min) {
                $min = $l;
                $idx = $g;
            }
        }

        return $idx;
    }

    /** @return array<int,int> */
    private function fallbackPartition(): array
    {
        $partition = [];
        $chunk = max(1, (int) floor($this->nNodes / $this->nGroups));
        foreach ($this->nodes as $i => $node) {
            $partition[$node] = min($this->nGroups - 1, intdiv($i, $chunk));
        }

        return $partition;
    }
}
