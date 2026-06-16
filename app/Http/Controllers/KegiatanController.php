<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKegiatanRequest;
use App\Http\Requests\UpdateKegiatanRequest;
use App\Models\GeojsonUpload;
use App\Models\Kegiatan;
use App\Models\KegiatanOverride;
use App\Models\KegiatanPetugas;
use App\Models\KegiatanWilayah;
use App\Models\Petugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KegiatanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $kegiatan = Kegiatan::with('creator')
            ->when($user->role !== 'admin', fn ($q) => $q->where('satker', $user->satker))
            ->orderBy('tahun', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Kegiatan/Index', [
            'kegiatan' => $kegiatan,
        ]);
    }

    public function create()
    {
        return Inertia::render('Kegiatan/Create');
    }

    public function store(StoreKegiatanRequest $request)
    {
        Kegiatan::create([
            ...$request->validated(),
            'satker' => $request->user()->satker,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('kegiatan.index')
            ->with('success', 'Kegiatan berhasil dibuat.');
    }

    public function show(Kegiatan $kegiatan)
    {
        $this->authorize('view', $kegiatan);

        $kegiatan->load(['creator', 'geojsonUploads' => fn ($q) => $q->latest('uploaded_at')->limit(1)]);

        $muatan = $kegiatan->wilayah()
            ->selectRaw('COUNT(*) as total, COUNT(muatan) as terisi, COALESCE(SUM(muatan),0) as total_muatan')
            ->first();

        $petugas = KegiatanPetugas::where('kegiatan_id', $kegiatan->id)
            ->with('petugas:id,nama,nip')
            ->orderBy('group_id')
            ->get(['id', 'petugas_id', 'peran', 'label', 'group_id']);

        $user = request()->user();
        $assigned = $petugas->pluck('petugas_id')->all();
        $petugasTersedia = Petugas::whereNotIn('id', $assigned)
            ->when($user->role !== 'admin', fn ($q) => $q->where('satker', $user->satker))
            ->orderBy('nama')
            ->get(['id', 'nama', 'nip', 'jenis']);

        $sesiFinal = $kegiatan->sesiPartisi()
            ->where('status', 'final')
            ->withCount('detail')
            ->latest('finalized_at')
            ->first(['id', 'nama', 'cv', 'finalized_at']);

        return Inertia::render('Kegiatan/Show', [
            'kegiatan' => $kegiatan,
            'jumlahWilayah' => (int) $muatan->total,
            'muatanTerisi' => (int) $muatan->terisi,
            'totalMuatan' => (int) $muatan->total_muatan,
            'petugasPpl' => $petugas->where('peran', 'ppl')->values(),
            'petugasPml' => $petugas->where('peran', 'pml')->values(),
            'petugasTersedia' => $petugasTersedia,
            'jumlahSesi' => (int) $kegiatan->sesiPartisi()->count(),
            'sesiFinal' => $sesiFinal,
            'jumlahOverride' => (int) $kegiatan->overrides()->count(),
            'terkunci' => $kegiatan->adaPartisiFinal(),
        ]);
    }

    public function edit(Kegiatan $kegiatan)
    {
        $this->authorize('update', $kegiatan);

        return Inertia::render('Kegiatan/Edit', [
            'kegiatan' => $kegiatan,
        ]);
    }

    public function update(UpdateKegiatanRequest $request, Kegiatan $kegiatan)
    {
        $this->authorize('update', $kegiatan);

        $kegiatan->update($request->validated());

        return redirect()->route('kegiatan.show', $kegiatan)
            ->with('success', 'Kegiatan berhasil diperbarui.');
    }

    public function destroy(Kegiatan $kegiatan)
    {
        $this->authorize('delete', $kegiatan);

        $kegiatan->delete();

        return redirect()->route('kegiatan.index')
            ->with('success', 'Kegiatan berhasil dihapus.');
    }

    /**
     * Duplikasi kegiatan sebagai draft baru (template). Hasil partisi
     * (sesi_partisi / partisi_detail) tidak pernah ikut — kegiatan baru
     * mulai bersih. Wilayah/petugas/koneksi opsional via checkbox modal.
     */
    public function duplicate(Request $request, Kegiatan $kegiatan)
    {
        $this->authorize('view', $kegiatan);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'salin_wilayah' => ['boolean'],
            'salin_petugas' => ['boolean'],
            'salin_koneksi' => ['boolean'],
        ]);

        $baru = DB::transaction(function () use ($kegiatan, $data, $request) {
            $userId = $request->user()->id;

            $baru = Kegiatan::create([
                'nama' => $data['nama'],
                'jenis' => $kegiatan->jenis,
                'tahun' => $kegiatan->tahun,
                'gelombang' => $kegiatan->gelombang,
                'tanggal_mulai' => $kegiatan->tanggal_mulai,
                'tanggal_selesai' => $kegiatan->tanggal_selesai,
                'deskripsi' => $kegiatan->deskripsi,
                'status' => 'draft',
                'satker' => $kegiatan->satker,
                'created_by' => $userId,
            ]);

            if ($request->boolean('salin_wilayah')) {
                $wilayah = KegiatanWilayah::where('kegiatan_id', $kegiatan->id)
                    ->get(['subsls_id', 'muatan', 'muatan_col'])
                    ->map(fn ($w) => [
                        'kegiatan_id' => $baru->id,
                        'subsls_id' => $w->subsls_id,
                        'muatan' => $w->muatan,
                        'muatan_col' => $w->muatan_col,
                    ])->all();
                foreach (array_chunk($wilayah, 500) as $chunk) {
                    KegiatanWilayah::insert($chunk);
                }

                // Salin metadata riwayat upload (file fisik dipakai bersama).
                foreach (GeojsonUpload::where('kegiatan_id', $kegiatan->id)->get() as $u) {
                    GeojsonUpload::create([
                        'kegiatan_id' => $baru->id,
                        'level' => $u->level,
                        'nama_file' => $u->nama_file,
                        'path' => $u->path,
                        'muatan_col' => $u->muatan_col,
                        'epsg' => $u->epsg,
                        'jumlah_fitur' => $u->jumlah_fitur,
                        'uploaded_by' => $userId,
                        'uploaded_at' => now(),
                    ]);
                }
            }

            if ($request->boolean('salin_petugas')) {
                $petugas = KegiatanPetugas::where('kegiatan_id', $kegiatan->id)
                    ->get(['petugas_id', 'peran', 'label', 'group_id'])
                    ->map(fn ($p) => [
                        'kegiatan_id' => $baru->id,
                        'petugas_id' => $p->petugas_id,
                        'peran' => $p->peran,
                        'label' => $p->label,
                        'group_id' => $p->group_id,
                    ])->all();
                if ($petugas) {
                    KegiatanPetugas::insert($petugas);
                }
            }

            if ($request->boolean('salin_koneksi')) {
                $koneksi = KegiatanOverride::where('kegiatan_id', $kegiatan->id)
                    ->get(['idsubsls_a', 'idsubsls_b', 'tipe', 'catatan'])
                    ->map(fn ($o) => [
                        'kegiatan_id' => $baru->id,
                        'idsubsls_a' => $o->idsubsls_a,
                        'idsubsls_b' => $o->idsubsls_b,
                        'tipe' => $o->tipe,
                        'catatan' => $o->catatan,
                        'created_by' => $userId,
                    ])->all();
                if ($koneksi) {
                    KegiatanOverride::insert($koneksi);
                }
            }

            return $baru;
        });

        return redirect()->route('kegiatan.show', $baru)
            ->with('success', 'Kegiatan berhasil diduplikasi sebagai draft baru.');
    }

    public function updateStatus(Request $request, Kegiatan $kegiatan)
    {
        $this->authorize('update', $kegiatan);

        $request->validate([
            'status' => ['required', 'in:draft,aktif,selesai'],
        ]);

        // Kegiatan hanya boleh "selesai" jika pembagian wilayah sudah difinalkan.
        if ($request->status === 'selesai' && ! $kegiatan->adaPartisiFinal()) {
            return back()->with('error', 'Kegiatan belum bisa diselesaikan: belum ada sesi partisi yang difinalkan.');
        }

        $kegiatan->update(['status' => $request->status]);

        return redirect()->back()
            ->with('success', 'Status kegiatan berhasil diubah.');
    }
}
