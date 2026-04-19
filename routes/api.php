<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\PasienController;
use App\Http\Controllers\API\DokterController;
use App\Http\Controllers\API\JadwalController;
use App\Http\Controllers\API\PendaftaranController;
use App\Http\Controllers\API\RekamMedisController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\LaporanController;

use App\Http\Controllers\API\Kasir\ObatController as KasirObatController;
use App\Http\Controllers\API\Kasir\PendaftaranFlowController as KasirPendaftaranFlowController;
use App\Http\Controllers\API\Kasir\TarifTindakanController as KasirTarifTindakanController;
use App\Http\Controllers\API\Kasir\TransaksiController as KasirTransaksiController;
use App\Http\Controllers\API\Kasir\LaporanKasirController;

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/profile/photo', [AuthController::class, 'updatePhoto']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'admin']);

        Route::apiResource('/dokter', DokterController::class);
        Route::apiResource('/jadwal', JadwalController::class);
        Route::apiResource('/pasien', PasienController::class);
        Route::apiResource('/pendaftaran', PendaftaranController::class);

        Route::post('/pendaftaran/{id}/verifikasi', [PendaftaranController::class, 'verifikasi']);
        Route::get('/pendaftaran/pasien/{pasien_id}', [PendaftaranController::class, 'getByPasien']);
        Route::get('/pendaftaran/dokter/{dokter_id}', [PendaftaranController::class, 'getByDokter']);

        Route::apiResource('/rekam-medis', RekamMedisController::class);

        Route::get('/laporan/pasien', [LaporanController::class, 'laporanPasien']);

        Route::apiResource('/obat', KasirObatController::class);
        Route::apiResource('/tarif-tindakan', KasirTarifTindakanController::class);

        Route::get('/pendaftaran/antrian-hari-ini', [KasirPendaftaranFlowController::class, 'antrianHariIni']);
        Route::post('/pendaftaran/{id}/check-in', [KasirPendaftaranFlowController::class, 'checkIn']);
        Route::post('/kunjungan-langsung', [KasirPendaftaranFlowController::class, 'kunjunganLangsung']);

        Route::get('/transaksi', [KasirTransaksiController::class, 'index']);
        Route::post('/transaksi', [KasirTransaksiController::class, 'store']);
        Route::get('/transaksi/{transaksi}', [KasirTransaksiController::class, 'show']);
        Route::get('/transaksi/{transaksi}/invoice', [KasirTransaksiController::class, 'invoice']);
        Route::post('/transaksi/{transaksi}/bayar', [KasirTransaksiController::class, 'bayar']);
        Route::post('/transaksi/{transaksi}/batal', [KasirTransaksiController::class, 'batal']);

        Route::get('/laporan/keuangan', [LaporanKasirController::class, 'keuangan']);
        Route::get('/laporan/operasional', [LaporanKasirController::class, 'operasional']);
    });

    Route::middleware('role:pasien')->prefix('pasien')->group(function () {

        Route::get('/profile', [PasienController::class, 'profile']);
        Route::put('/profile', [PasienController::class, 'updateProfile']);
        Route::post('/profile/photo', [PasienController::class, 'updatePhoto']);

        Route::get('/dashboard', [DashboardController::class, 'pasien']);

        Route::get('/dokters', [DokterController::class, 'indexForPasien']);
        Route::get('/dokter/{dokter_id}/jadwal', [JadwalController::class, 'getByDokter']);

        Route::post('/daftar', [PendaftaranController::class, 'store']);
        Route::get('/appointments', [PendaftaranController::class, 'riwayat']);
        Route::get('/antrian', [PendaftaranController::class, 'antrian']);
    });

    Route::middleware('role:dokter')->prefix('dokter')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'overview']);
        Route::get('/pasien', [PendaftaranController::class, 'getByDokter']);
    });
});