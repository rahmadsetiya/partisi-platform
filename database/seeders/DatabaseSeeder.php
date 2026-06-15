<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();
        $password = Hash::make('password');

        $users = [
            ['name' => 'Administrator', 'email' => 'admin@test.com', 'role' => 'admin', 'satker' => 'BPS Kabupaten Enrekang'],
            ['name' => 'Koordinator Lapangan', 'email' => 'koordinator@test.com', 'role' => 'koordinator', 'satker' => 'BPS Kabupaten Enrekang'],
            ['name' => 'Rahmat Hidayat', 'email' => 'rahmat@test.com', 'role' => 'koordinator', 'satker' => 'BPS Kabupaten Enrekang'],
            ['name' => 'Dewi Anggraini', 'email' => 'dewi@test.com', 'role' => 'koordinator', 'satker' => 'BPS Kabupaten Enrekang'],
        ];

        foreach ($users as $u) {
            DB::table('users')->insert([
                ...$u,
                'password' => $password,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Urutan penting: petugas & user admin harus ada sebelum kegiatan (FK + assign)
        $this->call([
            PetugasSeeder::class,
            KegiatanSeeder::class,
        ]);
    }
}
