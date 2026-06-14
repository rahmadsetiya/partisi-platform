<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\Petugas;
use App\Models\Subsls;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $perStatus = Kegiatan::selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $stat = [
            'kegiatan_total' => (int) $perStatus->sum(),
            'kegiatan_draft' => (int) ($perStatus['draft'] ?? 0),
            'kegiatan_aktif' => (int) ($perStatus['aktif'] ?? 0),
            'kegiatan_selesai' => (int) ($perStatus['selesai'] ?? 0),
            'petugas_total' => (int) Petugas::count(),
            'subsls_total' => (int) Subsls::count(),
        ];

        $kegiatanAktif = Kegiatan::where('status', 'aktif')
            ->withCount([
                'wilayah',
                'petugas as ppl_count' => fn ($q) => $q->where('peran', 'ppl'),
                'sesiPartisi as sesi_count',
                'sesiPartisi as final_count' => fn ($q) => $q->where('status', 'final'),
            ])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'nama', 'jenis', 'tahun', 'gelombang']);

        return Inertia::render('Dashboard', [
            'stat' => $stat,
            'kegiatanAktif' => $kegiatanAktif,
        ]);
    }
}
