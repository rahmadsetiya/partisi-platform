<?php

namespace App\Services\Partisi;

/**
 * Membangun adjacency list antar SubSLS dari geometri polygon (tanpa OSM/PostGIS).
 *
 * Pendekatan: shared-edge hashing — dua polygon bertetangga (rook adjacency)
 * bila memiliki segmen tepi yang sama (sepasang vertex berurutan yang identik
 * setelah dibulatkan). Polygon wilkerstat BPS umumnya berbagi vertex eksak.
 *
 * Fallback: node tanpa tetangga dihubungkan via vertex bersama, lalu (jika masih
 * sebatang kara) ke centroid terdekat — supaya tidak ada SubSLS yang terisolasi.
 */
class AdjacencyBuilder
{
    /** Pembulatan koordinat (≈ 0.1 m pada 6 desimal) untuk mencocokkan vertex. */
    private const PRESISI = 6;

    /**
     * @param  array<int,array{id:int,geometry:?array,centroid_lat:?float,centroid_lon:?float}>  $subsls
     * @param  array<int,array{a:int,b:int,tipe:string}>  $overrides  pasangan subsls_id (force_connect|force_disconnect)
     * @return array<int,array<int,bool>> adjacency: id => [tetanggaId => true]
     */
    public function build(array $subsls, array $overrides = []): array
    {
        $adj = [];
        foreach ($subsls as $s) {
            $adj[$s['id']] = [];
        }

        // 1. Index segmen tepi → daftar subsls_id pemiliknya.
        $edgeOwners = [];
        foreach ($subsls as $s) {
            foreach ($this->segments($s['geometry']) as $key) {
                $edgeOwners[$key][$s['id']] = true;
            }
        }

        // 2. Polygon yang berbagi segmen tepi sama = bertetangga.
        foreach ($edgeOwners as $owners) {
            if (count($owners) < 2) {
                continue;
            }
            $ids = array_keys($owners);
            foreach ($ids as $a) {
                foreach ($ids as $b) {
                    if ($a !== $b) {
                        $adj[$a][$b] = true;
                    }
                }
            }
        }

        // 3. Fallback untuk node terisolasi: vertex bersama → centroid terdekat.
        $this->hubungkanTerisolasi($adj, $subsls);

        // 4. Terapkan override manual.
        foreach ($overrides as $ov) {
            $a = $ov['a'];
            $b = $ov['b'];
            if (! isset($adj[$a]) || ! isset($adj[$b])) {
                continue;
            }
            if ($ov['tipe'] === 'force_connect') {
                $adj[$a][$b] = true;
                $adj[$b][$a] = true;
            } elseif ($ov['tipe'] === 'force_disconnect') {
                unset($adj[$a][$b], $adj[$b][$a]);
            }
        }

        return $adj;
    }

    /**
     * Segmen tepi sebuah geometri sebagai key tak-berarah "p1|p2" (urut).
     *
     * @return list<string>
     */
    private function segments(?array $geometry): array
    {
        if (! $geometry || empty($geometry['coordinates'])) {
            return [];
        }

        // Normalisasi Polygon vs MultiPolygon menjadi daftar ring.
        $rings = [];
        if (($geometry['type'] ?? '') === 'MultiPolygon') {
            foreach ($geometry['coordinates'] as $poly) {
                foreach ($poly as $ring) {
                    $rings[] = $ring;
                }
            }
        } else { // Polygon
            foreach ($geometry['coordinates'] as $ring) {
                $rings[] = $ring;
            }
        }

        $segments = [];
        foreach ($rings as $ring) {
            $n = count($ring);
            for ($i = 0; $i < $n - 1; $i++) {
                $p1 = $this->pointKey($ring[$i]);
                $p2 = $this->pointKey($ring[$i + 1]);
                if ($p1 === $p2) {
                    continue;
                }
                $segments[] = $p1 < $p2 ? "$p1|$p2" : "$p2|$p1";
            }
        }

        return $segments;
    }

    private function pointKey(array $coord): string
    {
        return sprintf('%.'.self::PRESISI.'f,%.'.self::PRESISI.'f', $coord[0], $coord[1]);
    }

    /**
     * Hubungkan node tanpa tetangga: pertama via vertex bersama, lalu centroid terdekat.
     *
     * @param  array<int,array<int,bool>>  $adj
     * @param  array<int,array>  $subsls
     */
    private function hubungkanTerisolasi(array &$adj, array $subsls): void
    {
        $terisolasi = array_filter($adj, fn ($n) => count($n) === 0);
        if (empty($terisolasi)) {
            return;
        }

        // Index vertex → pemilik (hanya dibangun bila ada node terisolasi).
        $vertexOwners = [];
        $byId = [];
        foreach ($subsls as $s) {
            $byId[$s['id']] = $s;
            foreach ($this->vertices($s['geometry']) as $vk) {
                $vertexOwners[$vk][$s['id']] = true;
            }
        }

        foreach (array_keys($terisolasi) as $id) {
            // a) vertex bersama
            foreach ($this->vertices($byId[$id]['geometry'] ?? null) as $vk) {
                foreach (array_keys($vertexOwners[$vk] ?? []) as $other) {
                    if ($other !== $id) {
                        $adj[$id][$other] = true;
                        $adj[$other][$id] = true;
                    }
                }
            }
            if (count($adj[$id]) > 0) {
                continue;
            }

            // b) centroid terdekat
            $nearest = null;
            $best = INF;
            foreach ($subsls as $s) {
                if ($s['id'] === $id) {
                    continue;
                }
                $d = $this->jarakCentroid($byId[$id], $s);
                if ($d < $best) {
                    $best = $d;
                    $nearest = $s['id'];
                }
            }
            if ($nearest !== null) {
                $adj[$id][$nearest] = true;
                $adj[$nearest][$id] = true;
            }
        }
    }

    /** @return list<string> */
    private function vertices(?array $geometry): array
    {
        if (! $geometry || empty($geometry['coordinates'])) {
            return [];
        }
        $out = [];
        array_walk_recursive($geometry['coordinates'], function ($v) use (&$out) {
            $out[] = $v;
        });
        // $out kini deret angka [lon,lat,lon,lat,...] → pasangkan.
        $keys = [];
        for ($i = 0; $i + 1 < count($out); $i += 2) {
            $keys[] = $this->pointKey([$out[$i], $out[$i + 1]]);
        }

        return array_values(array_unique($keys));
    }

    private function jarakCentroid(array $a, array $b): float
    {
        $latA = (float) ($a['centroid_lat'] ?? 0);
        $lonA = (float) ($a['centroid_lon'] ?? 0);
        $latB = (float) ($b['centroid_lat'] ?? 0);
        $lonB = (float) ($b['centroid_lon'] ?? 0);
        // Equirectangular approx (cukup untuk skala kabupaten).
        $dx = ($lonB - $lonA) * cos(deg2rad(($latA + $latB) / 2));
        $dy = $latB - $latA;

        return $dx * $dx + $dy * $dy;
    }
}
