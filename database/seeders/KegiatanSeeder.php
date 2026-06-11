<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KegiatanSeeder extends Seeder
{
    private array $desaNames = [
        'LIMBUANG', 'BANGKARO', 'LABUKKANG', 'TANETE', 'SALODUA',
        'MARIO', 'BONTONGAN', 'BAMBAPUANG', 'KALOSI', 'PASUI',
    ];

    private array $slsNames = [
        'MELATI', 'MAWAR', 'ANGGREK', 'KENANGA', 'CEMPAKA',
        'DAHLIA', 'SERUNI', 'TERATAI', 'FLAMBOYAN', 'KAMBOJA',
    ];

    public function run(): void
    {
        $adminId    = DB::table('users')->where('email', 'admin@test.com')->value('id');
        $petugasIds = DB::table('petugas')->orderBy('id')->pluck('id')->all();
        $now        = now()->toDateTimeString();

        // kdkec, nmkec, baseLon, baseLat per kegiatan agar idsubsls & area tidak bentrok
        $configs = [
            [
                'nama' => 'SUSENAS Maret 2025', 'jenis' => 'berkala', 'tahun' => 2025,
                'gelombang' => 'Maret', 'mulai' => '2025-03-01', 'selesai' => '2025-03-31',
                'status' => 'aktif', 'kdkec' => '010', 'nmkec' => 'MAIWA',
                'lon' => 119.80, 'lat' => -3.55, 'jumlah' => 120, 'muatan' => 'kolom',
                'ppl' => 18, 'pml' => 4,
                'deskripsi' => 'Survei Sosial Ekonomi Nasional gelombang Maret 2025.',
            ],
            [
                'nama' => 'SAKERNAS Agustus 2025', 'jenis' => 'berkala', 'tahun' => 2025,
                'gelombang' => 'Agustus', 'mulai' => '2025-08-01', 'selesai' => '2025-08-31',
                'status' => 'aktif', 'kdkec' => '020', 'nmkec' => 'ANGGERAJA',
                'lon' => 119.88, 'lat' => -3.40, 'jumlah' => 90, 'muatan' => 'kolom',
                'ppl' => 14, 'pml' => 3,
                'deskripsi' => 'Survei Angkatan Kerja Nasional periode Agustus 2025.',
            ],
            [
                'nama' => 'SUSENAS September 2024', 'jenis' => 'berkala', 'tahun' => 2024,
                'gelombang' => 'September', 'mulai' => '2024-09-01', 'selesai' => '2024-09-30',
                'status' => 'selesai', 'kdkec' => '030', 'nmkec' => 'ALLA',
                'lon' => 119.72, 'lat' => -3.48, 'jumlah' => 60, 'muatan' => 'seragam',
                'ppl' => 10, 'pml' => 2,
                'deskripsi' => 'SUSENAS September 2024 (sudah selesai).',
            ],
            [
                'nama' => 'Sensus Pertanian 2023 (ST2023)', 'jenis' => 'insidentil', 'tahun' => 2023,
                'gelombang' => null, 'mulai' => '2023-06-01', 'selesai' => '2023-07-31',
                'status' => 'selesai', 'kdkec' => '040', 'nmkec' => 'BUNTU BATU',
                'lon' => 119.95, 'lat' => -3.62, 'jumlah' => 40, 'muatan' => 'kolom',
                'ppl' => 8, 'pml' => 2,
                'deskripsi' => 'Sensus Pertanian 2023 — pendataan usaha pertanian.',
            ],
            [
                'nama' => 'SAKERNAS Februari 2025', 'jenis' => 'berkala', 'tahun' => 2025,
                'gelombang' => 'Februari', 'mulai' => '2025-02-01', 'selesai' => '2025-02-28',
                'status' => 'draft', 'kdkec' => '050', 'nmkec' => 'BARAKA',
                'lon' => 119.83, 'lat' => -3.33, 'jumlah' => 30, 'muatan' => 'kosong',
                'ppl' => 4, 'pml' => 1,
                'deskripsi' => 'SAKERNAS Februari 2025 — muatan belum diisi.',
            ],
            [
                'nama' => 'Survei Khusus Kemiskinan 2025', 'jenis' => 'insidentil', 'tahun' => 2025,
                'gelombang' => null, 'mulai' => '2025-10-01', 'selesai' => null,
                'status' => 'draft', 'kdkec' => null, 'nmkec' => null,
                'lon' => 0, 'lat' => 0, 'jumlah' => 0, 'muatan' => 'kosong',
                'ppl' => 0, 'pml' => 0,
                'deskripsi' => 'Survei insidentil baru — belum ada wilayah & petugas.',
            ],
        ];

        $offset = 0; // rotasi petugas antar kegiatan
        foreach ($configs as $c) {
            $kegiatanId = DB::table('kegiatan')->insertGetId([
                'nama'            => $c['nama'],
                'jenis'           => $c['jenis'],
                'tahun'           => $c['tahun'],
                'gelombang'       => $c['gelombang'],
                'tanggal_mulai'   => $c['mulai'],
                'tanggal_selesai' => $c['selesai'],
                'deskripsi'       => $c['deskripsi'],
                'status'          => $c['status'],
                'created_by'      => $adminId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            if ($c['jumlah'] > 0) {
                $this->generateWilayah($kegiatanId, $c, $now);
                $this->buatUpload($kegiatanId, $c, $adminId, $now);
            }

            if ($c['ppl'] > 0 || $c['pml'] > 0) {
                $this->assignPetugas($kegiatanId, $petugasIds, $c['ppl'], $c['pml'], $offset);
                $offset += $c['ppl'] + $c['pml'];
            }
        }
    }

    private function generateWilayah(int $kegiatanId, array $c, string $now): void
    {
        $jumlah  = $c['jumlah'];
        $cols    = 12;
        $cell    = 0.0025; // ~275 m
        $numDesa = max(1, min(6, (int) ceil($jumlah / 30)));
        $perDesa = (int) ceil($jumlah / $numDesa);

        $muatanCol = match ($c['muatan']) {
            'kolom'   => 'Perkiraan_Jumlah_Muatan',
            'seragam' => '(seragam)',
            'kosong'  => null,
        };

        $subslsRows  = [];
        $wilayahMeta = []; // idsubsls => muatan

        for ($i = 0; $i < $jumlah; $i++) {
            $desaIdx = intdiv($i, $perDesa);
            $within  = $i % $perDesa;
            $slsIdx  = intdiv($within, 4);
            $subIdx  = $within % 4;

            $kddesa   = sprintf('%03d', $desaIdx + 1);
            $kdsls    = sprintf('%04d', $slsIdx + 1);
            $kdsubsls = sprintf('%02d', $subIdx + 1);
            $idsubsls = '7316'.$c['kdkec'].$kddesa.$kdsls.$kdsubsls;

            $col  = $i % $cols;
            $row  = intdiv($i, $cols);
            $lon0 = $c['lon'] + $col * $cell;
            $lat0 = $c['lat'] + $row * $cell;
            $lon1 = $lon0 + $cell;
            $lat1 = $lat0 + $cell;

            $geometry = [
                'type'        => 'Polygon',
                'coordinates' => [[
                    [$lon0, $lat0], [$lon1, $lat0], [$lon1, $lat1], [$lon0, $lat1], [$lon0, $lat0],
                ]],
            ];

            $subslsRows[] = [
                'idsubsls'     => $idsubsls,
                'kdsubsls'     => $kdsubsls,
                'kdprov'       => '73', 'nmprov' => 'SULAWESI SELATAN',
                'kdkab'        => '16', 'nmkab' => 'ENREKANG',
                'kdkec'        => $c['kdkec'], 'nmkec' => $c['nmkec'],
                'kddesa'       => $kddesa, 'nmdesa' => $this->desaNames[$desaIdx % count($this->desaNames)],
                'kdsls'        => $kdsls, 'nmsls' => 'DUSUN '.$this->slsNames[$slsIdx % count($this->slsNames)],
                'idsls'        => '7316'.$c['kdkec'].$kddesa.$kdsls,
                'geometry'     => json_encode($geometry),
                'centroid_lat' => $lat0 + $cell / 2,
                'centroid_lon' => $lon0 + $cell / 2,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            $wilayahMeta[$idsubsls] = match ($c['muatan']) {
                'kolom'   => random_int(25, 95),
                'seragam' => 1,
                'kosong'  => null,
            };
        }

        foreach (array_chunk($subslsRows, 500) as $chunk) {
            DB::table('subsls')->insert($chunk);
        }

        $idToSubslsId = DB::table('subsls')
            ->whereIn('idsubsls', array_keys($wilayahMeta))
            ->pluck('id', 'idsubsls');

        $wilayahRows = [];
        foreach ($wilayahMeta as $idsubsls => $muatan) {
            $wilayahRows[] = [
                'kegiatan_id' => $kegiatanId,
                'subsls_id'   => $idToSubslsId[$idsubsls],
                'muatan'      => $muatan,
                'muatan_col'  => $muatanCol,
                'created_at'  => $now,
            ];
        }

        foreach (array_chunk($wilayahRows, 500) as $chunk) {
            DB::table('kegiatan_wilayah')->insert($chunk);
        }
    }

    private function buatUpload(int $kegiatanId, array $c, int $adminId, string $now): void
    {
        $slug = strtolower(str_replace(' ', '_', $c['nmkec']));

        DB::table('geojson_uploads')->insert([
            'kegiatan_id'  => $kegiatanId,
            'level'        => 'subsls',
            'nama_file'    => "wilkerstat_{$slug}.geojson",
            'path'         => "geojson/seed_{$c['kdkec']}.geojson",
            'muatan_col'   => $c['muatan'] === 'kolom' ? 'Perkiraan_Jumlah_Muatan' : null,
            'epsg'         => 32750,
            'jumlah_fitur' => $c['jumlah'],
            'uploaded_by'  => $adminId,
            'uploaded_at'  => $now,
        ]);
    }

    private function assignPetugas(int $kegiatanId, array $petugasIds, int $nPpl, int $nPml, int $offset): void
    {
        $total = count($petugasIds);
        $now   = now()->toDateTimeString();
        $rows  = [];

        $ambil = function (int $jumlah, int $start) use ($petugasIds, $total) {
            $out = [];
            for ($k = 0; $k < $jumlah; $k++) {
                $out[] = $petugasIds[($start + $k) % $total];
            }

            return $out;
        };

        $pplIds = $ambil($nPpl, $offset);
        $pmlIds = $ambil($nPml, $offset + $nPpl);

        foreach ($pplIds as $g => $pid) {
            $rows[] = [
                'kegiatan_id' => $kegiatanId, 'petugas_id' => $pid,
                'peran' => 'ppl', 'label' => 'PPL '.($g + 1), 'group_id' => $g,
                'created_at' => $now,
            ];
        }
        foreach ($pmlIds as $g => $pid) {
            $rows[] = [
                'kegiatan_id' => $kegiatanId, 'petugas_id' => $pid,
                'peran' => 'pml', 'label' => 'PML '.($g + 1), 'group_id' => $g,
                'created_at' => $now,
            ];
        }

        if (! empty($rows)) {
            DB::table('kegiatan_petugas')->insert($rows);
        }
    }
}
