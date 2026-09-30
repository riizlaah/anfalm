<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GeneratePaketController;
use App\Http\Controllers\Admin\KompetensiDasarController;
use App\Http\Controllers\Admin\MapelController;
use App\Http\Controllers\Admin\PaketSoalController;
use App\Http\Controllers\Admin\PaketTryoutController;
use App\Http\Controllers\Admin\SoalController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TryoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware(['auth', 'single.session'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('tryout', [TryoutController::class, 'index'])->name('tryout.index');
    Route::post('tryout/{paketTryout}/mulai', [TryoutController::class, 'mulai'])->name('tryout.mulai');
    Route::get('tryout/{paketTryout}/kerja', [TryoutController::class, 'kerja'])->name('tryout.kerja');
    Route::post('tryout/{paketTryout}/jawab', [TryoutController::class, 'jawab'])->name('tryout.jawab');
    Route::get('tryout/{paketTryout}/hasil', [TryoutController::class, 'hasil'])->name('tryout.hasil');
});

Route::middleware(['auth', 'single.session', 'role:admin'])->group(function () {
    Route::get('admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('admin/mapel/bulk-delete', [MapelController::class, 'bulkDestroy'])->name('admin.mapel.bulk-delete');
    Route::resource('admin/mapel', MapelController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('admin.mapel');
    Route::post('admin/mapel/{mapel}/kompetensi-dasar/bulk-delete', [KompetensiDasarController::class, 'bulkDestroy'])
        ->name('admin.mapel.kompetensi-dasar.bulk-delete');
    Route::resource('admin/mapel.kompetensi-dasar', KompetensiDasarController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->scoped()
        ->names('admin.mapel.kompetensi-dasar');
    Route::post('admin/mapel/{mapel}/soal/bulk-delete', [SoalController::class, 'bulkDestroy'])
        ->name('admin.mapel.soal.bulk-delete');
    Route::resource('admin/mapel.soal', SoalController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->scoped()
        ->names('admin.mapel.soal');
    Route::get('admin/paket-soal/generate', [GeneratePaketController::class, 'create'])->name('admin.paket-soal.generate');
    Route::post('admin/paket-soal/generate', [GeneratePaketController::class, 'storePart'])->name('admin.paket-soal.generate.store');
    Route::get('admin/paket-soal/kurasi', [GeneratePaketController::class, 'kurasi'])->name('admin.paket-soal.kurasi');
    Route::post('admin/paket-soal/simpan', [GeneratePaketController::class, 'simpan'])->name('admin.paket-soal.simpan');
    Route::resource('admin/paket-soal', PaketSoalController::class)
        ->except(['show'])
        ->names('admin.paket-soal');
    Route::resource('admin/paket-tryout', PaketTryoutController::class)
        ->except(['show'])
        ->names('admin.paket-tryout');
});
