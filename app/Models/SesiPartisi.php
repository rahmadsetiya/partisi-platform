<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesiPartisi extends Model
{
    protected $table = 'sesi_partisi';

    protected $fillable = [
        'kegiatan_id', 'nama', 'tipe', 'n_ppl', 'n_pml',
        'cv', 'epsg', 'config', 'status', 'created_by', 'finalized_at',
    ];

    protected $casts = [
        'config' => 'array',
        'cv' => 'double',
        'finalized_at' => 'datetime',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(PartisiDetail::class);
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }
}
