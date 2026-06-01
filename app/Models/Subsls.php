<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subsls extends Model
{
    protected $table = 'subsls';

    protected $fillable = [
        'idsubsls', 'kdsubsls',
        'kdprov', 'nmprov', 'kdkab', 'nmkab',
        'kdkec', 'nmkec', 'kddesa', 'nmdesa',
        'kdsls', 'nmsls', 'idsls',
        'geometry', 'centroid_lat', 'centroid_lon', 'luas',
    ];

    protected $casts = [
        'geometry'     => 'array',
        'centroid_lat' => 'double',
        'centroid_lon' => 'double',
        'luas'         => 'double',
    ];

    public function kegiatanWilayah(): HasMany
    {
        return $this->hasMany(KegiatanWilayah::class);
    }

    public function partisiDetail(): HasMany
    {
        return $this->hasMany(PartisiDetail::class);
    }
}
