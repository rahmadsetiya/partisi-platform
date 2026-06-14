<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\SesiPartisi;
use App\Services\Partisi\AdjacencyBuilder;
use App\Services\Partisi\BalancedPartitioner;
use App\Services\Partisi\PmlGrouper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SesiPartisiController extends Controller
{
    /**
     * Daftar sesi partisi sebuah kegiatan.
     */
    public function index(Kegiatan $kegiatan)
    {
        $sesi = $kegiatan->sesiPartisi()
            ->with('creator:id,name')
            ->withCount('detail')
            ->orderByDesc('created_at')
            ->get(['id', 'kegiatan_id', 'nama', 'tipe', 'n_ppl', 'n_pml', 'cv', 'status', 'created_by', 'finalized_at', 'created_at']);

        $ringkasan = $this->ringkasanWilayah($kegiatan);

        return Inertia::render('Kegiatan/Partisi/Index', [
            'kegiatan' => $kegiatan->only('id', 'nama', 'status'),
            'sesiList' => $sesi,
            'jumlahPpl' => (int) $kegiatan->petugas()->where('peran', 'ppl')->count(),
            'jumlahPml' => (int) $kegiatan->petugas()->where('peran', 'pml')->count(),
            'jumlahWilayah' => $ringkasan['total'],
            'muatanLengkap' => $ringkasan['total'] > 0 && $ringkasan['terisi'] === $ringkasan['total'],
        ]);
    }

    /**
     * Buat sesi partisi manual baru (draft).
     */
    public function store(Request $request, Kegiatan $kegiatan)
    {
        $data = $request->validate([
            'nama' => ['nullable', 'string', 'max:100'],
        ], [], ['nama' => 'nama sesi']);

        $nPpl = (int) $kegiatan->petugas()->where('peran', 'ppl')->count();
        $nPml = (int) $kegiatan->petugas()->where('peran', 'pml')->count();

        if ($nPpl < 1) {
            return back()->with('error', 'Tambahkan minimal satu PPL sebelum membuat sesi partisi.');
        }

        $ringkasan = $this->ringkasanWilayah($kegiatan);
        if ($ringkasan['total'] < 1) {
            return back()->with('error', 'Kegiatan belum memiliki wilayah kerja (SubSLS).');
        }

        $sesi = $kegiatan->sesiPartisi()->create([
            'nama' => $data['nama'] ?: 'Sesi '.now()->format('d/m H:i'),
            'tipe' => 'manual',
            'n_ppl' => $nPpl,
            'n_pml' => $nPml,
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('kegiatan.partisi.show', [$kegiatan->id, $sesi->id])
            ->with('success', 'Sesi partisi dibuat. Mulai bagi wilayah ke PPL.');
    }

    /** Batas aman jumlah SubSLS untuk eksekusi auto sinkron (hindari timeout PHP). */
    private const BATAS_AUTO = 600;

    /**
     * Buat sesi auto: jalankan algoritma partisi (PHP) lalu simpan hasilnya sebagai draft.
     */
    public function storeAuto(Request $request, Kegiatan $kegiatan)
    {
        $data = $request->validate([
            'nama' => ['nullable', 'string', 'max:100'],
            'prioritas_desa' => ['boolean'],
        ], [], ['nama' => 'nama sesi']);

        // PPL/PML urut group_id → indeks grup algoritma dipetakan ke id ini.
        $pplList = $kegiatan->petugas()->where('peran', 'ppl')->orderBy('group_id')->get(['id'])->pluck('id')->all();
        $pmlList = $kegiatan->petugas()->where('peran', 'pml')->orderBy('group_id')->get(['id'])->pluck('id')->all();
        $nPpl = count($pplList);
        $nPml = count($pmlList);

        if ($nPpl < 1) {
            return back()->with('error', 'Tambahkan minimal satu PPL sebelum menjalankan partisi auto.');
        }

        // Ambil wilayah + geometri + muatan.
        $rows = DB::table('kegiatan_wilayah as kw')
            ->join('subsls as s', 's.id', '=', 'kw.subsls_id')
            ->where('kw.kegiatan_id', $kegiatan->id)
            ->whereNotNull('s.geometry')
            ->select('s.id', 's.idsubsls', 's.geometry', 's.centroid_lat', 's.centroid_lon', 's.nmdesa', 'kw.muatan')
            ->get();

        if ($rows->isEmpty()) {
            return back()->with('error', 'Kegiatan belum memiliki wilayah kerja (SubSLS) bergeometri.');
        }
        if ($rows->count() > self::BATAS_AUTO) {
            return back()->with('error', 'Jumlah SubSLS ('.$rows->count().') melebihi batas auto ('.self::BATAS_AUTO.'). Perkecil cakupan wilayah atau bagi secara manual.');
        }
        if ($nPpl > $rows->count()) {
            return back()->with('error', 'Jumlah PPL melebihi jumlah SubSLS. Kurangi PPL.');
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

        $prioritasDesa = (bool) ($data['prioritas_desa'] ?? false);

        // Jalankan algoritma.
        $adjacency = (new AdjacencyBuilder)->build($subsls, $overrides);
        $partitioner = new BalancedPartitioner(
            loads: $loads,
            adjacency: $adjacency,
            nGroups: $nPpl,
            restarts: 6,
            desaMap: $prioritasDesa ? $desaMap : [],
            desaPenalty: $prioritasDesa ? 500.0 : 0.0,
        );
        $hasil = $partitioner->run();
        $partition = $hasil['partition']; // subsls_id => group (0-based)

        // PML auto: centroid rata-rata tiap PPL → kelompokkan.
        $pmlForGroup = [];
        if ($nPml > 0) {
            $pplCentroid = [];
            $acc = [];
            foreach ($partition as $sid => $g) {
                $acc[$g]['lat'] = ($acc[$g]['lat'] ?? 0) + $centroid[$sid]['lat'];
                $acc[$g]['lon'] = ($acc[$g]['lon'] ?? 0) + $centroid[$sid]['lon'];
                $acc[$g]['n'] = ($acc[$g]['n'] ?? 0) + 1;
            }
            foreach ($acc as $g => $a) {
                $pplCentroid[$g] = ['lat' => $a['lat'] / $a['n'], 'lon' => $a['lon'] / $a['n']];
            }
            $pmlForGroup = (new PmlGrouper)->group($pplCentroid, $nPml); // group => pmlIndex
        }

        // Simpan sesi + detail.
        $sesi = $kegiatan->sesiPartisi()->create([
            'nama' => $data['nama'] ?: 'Auto '.now()->format('d/m H:i'),
            'tipe' => 'auto',
            'n_ppl' => $nPpl,
            'n_pml' => $nPml,
            'status' => 'draft',
            'created_by' => $request->user()->id,
            'config' => [
                'algoritma' => 'balanced-connected-php',
                'restarts' => 6,
                'prioritas_desa' => $prioritasDesa,
                'gap' => $hasil['gap'],
            ],
        ]);

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

        DB::transaction(function () use ($sesi, $detailRows, $kegiatan) {
            foreach (array_chunk($detailRows, 500) as $chunk) {
                DB::table('partisi_detail')->insert($chunk);
            }
            $sesi->update(['cv' => $this->hitungCv($sesi, $kegiatan)]);
        });

        return redirect()->route('kegiatan.partisi.show', [$kegiatan->id, $sesi->id])
            ->with('success', 'Partisi auto selesai (CV '.number_format((float) $sesi->fresh()->cv * 100, 1).'%). Tinjau & poles bila perlu.');
    }

    /**
     * Halaman kerja partisi manual.
     */
    public function show(Kegiatan $kegiatan, SesiPartisi $sesi)
    {
        $this->pastikanMilik($kegiatan, $sesi);

        $ppl = $kegiatan->petugas()->where('peran', 'ppl')
            ->with('petugas:id,nama,nip')
            ->orderBy('group_id')
            ->get(['id', 'petugas_id', 'label', 'group_id']);

        $pml = $kegiatan->petugas()->where('peran', 'pml')
            ->with('petugas:id,nama,nip')
            ->orderBy('group_id')
            ->get(['id', 'petugas_id', 'label', 'group_id']);

        // assignment saat ini: subsls_id => {ppl_id, pml_id}
        $assignments = $sesi->detail()
            ->get(['subsls_id', 'ppl_id', 'pml_id'])
            ->mapWithKeys(fn ($d) => [$d->subsls_id => ['ppl_id' => $d->ppl_id, 'pml_id' => $d->pml_id]]);

        $ringkasan = $this->ringkasanWilayah($kegiatan);

        return Inertia::render('Kegiatan/Partisi/Edit', [
            'kegiatan' => $kegiatan->only('id', 'nama', 'status'),
            'sesi' => $sesi->only('id', 'nama', 'tipe', 'cv', 'status', 'finalized_at'),
            'ppl' => $ppl,
            'pml' => $pml,
            'assignments' => $assignments,
            'jumlahWilayah' => $ringkasan['total'],
            'geojsonUrl' => route('kegiatan.partisi.geojson', $kegiatan->id),
        ]);
    }

    /**
     * Halaman hasil partisi (read-only) — sumber data untuk export Excel & cetak PDF.
     */
    public function hasil(Kegiatan $kegiatan, SesiPartisi $sesi)
    {
        $this->pastikanMilik($kegiatan, $sesi);

        $rows = DB::table('partisi_detail as pd')
            ->join('subsls as s', 's.id', '=', 'pd.subsls_id')
            ->leftJoin('kegiatan_wilayah as kw', function ($j) use ($kegiatan) {
                $j->on('kw.subsls_id', '=', 'pd.subsls_id')
                    ->where('kw.kegiatan_id', '=', $kegiatan->id);
            })
            ->join('kegiatan_petugas as kpp', 'kpp.id', '=', 'pd.ppl_id')
            ->join('petugas as pp', 'pp.id', '=', 'kpp.petugas_id')
            ->leftJoin('kegiatan_petugas as kpm', 'kpm.id', '=', 'pd.pml_id')
            ->leftJoin('petugas as pm', 'pm.id', '=', 'kpm.petugas_id')
            ->where('pd.sesi_partisi_id', $sesi->id)
            ->orderBy('kpp.group_id')
            ->orderBy('s.idsubsls')
            ->select(
                's.idsubsls', 's.nmkec', 's.nmdesa', 's.nmsls', 'kw.muatan',
                'kpp.label as ppl_label', 'pp.nama as ppl_nama', 'pp.nip as ppl_nip',
                'kpm.label as pml_label', 'pm.nama as pml_nama',
                'kpp.group_id'
            )
            ->get();

        // Ringkasan beban per PPL (urut group_id), termasuk PPL tanpa assignment.
        $semuaPpl = $kegiatan->petugas()->where('peran', 'ppl')
            ->with('petugas:id,nama,nip')
            ->orderBy('group_id')
            ->get(['id', 'petugas_id', 'label', 'group_id']);

        $aggByLabel = $rows->groupBy('ppl_label')->map(fn ($g) => [
            'jumlah' => $g->count(),
            'muatan' => (int) $g->sum('muatan'),
        ]);

        $ringkasan = $semuaPpl->map(fn ($p) => [
            'label' => $p->label,
            'nama' => $p->petugas?->nama,
            'pml' => optional($rows->firstWhere('ppl_label', $p->label))->pml_label,
            'jumlah' => $aggByLabel[$p->label]['jumlah'] ?? 0,
            'muatan' => $aggByLabel[$p->label]['muatan'] ?? 0,
        ])->values();

        return Inertia::render('Kegiatan/Partisi/Hasil', [
            'kegiatan' => $kegiatan->only('id', 'nama', 'jenis', 'tahun', 'gelombang'),
            'sesi' => $sesi->only('id', 'nama', 'tipe', 'cv', 'status', 'finalized_at'),
            'rows' => $rows,
            'ringkasan' => $ringkasan,
            'totalMuatan' => (int) $rows->sum('muatan'),
        ]);
    }

    /**
     * Endpoint GeoJSON SubSLS sebuah kegiatan (di-fetch async oleh peta).
     */
    public function geojson(Kegiatan $kegiatan)
    {
        $rows = DB::table('kegiatan_wilayah as kw')
            ->join('subsls as s', 's.id', '=', 'kw.subsls_id')
            ->where('kw.kegiatan_id', $kegiatan->id)
            ->whereNotNull('s.geometry')
            ->select('s.id', 's.idsubsls', 's.nmkec', 's.nmdesa', 's.nmsls',
                's.centroid_lat', 's.centroid_lon', 's.geometry', 'kw.muatan')
            ->get();

        $features = $rows->map(function ($r) {
            $geometry = json_decode($r->geometry, true);
            if (! $geometry) {
                return null;
            }

            return [
                'type' => 'Feature',
                'geometry' => $geometry,
                'properties' => [
                    'id' => (int) $r->id,
                    'idsubsls' => $r->idsubsls,
                    'nmkec' => $r->nmkec,
                    'nmdesa' => $r->nmdesa,
                    'nmsls' => $r->nmsls,
                    'muatan' => $r->muatan !== null ? (int) $r->muatan : null,
                    'centroid_lat' => $r->centroid_lat !== null ? (float) $r->centroid_lat : null,
                    'centroid_lon' => $r->centroid_lon !== null ? (float) $r->centroid_lon : null,
                ],
            ];
        })->filter()->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Simpan assignment SubSLS -> PPL (+PML opsional) untuk sesi draft.
     */
    public function saveAssignments(Request $request, Kegiatan $kegiatan, SesiPartisi $sesi)
    {
        $this->pastikanMilik($kegiatan, $sesi);

        if ($sesi->isFinal()) {
            return back()->with('error', 'Sesi sudah final dan tidak bisa diubah.');
        }

        $data = $request->validate([
            'assignments' => ['present', 'array'],
            'assignments.*.subsls_id' => ['required', 'integer'],
            'assignments.*.ppl_id' => ['required', 'integer'],
            'assignments.*.pml_id' => ['nullable', 'integer'],
        ]);

        // Set valid: subsls_id milik kegiatan, ppl_id/pml_id milik kegiatan dgn peran benar.
        $validSubsls = DB::table('kegiatan_wilayah')
            ->where('kegiatan_id', $kegiatan->id)->pluck('subsls_id')->flip();
        $validPpl = $kegiatan->petugas()->where('peran', 'ppl')->pluck('id')->flip();
        $validPml = $kegiatan->petugas()->where('peran', 'pml')->pluck('id')->flip();

        $rows = [];
        foreach ($data['assignments'] as $a) {
            if (! $validSubsls->has($a['subsls_id']) || ! $validPpl->has($a['ppl_id'])) {
                continue;
            }
            $pmlId = $a['pml_id'] ?? null;
            if ($pmlId !== null && ! $validPml->has($pmlId)) {
                $pmlId = null;
            }
            $rows[] = [
                'sesi_partisi_id' => $sesi->id,
                'subsls_id' => $a['subsls_id'],
                'ppl_id' => $a['ppl_id'],
                'pml_id' => $pmlId,
            ];
        }

        DB::transaction(function () use ($sesi, $rows, $kegiatan) {
            $sesi->detail()->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('partisi_detail')->insert($chunk);
            }
            $sesi->update(['cv' => $this->hitungCv($sesi, $kegiatan)]);
        });

        return back()->with('success', count($rows).' SubSLS tersimpan ke sesi.');
    }

    /**
     * Finalkan sesi: semua SubSLS harus ter-assign, hanya satu final per kegiatan.
     */
    public function finalize(Kegiatan $kegiatan, SesiPartisi $sesi)
    {
        $this->pastikanMilik($kegiatan, $sesi);

        $total = (int) $kegiatan->wilayah()->count();
        $terassign = (int) $sesi->detail()->count();

        if ($terassign < $total) {
            $sisa = $total - $terassign;

            return back()->with('error', "Masih ada {$sisa} SubSLS yang belum dibagi. Lengkapi dulu sebelum finalkan.");
        }

        $adaFinal = $kegiatan->sesiPartisi()
            ->where('status', 'final')
            ->where('id', '!=', $sesi->id)
            ->exists();

        if ($adaFinal) {
            return back()->with('error', 'Sudah ada sesi final untuk kegiatan ini. Kembalikan sesi final lama ke draft dulu.');
        }

        $sesi->update(['status' => 'final', 'finalized_at' => now()]);

        return back()->with('success', 'Sesi partisi difinalkan.');
    }

    /**
     * Kembalikan sesi final ke draft (agar bisa diedit / pindah final).
     */
    public function reopen(Kegiatan $kegiatan, SesiPartisi $sesi)
    {
        $this->pastikanMilik($kegiatan, $sesi);

        $sesi->update(['status' => 'draft', 'finalized_at' => null]);

        return back()->with('success', 'Sesi dikembalikan ke draft.');
    }

    public function destroy(Kegiatan $kegiatan, SesiPartisi $sesi)
    {
        $this->pastikanMilik($kegiatan, $sesi);

        $sesi->delete(); // cascade ke partisi_detail

        return redirect()->route('kegiatan.partisi.index', $kegiatan->id)
            ->with('success', 'Sesi partisi dihapus.');
    }

    /**
     * Pastikan sesi memang milik kegiatan di URL.
     */
    private function pastikanMilik(Kegiatan $kegiatan, SesiPartisi $sesi): void
    {
        abort_unless($sesi->kegiatan_id === $kegiatan->id, 404);
    }

    /**
     * Ringkasan jumlah & kelengkapan muatan wilayah.
     */
    private function ringkasanWilayah(Kegiatan $kegiatan): array
    {
        $agg = $kegiatan->wilayah()
            ->selectRaw('COUNT(*) as total, COUNT(muatan) as terisi')
            ->first();

        return [
            'total' => (int) $agg->total,
            'terisi' => (int) $agg->terisi,
        ];
    }

    /**
     * Coefficient of Variation total muatan antar PPL (kualitas keseimbangan).
     * CV = stddev / mean. Semua PPL dihitung (yang kosong = beban 0).
     */
    private function hitungCv(SesiPartisi $sesi, Kegiatan $kegiatan): ?float
    {
        $nPpl = (int) $kegiatan->petugas()->where('peran', 'ppl')->count();
        if ($nPpl < 1) {
            return null;
        }

        // total muatan per ppl_id (hanya PPL yang punya assignment)
        $beban = DB::table('partisi_detail as pd')
            ->join('kegiatan_wilayah as kw', function ($j) use ($kegiatan) {
                $j->on('kw.subsls_id', '=', 'pd.subsls_id')
                    ->where('kw.kegiatan_id', '=', $kegiatan->id);
            })
            ->where('pd.sesi_partisi_id', $sesi->id)
            ->groupBy('pd.ppl_id')
            ->selectRaw('COALESCE(SUM(kw.muatan),0) as total')
            ->pluck('total')
            ->map(fn ($v) => (float) $v)
            ->all();

        // lengkapi PPL tanpa assignment dengan 0
        while (count($beban) < $nPpl) {
            $beban[] = 0.0;
        }

        $mean = array_sum($beban) / count($beban);
        if ($mean <= 0) {
            return null;
        }

        $varian = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $beban)) / count($beban);

        return round(sqrt($varian) / $mean, 4);
    }
}
