<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartisiDetail extends Model
{
    protected $table = 'partisi_detail';

    public $timestamps = false;

    protected $fillable = [
        'sesi_partisi_id', 'subsls_id', 'ppl_id', 'pml_id', 'status_lapangan',
    ];

    public function sesiPartisi(): BelongsTo
    {
        return $this->belongsTo(SesiPartisi::class);
    }

    public function subsls(): BelongsTo
    {
        return $this->belongsTo(Subsls::class);
    }

    public function ppl(): BelongsTo
    {
        return $this->belongsTo(KegiatanPetugas::class, 'ppl_id');
    }

    public function pml(): BelongsTo
    {
        return $this->belongsTo(KegiatanPetugas::class, 'pml_id');
    }
}
