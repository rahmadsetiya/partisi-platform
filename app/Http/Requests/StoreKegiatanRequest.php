<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKegiatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'jenis' => ['required', 'in:berkala,insidentil'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'gelombang' => ['nullable', 'string', 'max:50'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama' => 'nama kegiatan',
            'jenis' => 'jenis kegiatan',
            'tahun' => 'tahun',
            'gelombang' => 'gelombang',
            'tanggal_mulai' => 'tanggal mulai',
            'tanggal_selesai' => 'tanggal selesai',
            'deskripsi' => 'deskripsi',
        ];
    }
}
