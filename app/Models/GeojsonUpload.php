<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeojsonUpload extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'kegiatan_id', 'level', 'nama_file', 'path',
        'muatan_col', 'epsg', 'jumlah_fitur', 'uploaded_by', 'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'epsg' => 'integer',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
