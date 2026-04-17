<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pasien;
use App\Models\Dokter;
use App\Models\Pendaftaran;
use App\Models\RekamMedis;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overview()
    {
        $totalPasien = Pasien::count();
        $totalDokter = Dokter::where('status', true)->count();
        $totalPendaftaran = Pendaftaran::count();
        $pendaftaranHariIni = Pendaftaran::whereDate('tanggal_pendaftaran', now())->count();

        return response()->json([
            'totalPasien' => $totalPasien,
            'totalDokter' => $totalDokter,
            'totalPendaftaran' => $totalPendaftaran,
            'pendaftaranHariIni' => $pendaftaranHariIni,
        ], 200);
    }

    public function statistikPasien()
    {
        $pasienBaru = Pasien::whereDate('created_at', now())->count();
        $totalPasien = Pasien::count();
        $pasienAktif = Pendaftaran::where('status', '!=', 'cancelled')
            ->distinct()
            ->count('pasien_id');

        return response()->json([
            'pasienBaru' => $pasienBaru,
            'totalPasien' => $totalPasien,
            'pasienAktif' => $pasienAktif,
        ], 200);
    }

    public function statistikDokter()
    {
        $totalDokter = Dokter::count();
        $dokterAktif = Dokter::where('status', true)->count();
        $spesialisasi = Dokter::select('spesialisasi')
            ->groupBy('spesialisasi')
            ->with(['jadwalDokter'])
            ->get();

        return response()->json([
            'totalDokter' => $totalDokter,
            'dokterAktif' => $dokterAktif,
            'spesialisasi' => $spesialisasi,
        ], 200);
    }

    public function statistikPendaftaran()
    {
        $byStatus = Pendaftaran::select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->get();

        $hariIni = Pendaftaran::whereDate('tanggal_pendaftaran', now())
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->get();

        return response()->json([
            'byStatus' => $byStatus,
            'hariIni' => $hariIni,
        ], 200);
    }

    public function pendaftaranTerbaru()
    {
        $pendaftarans = Pendaftaran::with(['pasien', 'dokter'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $pendaftarans,
        ], 200);
    }

    public function aktivitasHariIni()
    {
        $pendaftaran = Pendaftaran::whereDate('tanggal_pendaftaran', now())
            ->orderBy('jam_kunjungan')
            ->get();

        $rekamMedis = RekamMedis::whereDate('tanggal_kunjungan', now())
            ->count();

        return response()->json([
            'pendaftaran' => $pendaftaran,
            'rekamMedis' => $rekamMedis,
        ], 200);
    }

    public function admin()
    {
        $totalPasien = Pasien::count();
        $totalDokter = Dokter::where('status', true)->count();
        $totalPendaftaran = Pendaftaran::count();
        $pendaftaranHariIni = Pendaftaran::whereDate('tanggal_pendaftaran', now())->count();
        $pendaftaranPending = Pendaftaran::where('status', 'pending')->count();

        return response()->json([
            'totalPasien' => $totalPasien,
            'totalDokter' => $totalDokter,
            'totalPendaftaran' => $totalPendaftaran,
            'pendaftaranHariIni' => $pendaftaranHariIni,
            'pendaftaranPending' => $pendaftaranPending,
        ], 200);
    }

    public function pasien(Request $request)
    {
        $user = $request->user();
        $pasien = Pasien::where('email', $user->email)->first();

        if (!$pasien) {
            return response()->json(['message' => 'Data pasien tidak ditemukan'], 404);
        }

        $pendaftaranTerbaru = Pendaftaran::where('pasien_id', $pasien->id)
            ->with(['dokter', 'jadwalDokter'])
            ->orderBy('created_at', 'desc')
            ->first();

        $totalKunjungan = RekamMedis::where('pasien_id', $pasien->id)->count();

        return response()->json([
            'pasien' => $pasien,
            'pendaftaranTerbaru' => $pendaftaranTerbaru,
            'totalKunjungan' => $totalKunjungan,
        ], 200);
    }
}
