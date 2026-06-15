<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\KegiatanPetugas;
use App\Models\Petugas;
use Illuminate\Http\Request;

class KegiatanPetugasController extends Controller
{
    public function store(Request $request, Kegiatan $kegiatan)
    {
        if ($kegiatan->adaPartisiFinal()) {
            return back()->with('error', 'Kegiatan terkunci karena ada sesi partisi final. Kembalikan sesi ke draft dulu untuk mengubah petugas.');
        }

        $data = $request->validate([
            'petugas_id' => ['required', 'integer', 'exists:petugas,id'],
            'peran' => ['required', 'in:ppl,pml'],
        ]);

        // Organik BPS tidak boleh jadi PPL (hanya mitra).
        if ($data['peran'] === 'ppl') {
            $jenis = Petugas::whereKey($data['petugas_id'])->value('jenis');
            if ($jenis !== 'mitra') {
                return back()->with('error', 'Organik BPS tidak bisa ditugaskan sebagai PPL. Hanya mitra yang boleh jadi PPL.');
            }
        }

        // Satu petugas hanya boleh punya satu peran dalam satu kegiatan
        $sudahAda = KegiatanPetugas::where('kegiatan_id', $kegiatan->id)
            ->where('petugas_id', $data['petugas_id'])
            ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Petugas tersebut sudah ditugaskan di kegiatan ini.');
        }

        $groupId = KegiatanPetugas::where('kegiatan_id', $kegiatan->id)
            ->where('peran', $data['peran'])
            ->count();

        KegiatanPetugas::create([
            'kegiatan_id' => $kegiatan->id,
            'petugas_id' => $data['petugas_id'],
            'peran' => $data['peran'],
            'group_id' => $groupId,
            'label' => strtoupper($data['peran']).' '.($groupId + 1),
        ]);

        return back()->with('success', 'Petugas berhasil ditugaskan.');
    }

    public function destroy(Kegiatan $kegiatan, KegiatanPetugas $kegiatanPetugas)
    {
        abort_unless($kegiatanPetugas->kegiatan_id === $kegiatan->id, 404);

        if ($kegiatan->adaPartisiFinal()) {
            return back()->with('error', 'Kegiatan terkunci karena ada sesi partisi final. Kembalikan sesi ke draft dulu.');
        }

        $peran = $kegiatanPetugas->peran;
        $kegiatanPetugas->delete();

        $this->resequence($kegiatan, $peran);

        return back()->with('success', 'Petugas berhasil dilepas dari kegiatan.');
    }

    /**
     * Rapikan group_id (0-based) dan label agar tetap berurutan setelah penghapusan.
     */
    private function resequence(Kegiatan $kegiatan, string $peran): void
    {
        $rows = KegiatanPetugas::where('kegiatan_id', $kegiatan->id)
            ->where('peran', $peran)
            ->orderBy('group_id')
            ->orderBy('id')
            ->get();

        foreach ($rows as $i => $row) {
            $baruLabel = strtoupper($peran).' '.($i + 1);
            if ($row->group_id !== $i || $row->label !== $baruLabel) {
                $row->update(['group_id' => $i, 'label' => $baruLabel]);
            }
        }
    }
}
