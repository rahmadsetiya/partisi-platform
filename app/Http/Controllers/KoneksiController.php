<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\KegiatanOverride;
use App\Services\Partisi\AdjacencyBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KoneksiController extends Controller
{
    /**
     * Halaman edit koneksi: peta + adjacency dasar + daftar override.
     */
    public function index(Kegiatan $kegiatan)
    {
        $rows = DB::table('kegiatan_wilayah as kw')
            ->join('subsls as s', 's.id', '=', 'kw.subsls_id')
            ->where('kw.kegiatan_id', $kegiatan->id)
            ->whereNotNull('s.geometry')
            ->select('s.id', 's.idsubsls', 's.geometry', 's.centroid_lat', 's.centroid_lon')
            ->get();

        $subsls = $rows->map(fn ($r) => [
            'id' => $r->id,
            'geometry' => json_decode($r->geometry, true),
            'centroid_lat' => $r->centroid_lat,
            'centroid_lon' => $r->centroid_lon,
        ])->all();

        // Adjacency dasar (tanpa override) → pasangan unik a<b.
        $adj = (new AdjacencyBuilder)->build($subsls);
        $edges = [];
        foreach ($adj as $a => $tetangga) {
            foreach (array_keys($tetangga) as $b) {
                if ($a < $b) {
                    $edges[] = [$a, $b];
                }
            }
        }

        // Override → map idsubsls ke subsls_id.
        $idToSubslsId = $rows->pluck('id', 'idsubsls');
        $overrides = $kegiatan->overrides()
            ->orderByDesc('created_at')
            ->get(['id', 'idsubsls_a', 'idsubsls_b', 'tipe', 'catatan'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'a_id' => $idToSubslsId[$o->idsubsls_a] ?? null,
                'b_id' => $idToSubslsId[$o->idsubsls_b] ?? null,
                'tipe' => $o->tipe,
                'catatan' => $o->catatan,
            ])
            ->values();

        return Inertia::render('Kegiatan/Koneksi/Index', [
            'kegiatan' => $kegiatan->only('id', 'nama'),
            'geojsonUrl' => route('kegiatan.partisi.geojson', $kegiatan->id),
            'edges' => $edges,
            'overrides' => $overrides,
            'jumlahWilayah' => $rows->count(),
        ]);
    }

    /**
     * Simpan override koneksi (force_connect / force_disconnect) antar 2 SubSLS.
     */
    public function store(Request $request, Kegiatan $kegiatan)
    {
        if ($kegiatan->adaPartisiFinal()) {
            return back()->with('error', 'Kegiatan terkunci karena ada sesi partisi final. Kembalikan sesi ke draft dulu untuk mengubah koneksi.');
        }

        $data = $request->validate([
            'a_id' => ['required', 'integer'],
            'b_id' => ['required', 'integer', 'different:a_id'],
            'tipe' => ['required', 'in:force_connect,force_disconnect'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ], [
            'a_id.required' => 'Pilih SubSLS pertama.',
            'b_id.required' => 'Pilih SubSLS kedua.',
            'b_id.different' => 'Kedua SubSLS harus berbeda.',
            'tipe.required' => 'Pilih jenis koneksi.',
            'tipe.in' => 'Jenis koneksi tidak valid.',
        ]);

        // Pastikan kedua SubSLS milik kegiatan ini, ambil idsubsls.
        $map = DB::table('kegiatan_wilayah as kw')
            ->join('subsls as s', 's.id', '=', 'kw.subsls_id')
            ->where('kw.kegiatan_id', $kegiatan->id)
            ->whereIn('s.id', [$data['a_id'], $data['b_id']])
            ->pluck('s.idsubsls', 's.id');

        if (! $map->has($data['a_id']) || ! $map->has($data['b_id'])) {
            return back()->with('error', 'SubSLS tidak ditemukan di kegiatan ini.');
        }

        $idA = $map[$data['a_id']];
        $idB = $map[$data['b_id']];

        // Hapus override lama untuk pasangan yang sama (urutan tak penting).
        $kegiatan->overrides()
            ->where(function ($q) use ($idA, $idB) {
                $q->where(['idsubsls_a' => $idA, 'idsubsls_b' => $idB])
                    ->orWhere(['idsubsls_a' => $idB, 'idsubsls_b' => $idA]);
            })
            ->delete();

        $kegiatan->overrides()->create([
            'idsubsls_a' => $idA,
            'idsubsls_b' => $idB,
            'tipe' => $data['tipe'],
            'catatan' => $data['catatan'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $label = $data['tipe'] === 'force_connect' ? 'disambungkan' : 'diputus';

        return back()->with('success', "Koneksi {$label}. Berlaku saat partisi auto dijalankan.");
    }

    public function destroy(Kegiatan $kegiatan, KegiatanOverride $override)
    {
        abort_unless($override->kegiatan_id === $kegiatan->id, 404);

        if ($kegiatan->adaPartisiFinal()) {
            return back()->with('error', 'Kegiatan terkunci karena ada sesi partisi final. Kembalikan sesi ke draft dulu.');
        }

        $override->delete();

        return back()->with('success', 'Override koneksi dihapus.');
    }
}
