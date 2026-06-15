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

        return Inertia::render('Petugas/Index', [
            'petugas' => $petugas,
            'filters' => ['q' => $q],
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
