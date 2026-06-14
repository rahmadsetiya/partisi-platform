<?php

namespace App\Services\Partisi;

/**
 * Mengelompokkan PPL (berdasarkan centroid wilayahnya) ke n_pml PML
 * yang berdekatan secara geografis (k-means++ ringan).
 */
class PmlGrouper
{
    /**
     * @param  array<int,array{lat:float,lon:float}>  $pplCentroid  pplGroupId => centroid
     * @return array<int,int> pplGroupId => pmlIndex (0-based)
     */
    public function group(array $pplCentroid, int $nPml): array
    {
        $groups = array_keys($pplCentroid);
        $n = count($groups);

        if ($nPml < 1 || $n === 0) {
            return [];
        }
        if ($nPml >= $n) {
            // Tiap PPL jadi PML sendiri (dibatasi nPml).
            $out = [];
            foreach ($groups as $i => $g) {
                $out[$g] = min($i, $nPml - 1);
            }

            return $out;
        }

        mt_srand(7);

        // Seed k-means++ : pusat menyebar.
        $centers = [$pplCentroid[$groups[mt_rand(0, $n - 1)]]];
        while (count($centers) < $nPml) {
            $best = null;
            $bestVal = -1.0;
            foreach ($groups as $g) {
                $minD = INF;
                foreach ($centers as $c) {
                    $minD = min($minD, $this->dist2($pplCentroid[$g], $c));
                }
                if ($minD > $bestVal) {
                    $bestVal = $minD;
                    $best = $pplCentroid[$g];
                }
            }
            $centers[] = $best;
        }

        // Lloyd iterations.
        $assign = [];
        for ($iter = 0; $iter < 20; $iter++) {
            $changed = false;
            foreach ($groups as $g) {
                $bestC = 0;
                $bestD = INF;
                foreach ($centers as $ci => $c) {
                    $d = $this->dist2($pplCentroid[$g], $c);
                    if ($d < $bestD) {
                        $bestD = $d;
                        $bestC = $ci;
                    }
                }
                if (($assign[$g] ?? -1) !== $bestC) {
                    $assign[$g] = $bestC;
                    $changed = true;
                }
            }

            // Recompute centers.
            $sum = array_fill(0, $nPml, ['lat' => 0.0, 'lon' => 0.0, 'n' => 0]);
            foreach ($groups as $g) {
                $c = $assign[$g];
                $sum[$c]['lat'] += $pplCentroid[$g]['lat'];
                $sum[$c]['lon'] += $pplCentroid[$g]['lon'];
                $sum[$c]['n']++;
            }
            foreach ($sum as $ci => $s) {
                if ($s['n'] > 0) {
                    $centers[$ci] = ['lat' => $s['lat'] / $s['n'], 'lon' => $s['lon'] / $s['n']];
                }
            }

            if (! $changed) {
                break;
            }
        }

        return $assign;
    }

    /** @param array{lat:float,lon:float} $a @param array{lat:float,lon:float} $b */
    private function dist2(array $a, array $b): float
    {
        $dx = ($b['lon'] - $a['lon']) * cos(deg2rad(($a['lat'] + $b['lat']) / 2));
        $dy = $b['lat'] - $a['lat'];

        return $dx * $dx + $dy * $dy;
    }
}
