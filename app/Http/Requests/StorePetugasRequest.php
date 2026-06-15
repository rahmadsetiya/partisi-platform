<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePetugasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'jenis' => ['required', 'in:organik,mitra'],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('petugas', 'nip')],
            'telepon' => ['nullable', 'string', 'max:20'],
            'satker' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama' => 'nama petugas',
            'jenis' => 'jenis petugas',
            'nip' => 'NIP',
            'telepon' => 'nomor telepon',
            'satker' => 'satuan kerja',
        ];
    }
}
