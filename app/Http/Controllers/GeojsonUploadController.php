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

    public function store(Request $request, Kegiatan $kegiatan)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:30720'],
            'level' => ['required', 'in:desa,subsls'],
            'sumber_muatan' => ['required', 'in:kolom,seragam,kosong'],
            // muatan_col hanya wajib kalau sumber = kolom
            'muatan_col' => ['nullable', 'required_if:sumber_muatan,kolom', 'string', 'max:100'],
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $geoJson = json_decode($content, true);

        if (! $geoJson || ($geoJson['type'] ?? null) !== 'FeatureCollection' || empty($geoJson['features'])) {
            return back()->withErrors(['file' => 'File bukan FeatureCollection GeoJSON yang valid.']);
        }

        $features = $geoJson['features'];
        $sumber = $request->sumber_muatan;
        $jumlah = count($features);
        $now = now()->toDateTimeString();

        // Label sumber muatan disimpan di kolom muatan_col (audit trail)
        $muatanCol = match ($sumber) {
            'kolom' => $request->muatan_col,
            'seragam' => '(seragam)',
            'kosong' => null,
        };

        $path = $file->store('geojson', 'local');

        DB::beginTransaction();
        try {
            KegiatanWilayah::where('kegiatan_id', $kegiatan->id)->delete();

            foreach (array_chunk($features, 500) as $chunk) {
                $subslsRows = [];

                foreach ($chunk as $feature) {
                    $props = $feature['properties'] ?? [];
                    $idsubsls = isset($props['idsubsls']) ? (string) $props['idsubsls'] : null;
                    if (! $idsubsls) {
                        continue;
                    }

                    [$lat, $lon] = self::centroid($feature['geometry']);

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
                        'geometry' => json_encode($feature['geometry']),
                        'centroid_lat' => $lat,
                        'centroid_lon' => $lon,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (empty($subslsRows)) {
                    continue;
                }

                DB::table('subsls')->upsert(
                    $subslsRows,
                    ['idsubsls'],
                    ['kdsubsls', 'kdprov', 'nmprov', 'kdkab', 'nmkab',
                        'kdkec', 'nmkec', 'kddesa', 'nmdesa', 'kdsls', 'nmsls',
                        'idsls', 'geometry', 'centroid_lat', 'centroid_lon', 'updated_at'],
                );

                $idsubslsList = array_column($subslsRows, 'idsubsls');
                $subslsMap = DB::table('subsls')
                    ->whereIn('idsubsls', $idsubslsList)
                    ->pluck('id', 'idsubsls');

                $wilayahRows = [];
                foreach ($chunk as $feature) {
                    $props = $feature['properties'] ?? [];
                    $idsubsls = isset($props['idsubsls']) ? (string) $props['idsubsls'] : null;
                    if (! $idsubsls || ! isset($subslsMap[$idsubsls])) {
                        continue;
                    }

                    $muatan = match ($sumber) {
                        'kolom' => (int) ($props[$muatanCol] ?? 0),
                        'seragam' => 1,
                        'kosong' => null,
                    };

                    $wilayahRows[] = [
                        'kegiatan_id' => $kegiatan->id,
                        'subsls_id' => $subslsMap[$idsubsls],
                        'muatan' => $muatan,
                        'muatan_col' => $muatanCol,
                        'created_at' => $now,
                    ];
                }

                if (! empty($wilayahRows)) {
                    DB::table('kegiatan_wilayah')->insert($wilayahRows);
                }
            }

            GeojsonUpload::create([
                'kegiatan_id' => $kegiatan->id,
                'level' => $request->level,
                'nama_file' => $file->getClientOriginalName(),
                'path' => $path,
                'muatan_col' => $muatanCol,
                'jumlah_fitur' => $jumlah,
                'uploaded_by' => $request->user()->id,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'Gagal memproses GeoJSON: '.$e->getMessage()]);
        }

        $pesan = "GeoJSON berhasil diupload. {$jumlah} SubSLS berhasil dimuat.";
        if ($sumber === 'kosong') {
            $pesan .= ' Muatan belum diisi — lengkapi lewat menu Kelola Muatan.';
        }

        return redirect()->route('kegiatan.show', $kegiatan)->with('success', $pesan);
    }

    public function destroy(Kegiatan $kegiatan, GeojsonUpload $upload)
    {
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
