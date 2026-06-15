<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePetugasRequest;
use App\Http\Requests\UpdatePetugasRequest;
use App\Models\Petugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PetugasController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $user = $request->user();

        $petugas = Petugas::query()
            ->withCount('kegiatanPetugas')
            ->when($user->role !== 'admin', fn ($query) => $query->where('satker', $user->satker))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('nama', 'like', "%{$q}%")
                        ->orWhere('nip', 'like', "%{$q}%")
                        ->orWhere('satker', 'like', "%{$q}%");
                });
            })
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        $this->lampirkanBeban($petugas->getCollection());

        return Inertia::render('Petugas/Index', [
            'petugas' => $petugas,
            'filters' => ['q' => $q],
        ]);
    }

    /**
     * Lampirkan beban lintas kegiatan ke koleksi petugas:
     * aktif_count (jml kegiatan aktif) & muatan_final (beban PPL dari sesi final).
     */
    private function lampirkanBeban($collection): void
    {
        $ids = $collection->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $aktif = DB::table('kegiatan_petugas as kp')
            ->join('kegiatan as k', 'k.id', '=', 'kp.kegiatan_id')
            ->where('k.status', 'aktif')
            ->whereIn('kp.petugas_id', $ids)
            ->groupBy('kp.petugas_id')
            ->selectRaw('kp.petugas_id, COUNT(DISTINCT kp.kegiatan_id) as c')
            ->pluck('c', 'kp.petugas_id');

        $muatan = DB::table('partisi_detail as pd')
            ->join('sesi_partisi as sp', 'sp.id', '=', 'pd.sesi_partisi_id')
            ->where('sp.status', 'final')
            ->join('kegiatan_petugas as kp', 'kp.id', '=', 'pd.ppl_id')
            ->join('kegiatan_wilayah as kw', function ($j) {
                $j->on('kw.subsls_id', '=', 'pd.subsls_id')->on('kw.kegiatan_id', '=', 'sp.kegiatan_id');
            })
            ->whereIn('kp.petugas_id', $ids)
            ->groupBy('kp.petugas_id')
            ->selectRaw('kp.petugas_id, COALESCE(SUM(kw.muatan),0) as m')
            ->pluck('m', 'kp.petugas_id');

        $collection->transform(function ($p) use ($aktif, $muatan) {
            $p->aktif_count = (int) ($aktif[$p->id] ?? 0);
            $p->muatan_final = (int) ($muatan[$p->id] ?? 0);

            return $p;
        });
    }

    public function show(Petugas $petugas)
    {
        $this->authorize('view', $petugas);

        $riwayat = DB::table('kegiatan_petugas as kp')
            ->join('kegiatan as k', 'k.id', '=', 'kp.kegiatan_id')
            ->where('kp.petugas_id', $petugas->id)
            ->orderByDesc('k.tahun')
            ->orderByDesc('k.created_at')
            ->select('kp.id as kp_id', 'k.id as kegiatan_id', 'k.nama', 'k.jenis', 'k.tahun', 'k.gelombang', 'k.status', 'kp.peran', 'kp.label')
            ->get();

        // Beban final (jml SubSLS + muatan) per penugasan PPL.
        $beban = DB::table('partisi_detail as pd')
            ->join('sesi_partisi as sp', 'sp.id', '=', 'pd.sesi_partisi_id')
            ->where('sp.status', 'final')
            ->join('kegiatan_wilayah as kw', function ($j) {
                $j->on('kw.subsls_id', '=', 'pd.subsls_id')->on('kw.kegiatan_id', '=', 'sp.kegiatan_id');
            })
            ->whereIn('pd.ppl_id', $riwayat->pluck('kp_id'))
            ->groupBy('pd.ppl_id')
            ->selectRaw('pd.ppl_id, COUNT(*) as jml, COALESCE(SUM(kw.muatan),0) as muatan')
            ->get()
            ->keyBy('ppl_id');

        $riwayat = $riwayat->map(fn ($r) => [
            'kegiatan_id' => $r->kegiatan_id,
            'nama' => $r->nama,
            'jenis' => $r->jenis,
            'tahun' => $r->tahun,
            'gelombang' => $r->gelombang,
            'status' => $r->status,
            'peran' => $r->peran,
            'label' => $r->label,
            'jml_subsls' => (int) ($beban[$r->kp_id]->jml ?? 0),
            'muatan' => (int) ($beban[$r->kp_id]->muatan ?? 0),
        ]);

        return Inertia::render('Petugas/Show', [
            'petugas' => $petugas->only('id', 'nama', 'nip', 'telepon', 'satker'),
            'riwayat' => $riwayat,
        ]);
    }

    public function store(StorePetugasRequest $request)
    {
        $data = $request->validated();
        // Koordinator hanya boleh menambah petugas di satker-nya.
        if ($request->user()->role !== 'admin') {
            $data['satker'] = $request->user()->satker;
        }

        Petugas::create($data);

        return back()->with('success', 'Petugas berhasil ditambahkan.');
    }

    public function update(UpdatePetugasRequest $request, Petugas $petugas)
    {
        $this->authorize('update', $petugas);

        $data = $request->validated();
        if ($request->user()->role !== 'admin') {
            $data['satker'] = $request->user()->satker;
        }

        $petugas->update($data);

        return back()->with('success', 'Petugas berhasil diperbarui.');
    }

    public function destroy(Petugas $petugas)
    {
        $this->authorize('delete', $petugas);

        if ($petugas->kegiatanPetugas()->exists()) {
            return back()->with('error', 'Petugas tidak bisa dihapus karena masih ditugaskan di kegiatan.');
        }

        $petugas->delete();

        return back()->with('success', 'Petugas berhasil dihapus.');
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.nama' => ['required', 'string', 'max:100'],
            'rows.*.jenis' => ['nullable', 'in:organik,mitra'],
            'rows.*.nip' => ['nullable', 'string', 'max:30'],
            'rows.*.telepon' => ['nullable', 'string', 'max:20'],
            'rows.*.satker' => ['nullable', 'string', 'max:100'],
        ]);

        // NIP yang sudah ada → dilewati agar tidak duplikat
        $nipAda = Petugas::whereNotNull('nip')->pluck('nip')->flip();

        $now = now()->toDateTimeString();
        $insert = [];
        $dilewati = 0;
        $nipBatch = [];

        foreach ($data['rows'] as $row) {
            $nip = $row['nip'] ?? null;
            if ($nip !== null && $nip !== '' && ($nipAda->has($nip) || isset($nipBatch[$nip]))) {
                $dilewati++;

                continue;
            }
            if ($nip) {
                $nipBatch[$nip] = true;
            }

            $insert[] = [
                'nama' => $row['nama'],
                'jenis' => ($row['jenis'] ?? null) === 'organik' ? 'organik' : 'mitra',
                'nip' => $nip ?: null,
                'telepon' => $row['telepon'] ?? null,
                'satker' => $request->user()->role !== 'admin' ? $request->user()->satker : ($row['satker'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($insert)) {
            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('petugas')->insert($chunk);
            }
        }

        $pesan = count($insert).' petugas berhasil diimport';
        $pesan .= $dilewati > 0 ? ", {$dilewati} dilewati (NIP duplikat)." : '.';

        return redirect()->route('petugas.index')->with('success', $pesan);
    }
}
