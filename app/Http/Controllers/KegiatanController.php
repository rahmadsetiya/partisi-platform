<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKegiatanRequest;
use App\Http\Requests\UpdateKegiatanRequest;
use App\Models\Kegiatan;
use App\Models\KegiatanPetugas;
use App\Models\Petugas;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KegiatanController extends Controller
{
    public function index()
    {
        $kegiatan = Kegiatan::with('creator')
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
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('kegiatan.index')
            ->with('success', 'Kegiatan berhasil dibuat.');
    }

    public function show(Kegiatan $kegiatan)
    {
        $kegiatan->load(['creator', 'geojsonUploads' => fn ($q) => $q->latest('uploaded_at')->limit(1)]);

        $muatan = $kegiatan->wilayah()
            ->selectRaw('COUNT(*) as total, COUNT(muatan) as terisi, COALESCE(SUM(muatan),0) as total_muatan')
            ->first();

        $petugas = KegiatanPetugas::where('kegiatan_id', $kegiatan->id)
            ->with('petugas:id,nama,nip')
            ->orderBy('group_id')
            ->get(['id', 'petugas_id', 'peran', 'label', 'group_id']);

        $assigned = $petugas->pluck('petugas_id')->all();
        $petugasTersedia = Petugas::whereNotIn('id', $assigned)
            ->orderBy('nama')
            ->get(['id', 'nama', 'nip']);

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
        ]);
    }

    public function edit(Kegiatan $kegiatan)
    {
        return Inertia::render('Kegiatan/Edit', [
            'kegiatan' => $kegiatan,
        ]);
    }

    public function update(UpdateKegiatanRequest $request, Kegiatan $kegiatan)
    {
        $kegiatan->update($request->validated());

        return redirect()->route('kegiatan.show', $kegiatan)
            ->with('success', 'Kegiatan berhasil diperbarui.');
    }

    public function destroy(Kegiatan $kegiatan)
    {
        $kegiatan->delete();

        return redirect()->route('kegiatan.index')
            ->with('success', 'Kegiatan berhasil dihapus.');
    }

    public function updateStatus(Request $request, Kegiatan $kegiatan)
    {
        $request->validate([
            'status' => ['required', 'in:draft,aktif,selesai'],
        ]);

        $kegiatan->update(['status' => $request->status]);

        return redirect()->back()
            ->with('success', 'Status kegiatan berhasil diubah.');
    }
}
