<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KegiatanOverride extends Model
{
    protected $table = 'kegiatan_override';

    public $timestamps = false;

    protected $fillable = [
        'kegiatan_id', 'idsubsls_a', 'idsubsls_b', 'tipe', 'catatan', 'created_by',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
