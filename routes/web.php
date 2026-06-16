<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeojsonUploadController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\KegiatanPetugasController;
use App\Http\Controllers\KoneksiController;
use App\Http\Controllers\MuatanController;
use App\Http\Controllers\PetugasController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\SesiPartisiController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('kegiatan', KegiatanController::class);
    Route::patch('kegiatan/{kegiatan}/status', [KegiatanController::class, 'updateStatus'])
        ->name('kegiatan.updateStatus');

    // CRUD Petugas (master data)
    Route::post('petugas/import', [PetugasController::class, 'import'])->name('petugas.import');
    Route::resource('petugas', PetugasController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy'])
        ->parameters(['petugas' => 'petugas']);

    // Semua aksi pada satu kegiatan wajib lolos otorisasi 'view' (scoping per satker).
    Route::middleware('can:view,kegiatan')->group(function () {
        Route::get('kegiatan/{kegiatan}/geojson/create', [GeojsonUploadController::class, 'create'])
            ->name('kegiatan.geojson.create');
        Route::post('kegiatan/{kegiatan}/geojson/chunk', [GeojsonUploadController::class, 'storeChunk'])
            ->name('kegiatan.geojson.chunk');
        Route::delete('kegiatan/{kegiatan}/geojson/{upload}', [GeojsonUploadController::class, 'destroy'])
            ->name('kegiatan.geojson.destroy');

        // Kelola Muatan per kegiatan
        Route::get('kegiatan/{kegiatan}/muatan', [MuatanController::class, 'index'])
            ->name('kegiatan.muatan.index');
        Route::post('kegiatan/{kegiatan}/muatan/seragam', [MuatanController::class, 'seragam'])
            ->name('kegiatan.muatan.seragam');
        Route::post('kegiatan/{kegiatan}/muatan/import', [MuatanController::class, 'import'])
            ->name('kegiatan.muatan.import');
        Route::patch('kegiatan/{kegiatan}/muatan/manual', [MuatanController::class, 'manual'])
            ->name('kegiatan.muatan.manual');
        Route::delete('kegiatan/{kegiatan}/muatan/kosongkan', [MuatanController::class, 'kosongkan'])
            ->name('kegiatan.muatan.kosongkan');
        Route::delete('kegiatan/{kegiatan}/muatan/{subsls}', [MuatanController::class, 'hapusSubsls'])
            ->name('kegiatan.muatan.hapusSubsls');

        // Assign petugas ke kegiatan (PPL/PML)
        Route::post('kegiatan/{kegiatan}/petugas', [KegiatanPetugasController::class, 'store'])
            ->name('kegiatan.petugas.store');
        Route::delete('kegiatan/{kegiatan}/petugas/{kegiatanPetugas}', [KegiatanPetugasController::class, 'destroy'])
            ->name('kegiatan.petugas.destroy');

        // Sesi Partisi (pembagian wilayah ke PPL) — nested di kegiatan
        Route::get('kegiatan/{kegiatan}/geojson-data', [SesiPartisiController::class, 'geojson'])
            ->name('kegiatan.partisi.geojson');
        Route::get('kegiatan/{kegiatan}/partisi', [SesiPartisiController::class, 'index'])
            ->name('kegiatan.partisi.index');
        Route::post('kegiatan/{kegiatan}/partisi', [SesiPartisiController::class, 'store'])
            ->name('kegiatan.partisi.store');
        Route::post('kegiatan/{kegiatan}/partisi/auto', [SesiPartisiController::class, 'storeAuto'])
            ->name('kegiatan.partisi.storeAuto');
        Route::get('kegiatan/{kegiatan}/partisi/{sesi}', [SesiPartisiController::class, 'show'])
            ->name('kegiatan.partisi.show');
        Route::get('kegiatan/{kegiatan}/partisi/{sesi}/hasil', [SesiPartisiController::class, 'hasil'])
            ->name('kegiatan.partisi.hasil');
        Route::get('kegiatan/{kegiatan}/partisi/{sesi}/surat-tugas', [SesiPartisiController::class, 'suratTugas'])
            ->name('kegiatan.partisi.suratTugas');
        Route::get('kegiatan/{kegiatan}/partisi/{sesi}/monitoring', [SesiPartisiController::class, 'monitoring'])
            ->name('kegiatan.partisi.monitoring');
        Route::patch('kegiatan/{kegiatan}/partisi/{sesi}/monitoring', [SesiPartisiController::class, 'updateStatusLapangan'])
            ->name('kegiatan.partisi.monitoringUpdate');
        Route::patch('kegiatan/{kegiatan}/partisi/{sesi}/assign', [SesiPartisiController::class, 'saveAssignments'])
            ->name('kegiatan.partisi.assign');
        Route::patch('kegiatan/{kegiatan}/partisi/{sesi}/finalize', [SesiPartisiController::class, 'finalize'])
            ->name('kegiatan.partisi.finalize');
        Route::patch('kegiatan/{kegiatan}/partisi/{sesi}/reopen', [SesiPartisiController::class, 'reopen'])
            ->name('kegiatan.partisi.reopen');
        Route::post('kegiatan/{kegiatan}/partisi/{sesi}/regenerate', [SesiPartisiController::class, 'regenerate'])
            ->name('kegiatan.partisi.regenerate');
        Route::delete('kegiatan/{kegiatan}/partisi/{sesi}', [SesiPartisiController::class, 'destroy'])
            ->name('kegiatan.partisi.destroy');

        // Edit Koneksi (override adjacency antar SubSLS) — per kegiatan
        Route::get('kegiatan/{kegiatan}/koneksi', [KoneksiController::class, 'index'])
            ->name('kegiatan.koneksi.index');
        Route::post('kegiatan/{kegiatan}/koneksi', [KoneksiController::class, 'store'])
            ->name('kegiatan.koneksi.store');
        Route::delete('kegiatan/{kegiatan}/koneksi/{override}', [KoneksiController::class, 'destroy'])
            ->name('kegiatan.koneksi.destroy');
    });
});

// Rute khusus admin
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/auth.php';
