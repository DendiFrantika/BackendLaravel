<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\PasienController;
use App\Http\Controllers\API\DokterController;
use App\Http\Controllers\API\JadwalController;
use App\Http\Controllers\API\PendaftaranController;
use App\Http\Controllers\API\RekamMedisController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\LaporanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | AUTH
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {

        Route::prefix('admin')->group(function () {

            // Dashboard
            Route::get('/dashboard', [DashboardController::class, 'admin']);

            // Dokter
            Route::apiResource('/dokter', DokterController::class);

        Route::post('/pendaftaran/{id}/verifikasi', [PendaftaranController::class, 'verifikasi']);
        Route::get('/pendaftaran/pasien/{pasien_id}', [PendaftaranController::class, 'getByPasien']);
        Route::get('/pendaftaran/dokter/{dokter_id}', [PendaftaranController::class, 'getByDokter']);

            // Jadwal
            Route::apiResource('/jadwal', JadwalController::class);

            // Pasien
            Route::apiResource('/pasien', PasienController::class);

            // Pendaftaran
            Route::apiResource('/pendaftaran', PendaftaranController::class);

            // Laporan
            Route::get('/laporan/pasien', [LaporanController::class, 'laporanPasien']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | PASIEN (INI YANG KAMU BUTUH)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:pasien')->prefix('pasien')->group(function () {

        // 🔥 PROFIL (INI FIX UTAMA KAMU)
        Route::get('/profile', [PasienController::class, 'profile']);
        Route::put('/profile', [PasienController::class, 'update']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'pasien']);

        // Pendaftaran
        Route::post('/daftar', [PendaftaranController::class, 'store']);

        // Riwayat (rename biar konsisten)
        Route::get('/appointments', [PendaftaranController::class, 'riwayat']);

        // Antrian
        Route::get('/antrian', [PendaftaranController::class, 'antrian']);
    });

    /*
    |--------------------------------------------------------------------------
    | DOKTER
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:dokter')->prefix('dokter')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'overview']);

        Route::get('/pasien', [PendaftaranController::class, 'getByDokter']);
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN & DOKTER
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin,dokter')->group(function () {
        Route::apiResource('/rekam-medis', RekamMedisController::class);
    });

});
