<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kegiatan extends Model
{
    protected $fillable = [
        'nama', 'jenis', 'tahun', 'gelombang',
        'tanggal_mulai', 'tanggal_selesai', 'deskripsi', 'status', 'created_by',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function geojsonUploads(): HasMany
    {
        return $this->hasMany(GeojsonUpload::class);
    }

    public function wilayah(): HasMany
    {
        return $this->hasMany(KegiatanWilayah::class);
    }

    public function petugas(): HasMany
    {
        return $this->hasMany(KegiatanPetugas::class);
    }

    public function sesiPartisi(): HasMany
    {
        return $this->hasMany(SesiPartisi::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(KegiatanOverride::class);
    }

    public function sesiAktif(): ?SesiPartisi
    {
        return $this->sesiPartisi()->where('status', 'final')->latest()->first();
    }
}
