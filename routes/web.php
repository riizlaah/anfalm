<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\KompetensiDasarController;
use App\Http\Controllers\Admin\MapelController;
use App\Http\Controllers\Admin\SoalController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
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
});

Route::middleware(['auth', 'single.session', 'role:admin'])->group(function () {
    Route::get('admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::resource('admin/mapel', MapelController::class)
        ->except(['show'])
        ->names('admin.mapel');
    Route::resource('admin/kompetensi-dasar', KompetensiDasarController::class)
        ->except(['show'])
        ->names('admin.kompetensi-dasar');
    Route::resource('admin/soal', SoalController::class)
        ->except(['show'])
        ->names('admin.soal');
});
