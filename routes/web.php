<?php

use App\Http\Controllers\GeojsonUploadController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('kegiatan', KegiatanController::class);
    Route::patch('kegiatan/{kegiatan}/status', [KegiatanController::class, 'updateStatus'])
        ->name('kegiatan.updateStatus');

    Route::get('kegiatan/{kegiatan}/geojson/create', [GeojsonUploadController::class, 'create'])
        ->name('kegiatan.geojson.create');
    Route::post('kegiatan/{kegiatan}/geojson', [GeojsonUploadController::class, 'store'])
        ->name('kegiatan.geojson.store');
    Route::delete('kegiatan/{kegiatan}/geojson/{upload}', [GeojsonUploadController::class, 'destroy'])
        ->name('kegiatan.geojson.destroy');
});

// Rute khusus admin — tambahkan di sini
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Route::get('/users', ...) — contoh
});

require __DIR__.'/auth.php';
