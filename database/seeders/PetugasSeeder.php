<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PetugasSeeder extends Seeder
{
    public function run(): void
    {
        $depan = [
            'Budi', 'Siti', 'Andi', 'Dewi', 'Rizki', 'Putri', 'Agus', 'Indah', 'Hendra', 'Maya',
            'Fajar', 'Ratna', 'Joko', 'Lestari', 'Bayu', 'Wulan', 'Eko', 'Sari', 'Dimas', 'Citra',
            'Yusuf', 'Ayu', 'Rahmat', 'Nita', 'Arif', 'Fitri', 'Hadi', 'Rina', 'Teguh', 'Mega',
            'Surya', 'Anita', 'Galih', 'Yuni', 'Bagas', 'Tika', 'Irfan', 'Dian', 'Reza', 'Nurul',
        ];
        $belakang = [
            'Santoso', 'Pratama', 'Lestari', 'Wijaya', 'Nugroho', 'Saputra', 'Anggraini', 'Hidayat',
            'Kusuma', 'Maulana', 'Permana', 'Wahyuni', 'Setiawan', 'Halim', 'Ramadhan', 'Susanti',
            'Firmansyah', 'Utami', 'Gunawan', 'Pertiwi',
        ];

        $now = now()->toDateTimeString();
        $rows = [];
        $tahunL = [1985, 1988, 1990, 1992, 1995, 1998];

        for ($i = 0; $i < 40; $i++) {
            $nama = $depan[$i].' '.$belakang[$i % count($belakang)];

            // NIP BPS 18 digit: tgllahir(8) + tahunbulan masuk(6) + jeniskelamin(1) + urut(3)
            $thn = $tahunL[$i % count($tahunL)];
            $nip = sprintf('%04d%02d%02d', $thn, ($i % 12) + 1, ($i % 27) + 1)
                  .sprintf('%04d%02d', 2010 + ($i % 12), (($i % 2) === 0 ? 1 : 3))
                  .(($i % 2) === 0 ? '1' : '2')
                  .sprintf('%03d', $i + 1);

            // 10 organik pertama (pegawai BPS, ber-NIP), sisanya mitra.
            $organik = $i < 10;

            $rows[] = [
                'nama' => $nama,
                'jenis' => $organik ? 'organik' : 'mitra',
                'nip' => $organik ? $nip : null,
                'telepon' => '0812'.sprintf('%08d', 10000000 + $i * 137),
                'satker' => 'BPS Kabupaten Enrekang',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('petugas')->insert($chunk);
        }
    }
}
