<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KegiatanWilayah extends Model
{
    protected $table = 'kegiatan_wilayah';

    public $timestamps = false;

    protected $fillable = [
        'kegiatan_id', 'subsls_id', 'muatan', 'muatan_col',
    ];

    protected $casts = [
        'muatan' => 'integer',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function subsls(): BelongsTo
    {
        return $this->belongsTo(Subsls::class);
    }
}
