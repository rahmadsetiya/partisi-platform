<?php

namespace App\Services\Partisi;

use App\Models\SesiPartisi;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan algoritma partisi auto untuk sebuah SesiPartisi dan menyimpan
 * hasilnya ke partisi_detail. Dipakai baik sinkron (controller) maupun via
 * queue Job (JalankanPartisiAuto). Idempotent: assignment lama sesi dihapus
 * dulu sebelum diisi ulang (mendukung regenerate).
 */
class PartisiRunner
{
    /**
     * @return array{cv:?float, gap:float, count:int}
     */
    public function run(SesiPartisi $sesi): array
    {
        $kegiatan = $sesi->kegiatan;

        $pplList = $kegiatan->petugas()->where('peran', 'ppl')->orderBy('group_id')->pluck('id')->all();
        $pmlList = $kegiatan->petugas()->where('peran', 'pml')->orderBy('group_id')->pluck('id')->all();
        $nPpl = count($pplList);
        $nPml = count($pmlList);

        if ($nPpl < 1) {
            throw new \RuntimeException('Kegiatan belum punya PPL.');
        }

        $rows = DB::table('kegiatan_wilayah as kw')
            ->join('subsls as s', 's.id', '=', 'kw.subsls_id')
            ->where('kw.kegiatan_id', $kegiatan->id)
            ->whereNotNull('s.geometry')
            ->select('s.id', 's.idsubsls', 's.geometry', 's.centroid_lat', 's.centroid_lon', 's.nmdesa', 'kw.muatan')
            ->get();

        if ($rows->isEmpty()) {
            throw new \RuntimeException('Kegiatan belum memiliki wilayah kerja (SubSLS) bergeometri.');
        }
        if ($nPpl > $rows->count()) {
            throw new \RuntimeException('Jumlah PPL melebihi jumlah SubSLS.');
        }

        // Siapkan input algoritma.
        $subsls = [];
        $loads = [];
        $desaMap = [];
        $idToSubslsId = [];
        $centroid = [];
        foreach ($rows as $r) {
            $subsls[] = [
                'id' => $r->id,
                'geometry' => json_decode($r->geometry, true),
                'centroid_lat' => $r->centroid_lat,
                'centroid_lon' => $r->centroid_lon,
            ];
            $loads[$r->id] = (float) ($r->muatan ?? 1);
            $desaMap[$r->id] = $r->nmdesa;
            $idToSubslsId[$r->idsubsls] = $r->id;
            $centroid[$r->id] = ['lat' => (float) $r->centroid_lat, 'lon' => (float) $r->centroid_lon];
        }

        // Override koneksi (force_connect/disconnect) → pasangan subsls_id.
        $overrides = [];
        foreach ($kegiatan->overrides()->get(['idsubsls_a', 'idsubsls_b', 'tipe']) as $ov) {
            if (isset($idToSubslsId[$ov->idsubsls_a], $idToSubslsId[$ov->idsubsls_b])) {
                $overrides[] = [
                    'a' => $idToSubslsId[$ov->idsubsls_a],
                    'b' => $idToSubslsId[$ov->idsubsls_b],
                    'tipe' => $ov->tipe,
                ];
            }
        }

        $config = $sesi->config ?? [];
        $prioritasDesa = (bool) ($config['prioritas_desa'] ?? false);
        $restarts = (int) ($config['restarts'] ?? 6);

        // Jalankan algoritma.
        $adjacency = (new AdjacencyBuilder)->build($subsls, $overrides);
        $partitioner = new BalancedPartitioner(
            loads: $loads,
            adjacency: $adjacency,
            nGroups: $nPpl,
            restarts: $restarts,
            desaMap: $prioritasDesa ? $desaMap : [],
            desaPenalty: $prioritasDesa ? 500.0 : 0.0,
        );
        $hasil = $partitioner->run();
        $partition = $hasil['partition']; // subsls_id => group (0-based)

        // PML auto: centroid rata-rata tiap PPL → kelompokkan.
        $pmlForGroup = [];
        if ($nPml > 0) {
            $acc = [];
            foreach ($partition as $sid => $g) {
                $acc[$g]['lat'] = ($acc[$g]['lat'] ?? 0) + $centroid[$sid]['lat'];
                $acc[$g]['lon'] = ($acc[$g]['lon'] ?? 0) + $centroid[$sid]['lon'];
                $acc[$g]['n'] = ($acc[$g]['n'] ?? 0) + 1;
            }
            $pplCentroid = [];
            foreach ($acc as $g => $a) {
                $pplCentroid[$g] = ['lat' => $a['lat'] / $a['n'], 'lon' => $a['lon'] / $a['n']];
            }
            $pmlForGroup = (new PmlGrouper)->group($pplCentroid, $nPml); // group => pmlIndex
        }

        // Susun baris detail.
        $now = now();
        $detailRows = [];
        foreach ($partition as $sid => $g) {
            $pplId = $pplList[$g] ?? $pplList[array_key_first($pplList)];
            $pmlId = null;
            if ($nPml > 0 && isset($pmlForGroup[$g], $pmlList[$pmlForGroup[$g]])) {
                $pmlId = $pmlList[$pmlForGroup[$g]];
            }
            $detailRows[] = [
                'sesi_partisi_id' => $sesi->id,
                'subsls_id' => $sid,
                'ppl_id' => $pplId,
                'pml_id' => $pmlId,
                'created_at' => $now,
            ];
        }

        // Tulis (idempotent: hapus assignment lama dulu).
        DB::transaction(function () use ($sesi, $detailRows, $hasil, $config) {
            $sesi->detail()->delete();
            foreach (array_chunk($detailRows, 500) as $chunk) {
                DB::table('partisi_detail')->insert($chunk);
            }
            $sesi->update([
                'cv' => $hasil['cv'],
                'config' => array_merge($config, ['gap' => $hasil['gap']]),
            ]);
        });

        return [
            'cv' => $hasil['cv'],
            'gap' => $hasil['gap'],
            'count' => count($detailRows),
        ];
    }
}
