<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Petugas extends Model
{
    protected $table = 'petugas';

    protected $fillable = [
        'nama', 'jenis', 'nip', 'telepon', 'satker',
    ];

    public function kegiatanPetugas(): HasMany
    {
        return $this->hasMany(KegiatanPetugas::class);
    }
}
