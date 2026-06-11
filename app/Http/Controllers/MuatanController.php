<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MuatanController extends Controller
{
    public function index(Request $request, Kegiatan $kegiatan)
    {
        $q = trim((string) $request->query('q', ''));

        $rows = DB::table('kegiatan_wilayah as kw')
            ->join('subsls as s', 's.id', '=', 'kw.subsls_id')
            ->where('kw.kegiatan_id', $kegiatan->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('s.nmkec', 'like', "%{$q}%")
                        ->orWhere('s.nmdesa', 'like', "%{$q}%")
                        ->orWhere('s.nmsls', 'like', "%{$q}%")
                        ->orWhere('s.idsubsls', 'like', "%{$q}%");
                });
            })
            ->orderBy('s.nmkec')
            ->orderBy('s.nmdesa')
            ->orderBy('s.idsubsls')
            ->select('kw.subsls_id', 's.idsubsls', 's.nmkec', 's.nmdesa', 's.nmsls', 'kw.muatan')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Kegiatan/KelolaMuatan', [
            'kegiatan' => $kegiatan->only('id', 'nama'),
            'rows' => $rows,
            'filters' => ['q' => $q],
            'summary' => $this->summary($kegiatan),
        ]);
    }

    public function seragam(Request $request, Kegiatan $kegiatan)
    {
        $data = $request->validate([
            'nilai' => ['required', 'integer', 'min:0'],
        ]);

        DB::table('kegiatan_wilayah')
            ->where('kegiatan_id', $kegiatan->id)
            ->update(['muatan' => $data['nilai'], 'muatan_col' => '(seragam)']);

        return back()->with('success', "Muatan seragam {$data['nilai']} diterapkan ke semua SubSLS.");
    }

    public function import(Request $request, Kegiatan $kegiatan)
    {
        $data = $request->validate([
            'nama_file' => ['nullable', 'string', 'max:255'],
            'map' => ['required', 'array', 'min:1'],
            'map.*' => ['nullable', 'numeric'],
        ]);

        // idsubsls → subsls_id untuk SubSLS yang ada di kegiatan ini
        $idToSubslsId = DB::table('kegiatan_wilayah as kw')
            ->join('subsls as s', 's.id', '=', 'kw.subsls_id')
            ->where('kw.kegiatan_id', $kegiatan->id)
            ->pluck('kw.subsls_id', 's.idsubsls');

        $label = '(import: '.($data['nama_file'] ?? 'file').')';
        $cocok = 0;
        $diabaikan = 0;

        DB::transaction(function () use ($data, $idToSubslsId, $kegiatan, $label, &$cocok, &$diabaikan) {
            foreach ($data['map'] as $idsubsls => $muatan) {
                $idsubsls = (string) $idsubsls;
                if (! isset($idToSubslsId[$idsubsls])) {
                    $diabaikan++;

                    continue;
                }

                DB::table('kegiatan_wilayah')
                    ->where('kegiatan_id', $kegiatan->id)
                    ->where('subsls_id', $idToSubslsId[$idsubsls])
                    ->update(['muatan' => (int) round((float) $muatan), 'muatan_col' => $label]);
                $cocok++;
            }
        });

        $pesan = "Import muatan selesai. {$cocok} SubSLS diperbarui";
        $pesan .= $diabaikan > 0 ? ", {$diabaikan} idsubsls tidak ditemukan di kegiatan ini." : '.';

        return back()->with('success', $pesan);
    }

    public function manual(Request $request, Kegiatan $kegiatan)
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.subsls_id' => ['required', 'integer'],
            'rows.*.muatan' => ['nullable', 'integer', 'min:0'],
        ]);

        // Batasi hanya subsls_id yang memang milik kegiatan ini
        $valid = DB::table('kegiatan_wilayah')
            ->where('kegiatan_id', $kegiatan->id)
            ->pluck('subsls_id')
            ->flip();

        $jumlah = 0;
        DB::transaction(function () use ($data, $kegiatan, $valid, &$jumlah) {
            foreach ($data['rows'] as $row) {
                if (! $valid->has($row['subsls_id'])) {
                    continue;
                }

                DB::table('kegiatan_wilayah')
                    ->where('kegiatan_id', $kegiatan->id)
                    ->where('subsls_id', $row['subsls_id'])
                    ->update(['muatan' => $row['muatan'], 'muatan_col' => '(manual)']);
                $jumlah++;
            }
        });

        return back()->with('success', "{$jumlah} muatan SubSLS berhasil disimpan.");
    }

    private function summary(Kegiatan $kegiatan): array
    {
        $agg = DB::table('kegiatan_wilayah')
            ->where('kegiatan_id', $kegiatan->id)
            ->selectRaw('COUNT(*) as total, COUNT(muatan) as terisi, COALESCE(SUM(muatan),0) as total_muatan')
            ->first();

        return [
            'total' => (int) $agg->total,
            'terisi' => (int) $agg->terisi,
            'total_muatan' => (int) $agg->total_muatan,
        ];
    }
}
