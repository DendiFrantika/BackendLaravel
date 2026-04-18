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

// Public Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/dokter/spesialisasi', [DokterController::class, 'getSpesialisasi']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Auth Routes
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // ADMIN ROUTES
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin']);
        Route::get('/dashboard/statistik-pasien', [DashboardController::class, 'statistikPasien']);
        Route::get('/dashboard/statistik-dokter', [DashboardController::class, 'statistikDokter']);
        Route::get('/dashboard/statistik-pendaftaran', [DashboardController::class, 'statistikPendaftaran']);
        Route::get('/dashboard/pendaftaran-terbaru', [DashboardController::class, 'pendaftaranTerbaru']);
        Route::get('/dashboard/aktivitas-hari-ini', [DashboardController::class, 'aktivitasHariIni']);

        Route::apiResource('/dokter', DokterController::class);
        Route::get('/dokter/spesialisasi/{spesialisasi}', [DokterController::class, 'getBySpesialisasi']);

        Route::apiResource('/jadwal', JadwalController::class);
        Route::get('/jadwal/dokter/{dokter_id}', [JadwalController::class, 'getByDokter']);

        Route::apiResource('/pasien', PasienController::class);

        Route::apiResource('/pendaftaran', PendaftaranController::class);
        Route::post('/pendaftaran/{id}/verifikasi', [PendaftaranController::class, 'verifikasi']);
        Route::get('/pendaftaran/pasien/{pasien_id}', [PendaftaranController::class, 'getByPasien']);
        Route::get('/pendaftaran/dokter/{dokter_id}', [PendaftaranController::class, 'getByDokter']);

        Route::get('/laporan', [LaporanController::class, 'laporanPasien']);
        Route::get('/laporan/pasien', [LaporanController::class, 'laporanPasien']);
        Route::get('/laporan/rekam-medis', [LaporanController::class, 'laporanRekamMedis']);
        Route::get('/laporan/dokter', [LaporanController::class, 'laporanDokter']);
        Route::get('/laporan/pendaftaran', [LaporanController::class, 'laporanPendaftaran']);
        Route::post('/laporan/export-pdf', [LaporanController::class, 'exportPDF']);
        Route::post('/laporan/export-excel', [LaporanController::class, 'exportExcel']);
    });

    // PASIEN ROUTES
    Route::middleware('role:pasien')->group(function () {
        Route::get('/dashboard-pasien', [DashboardController::class, 'pasien']);
        Route::post('/daftar-berobat', [PendaftaranController::class, 'store']);
        Route::get('/riwayat', [PendaftaranController::class, 'riwayat']);
        Route::get('/antrian', [PendaftaranController::class, 'antrian']);
    });

    // ADMIN / DOKTER REKAM MEDIS ROUTES
    Route::middleware('role:admin,dokter')->group(function () {
        Route::apiResource('/rekam-medis', RekamMedisController::class);
        Route::get('/rekam-medis/pasien/{pasien_id}', [RekamMedisController::class, 'getByPasien']);
        Route::get('/rekam-medis/dokter/{dokter_id}', [RekamMedisController::class, 'getByDokter']);
    });

    // DOKTER ROUTES
    Route::middleware('role:dokter')->group(function () {
        Route::get('/dashboard-dokter', [DashboardController::class, 'overview']);
        Route::get('/pasien-saya', [PendaftaranController::class, 'getByDokter']);
    });
});



