<?php

namespace App\Policies;

use App\Models\Kegiatan;
use App\Models\User;

class KegiatanPolicy
{
    /** Admin akses semua; koordinator hanya satker-nya. */
    private function sesatker(User $user, Kegiatan $kegiatan): bool
    {
        return $user->role === 'admin'
            || ($user->satker !== null && $user->satker === $kegiatan->satker);
    }

    public function viewAny(User $user): bool
    {
        return true; // daftar difilter per satker di controller
    }

    public function view(User $user, Kegiatan $kegiatan): bool
    {
        return $this->sesatker($user, $kegiatan);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Kegiatan $kegiatan): bool
    {
        return $this->sesatker($user, $kegiatan);
    }

    public function delete(User $user, Kegiatan $kegiatan): bool
    {
        return $this->sesatker($user, $kegiatan);
    }
}
