<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KegiatanPetugas extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'kegiatan_id', 'petugas_id', 'peran', 'label', 'group_id',
    ];

    protected $casts = [
        'group_id' => 'integer',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Petugas::class);
    }

    public function partisiSebagaiPpl(): HasMany
    {
        return $this->hasMany(PartisiDetail::class, 'ppl_id');
    }

    public function partisiSebagaiPml(): HasMany
    {
        return $this->hasMany(PartisiDetail::class, 'pml_id');
    }
}
