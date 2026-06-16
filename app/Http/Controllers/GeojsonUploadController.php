<?php

namespace App\Http\Controllers;

use App\Models\GeojsonUpload;
use App\Models\Kegiatan;
use App\Models\KegiatanWilayah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GeojsonUploadController extends Controller
{
    public function create(Kegiatan $kegiatan)
    {
        $uploads = $kegiatan->geojsonUploads()->with('uploader')->latest('uploaded_at')->get();

        return Inertia::render('Kegiatan/UploadGeojson', [
            'kegiatan' => $kegiatan,
            'uploads' => $uploads,
        ]);
    }

    /**
     * Terima GeoJSON yang diparse di klien secara bertahap (chunk) — menghindari
     * limit upload PHP (post/upload_max_filesize) & hemat memori untuk file besar.
     */
    public function storeChunk(Request $request, Kegiatan $kegiatan)
    {
        $data = $request->validate([
            'chunk_index' => ['required', 'integer', 'min:0'],
            'is_first' => ['required', 'boolean'],
            'is_last' => ['required', 'boolean'],
            'nama_file' => ['required', 'string', 'max:255'],
            'level' => ['required', 'in:desa,subsls'],
            'muatan_col' => ['nullable', 'string', 'max:100'],
            'total_fitur' => ['nullable', 'integer', 'min:0'],
            'features' => ['required', 'array', 'min:1'],
            'features.*.properties' => ['required', 'array'],
            'features.*.geometry' => ['required', 'array'],
            'features.*.muatan' => ['nullable', 'numeric'],
        ]);

        if ($data['is_first'] && $kegiatan->adaPartisiFinal()) {
            return response()->json(['message' => 'Kegiatan terkunci karena ada sesi partisi final.'], 422);
        }

        $now = now()->toDateTimeString();
        $muatanCol = $data['muatan_col'] ?: null;

        DB::transaction(function () use ($data, $kegiatan, $now, $muatanCol) {
            if ($data['is_first']) {
                KegiatanWilayah::where('kegiatan_id', $kegiatan->id)->delete();
            }

            $subslsRows = [];
            foreach ($data['features'] as $f) {
                $props = $f['properties'];
                $idsubsls = isset($props['idsubsls']) ? (string) $props['idsubsls'] : null;
                if (! $idsubsls) {
                    continue;
                }
                [$lat, $lon] = self::centroid($f['geometry']);

                $subslsRows[] = [
                    'idsubsls' => $idsubsls,
                    'kdsubsls' => (string) ($props['kdsubsls'] ?? ''),
                    'kdprov' => (string) ($props['kdprov'] ?? ''),
                    'nmprov' => (string) ($props['nmprov'] ?? ''),
                    'kdkab' => (string) ($props['kdkab'] ?? ''),
                    'nmkab' => (string) ($props['nmkab'] ?? ''),
                    'kdkec' => (string) ($props['kdkec'] ?? ''),
                    'nmkec' => (string) ($props['nmkec'] ?? ''),
                    'kddesa' => (string) ($props['kddesa'] ?? ''),
                    'nmdesa' => (string) ($props['nmdesa'] ?? ''),
                    'kdsls' => (string) ($props['kdsls'] ?? ''),
                    'nmsls' => (string) ($props['nmsls'] ?? ''),
                    'idsls' => isset($props['idsls']) ? (string) $props['idsls'] : null,
                    'geometry' => json_encode($f['geometry']),
                    'centroid_lat' => $lat,
                    'centroid_lon' => $lon,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (empty($subslsRows)) {
                return;
            }

            DB::table('subsls')->upsert(
                $subslsRows,
                ['idsubsls'],
                ['kdsubsls', 'kdprov', 'nmprov', 'kdkab', 'nmkab',
                    'kdkec', 'nmkec', 'kddesa', 'nmdesa', 'kdsls', 'nmsls',
                    'idsls', 'geometry', 'centroid_lat', 'centroid_lon', 'updated_at'],
            );

            $subslsMap = DB::table('subsls')
                ->whereIn('idsubsls', array_column($subslsRows, 'idsubsls'))
                ->pluck('id', 'idsubsls');

            $wilayahRows = [];
            foreach ($data['features'] as $f) {
                $idsubsls = isset($f['properties']['idsubsls']) ? (string) $f['properties']['idsubsls'] : null;
                if (! $idsubsls || ! isset($subslsMap[$idsubsls])) {
                    continue;
                }
                $wilayahRows[] = [
                    'kegiatan_id' => $kegiatan->id,
                    'subsls_id' => $subslsMap[$idsubsls],
                    'muatan' => isset($f['muatan']) && $f['muatan'] !== null ? (int) round((float) $f['muatan']) : null,
                    'muatan_col' => $muatanCol,
                    'created_at' => $now,
                ];
            }
            if (! empty($wilayahRows)) {
                DB::table('kegiatan_wilayah')->insert($wilayahRows);
            }
        });

        if ($data['is_last']) {
            GeojsonUpload::create([
                'kegiatan_id' => $kegiatan->id,
                'level' => $data['level'],
                'nama_file' => $data['nama_file'],
                'path' => '(chunked)',
                'muatan_col' => $muatanCol,
                'jumlah_fitur' => $data['total_fitur'] ?? 0,
                'uploaded_by' => $request->user()->id,
            ]);

            $request->session()->flash('success', "GeoJSON '{$data['nama_file']}' berhasil diproses (".($data['total_fitur'] ?? 0).' fitur).');

            return response()->json(['done' => true]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Kegiatan $kegiatan, GeojsonUpload $upload)
    {
        if ($kegiatan->adaPartisiFinal()) {
            return back()->with('error', 'Kegiatan terkunci karena ada sesi partisi final. Kembalikan sesi ke draft dulu.');
        }

        $remaining = GeojsonUpload::where('kegiatan_id', $kegiatan->id)->count();

        if ($remaining <= 1) {
            KegiatanWilayah::where('kegiatan_id', $kegiatan->id)->delete();
        }

        Storage::disk('local')->delete($upload->path);
        $upload->delete();

        return redirect()->back()->with('success', 'Upload GeoJSON berhasil dihapus.');
    }

    private static function centroid(array $geometry): array
    {
        try {
            $coords = self::flattenCoords($geometry['coordinates'], $geometry['type']);
            if (empty($coords)) {
                return [0.0, 0.0];
            }

            $count = count($coords);

            return [
                array_sum(array_column($coords, 1)) / $count,
                array_sum(array_column($coords, 0)) / $count,
            ];
        } catch (\Throwable) {
            return [0.0, 0.0];
        }
    }

    private static function flattenCoords(mixed $coords, string $type): array
    {
        return match ($type) {
            'Point' => [$coords],
            'MultiPoint', 'LineString' => $coords,
            'MultiLineString', 'Polygon' => array_merge(...$coords),
            'MultiPolygon' => array_merge(...array_map(
                fn ($poly) => array_merge(...$poly),
                $coords
            )),
            default => [],
        };
    }
}
