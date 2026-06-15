<?php

namespace App\Policies;

use App\Models\Petugas;
use App\Models\User;

class PetugasPolicy
{
    private function sesatker(User $user, Petugas $petugas): bool
    {
        return $user->role === 'admin'
            || ($user->satker !== null && $user->satker === $petugas->satker);
    }

    public function view(User $user, Petugas $petugas): bool
    {
        return $this->sesatker($user, $petugas);
    }

    public function update(User $user, Petugas $petugas): bool
    {
        return $this->sesatker($user, $petugas);
    }

    public function delete(User $user, Petugas $petugas): bool
    {
        return $this->sesatker($user, $petugas);
    }
}
