<?php

namespace App\Http\Controllers;

use App\Jobs\JalankanPartisiAuto;
use App\Models\Kegiatan;
use App\Models\SesiPartisi;
use App\Services\Partisi\PartisiRunner;
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
            ->get(['id', 'kegiatan_id', 'nama', 'tipe', 'n_ppl', 'n_pml', 'cv', 'status', 'job_status', 'job_error', 'created_by', 'finalized_at', 'created_at']);

        $ringkasan = $this->ringkasanWilayah($kegiatan);

        return Inertia::render('Kegiatan/Partisi/Index', [
            'kegiatan' => $kegiatan->only('id', 'nama', 'status'),
            'sesiList' => $sesi,
            'jumlahPpl' => (int) $kegiatan->petugas()->where('peran', 'ppl')->count(),
            'jumlahPml' => (int) $kegiatan->petugas()->where('peran', 'pml')->count(),
            'jumlahWilayah' => $ringkasan['total'],
            'totalMuatan' => $ringkasan['total_muatan'],
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

    /** Batas SubSLS untuk eksekusi sinkron; di atas ini di-queue ke background. */
    private const BATAS_SYNC = 500;

    /**
     * Buat sesi auto. ≤BATAS_SYNC SubSLS dijalankan sinkron; selebihnya di-queue
     * (background via cron) dengan status dipantau lewat sesi_partisi.job_status.
     */
    public function storeAuto(Request $request, Kegiatan $kegiatan, PartisiRunner $runner)
    {
        $data = $request->validate([
            'nama' => ['nullable', 'string', 'max:100'],
            'prioritas_desa' => ['boolean'],
        ], [], ['nama' => 'nama sesi']);

        $nPpl = (int) $kegiatan->petugas()->where('peran', 'ppl')->count();
        $nPml = (int) $kegiatan->petugas()->where('peran', 'pml')->count();

        if ($nPpl < 1) {
            return back()->with('error', 'Tambahkan minimal satu PPL sebelum menjalankan partisi auto.');
        }

        $total = (int) DB::table('kegiatan_wilayah as kw')
            ->join('subsls as s', 's.id', '=', 'kw.subsls_id')
            ->where('kw.kegiatan_id', $kegiatan->id)
            ->whereNotNull('s.geometry')
            ->count();

        if ($total < 1) {
            return back()->with('error', 'Kegiatan belum memiliki wilayah kerja (SubSLS) bergeometri.');
        }
        if ($nPpl > $total) {
            return back()->with('error', 'Jumlah PPL melebihi jumlah SubSLS. Kurangi PPL.');
        }

        $antri = $total > self::BATAS_SYNC;

        $sesi = $kegiatan->sesiPartisi()->create([
            'nama' => $data['nama'] ?: 'Auto '.now()->format('d/m H:i'),
            'tipe' => 'auto',
            'n_ppl' => $nPpl,
            'n_pml' => $nPml,
            'status' => 'draft',
            'job_status' => $antri ? 'antri' : 'proses',
            'created_by' => $request->user()->id,
            'config' => [
                'algoritma' => 'balanced-connected-php',
                'restarts' => 6,
                'prioritas_desa' => (bool) ($data['prioritas_desa'] ?? false),
            ],
        ]);

        // Area besar → proses di background.
        if ($antri) {
            JalankanPartisiAuto::dispatch($sesi->id);

            return redirect()->route('kegiatan.partisi.index', $kegiatan->id)
                ->with('success', "Partisi auto untuk {$total} SubSLS sedang diproses di latar belakang. Status akan diperbarui otomatis.");
        }

        // Area kecil/sedang → sinkron.
        try {
            $runner->run($sesi);
            $sesi->update(['job_status' => 'selesai']);
        } catch (\Throwable $e) {
            $sesi->update(['job_status' => 'gagal', 'job_error' => mb_substr($e->getMessage(), 0, 500)]);

            return redirect()->route('kegiatan.partisi.index', $kegiatan->id)
                ->with('error', 'Partisi auto gagal: '.$e->getMessage());
        }

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
            'sesi' => $sesi->only('id', 'nama', 'tipe', 'cv', 'status', 'finalized_at', 'config'),
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

    /**
     * Jalankan ulang algoritma auto pada sesi draft (menimpa assignment lama).
     */
    public function regenerate(Request $request, Kegiatan $kegiatan, SesiPartisi $sesi, PartisiRunner $runner)
    {
        $this->pastikanMilik($kegiatan, $sesi);

        if ($sesi->tipe !== 'auto') {
            return back()->with('error', 'Hanya sesi auto yang bisa dijalankan ulang.');
        }
        if ($sesi->isFinal()) {
            return back()->with('error', 'Sesi sudah final. Kembalikan ke draft dulu untuk menjalankan ulang.');
        }

        $data = $request->validate(['prioritas_desa' => ['boolean']]);

        $nPpl = (int) $kegiatan->petugas()->where('peran', 'ppl')->count();
        if ($nPpl < 1) {
            return back()->with('error', 'Tambahkan minimal satu PPL.');
        }

        $total = (int) DB::table('kegiatan_wilayah as kw')
            ->join('subsls as s', 's.id', '=', 'kw.subsls_id')
            ->where('kw.kegiatan_id', $kegiatan->id)
            ->whereNotNull('s.geometry')
            ->count();

        if ($total < 1) {
            return back()->with('error', 'Kegiatan belum memiliki wilayah kerja (SubSLS) bergeometri.');
        }
        if ($nPpl > $total) {
            return back()->with('error', 'Jumlah PPL melebihi jumlah SubSLS.');
        }

        // Perbarui config (pertahankan prioritas_desa lama bila tak dikirim) + jumlah petugas.
        $config = $sesi->config ?? [];
        $config['prioritas_desa'] = (bool) ($data['prioritas_desa'] ?? ($config['prioritas_desa'] ?? false));
        $config['restarts'] = $config['restarts'] ?? 6;
        $sesi->update([
            'config' => $config,
            'n_ppl' => $nPpl,
            'n_pml' => (int) $kegiatan->petugas()->where('peran', 'pml')->count(),
        ]);

        // Area besar → background.
        if ($total > self::BATAS_SYNC) {
            $sesi->update(['job_status' => 'antri', 'job_error' => null]);
            JalankanPartisiAuto::dispatch($sesi->id);

            return redirect()->route('kegiatan.partisi.index', $kegiatan->id)
                ->with('success', "Partisi ulang untuk {$total} SubSLS sedang diproses di latar belakang.");
        }

        // Sinkron.
        $sesi->update(['job_status' => 'proses', 'job_error' => null]);
        try {
            $runner->run($sesi);
            $sesi->update(['job_status' => 'selesai']);
        } catch (\Throwable $e) {
            $sesi->update(['job_status' => 'gagal', 'job_error' => mb_substr($e->getMessage(), 0, 500)]);

            return back()->with('error', 'Partisi ulang gagal: '.$e->getMessage());
        }

        return redirect()->route('kegiatan.partisi.show', [$kegiatan->id, $sesi->id])
            ->with('success', 'Partisi auto dijalankan ulang (CV '.number_format((float) $sesi->fresh()->cv * 100, 1).'%).');
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
            ->selectRaw('COUNT(*) as total, COUNT(muatan) as terisi, COALESCE(SUM(muatan),0) as total_muatan')
            ->first();

        return [
            'total' => (int) $agg->total,
            'terisi' => (int) $agg->terisi,
            'total_muatan' => (int) $agg->total_muatan,
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
