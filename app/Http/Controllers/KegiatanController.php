<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKegiatanRequest;
use App\Http\Requests\UpdateKegiatanRequest;
use App\Models\Kegiatan;
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

        return Inertia::render('Kegiatan/Show', [
            'kegiatan' => $kegiatan,
            'jumlahWilayah' => (int) $muatan->total,
            'muatanTerisi' => (int) $muatan->terisi,
            'totalMuatan' => (int) $muatan->total_muatan,
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
