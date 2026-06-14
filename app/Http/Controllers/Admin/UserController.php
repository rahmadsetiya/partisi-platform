<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email', 'role', 'satker', 'created_at']);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return back()->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        // Cegah admin terakhir di-demote (lockout).
        if ($user->role === 'admin' && $data['role'] !== 'admin' && $this->adminCount() <= 1) {
            return back()->with('error', 'Tidak bisa mengubah peran admin terakhir.');
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        if ($user->role === 'admin' && $this->adminCount() <= 1) {
            return back()->with('error', 'Tidak bisa menghapus admin terakhir.');
        }

        $user->delete();

        return back()->with('success', 'Pengguna berhasil dihapus.');
    }

    private function adminCount(): int
    {
        return (int) User::where('role', 'admin')->count();
    }
}
