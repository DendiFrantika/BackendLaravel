<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pendaftaran;
use App\Models\RekamMedis;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function laporanPasien(Request $request)
    {
        $query = Pendaftaran::with(['pasien', 'dokter']);

        if ($request->has('tanggal_mulai') && $request->has('tanggal_akhir')) {
            $query->whereBetween('tanggal_pendaftaran', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('dokter_id')) {
            $query->where('dokter_id', $request->dokter_id);
        }

        $laporan = $query->get();

        return response()->json([
            'total' => $laporan->count(),
            'data' => $laporan,
        ], 200);
    }

    public function index(Request $request)
    {
        return $this->laporanPasien($request);
    }

    public function laporanRekamMedis(Request $request)
    {
        $query = RekamMedis::with(['pasien', 'dokter']);

        if ($request->has('tanggal_mulai') && $request->has('tanggal_akhir')) {
            $query->whereBetween('tanggal_kunjungan', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        if ($request->has('dokter_id')) {
            $query->where('dokter_id', $request->dokter_id);
        }

        if ($request->has('pasien_id')) {
            $query->where('pasien_id', $request->pasien_id);
        }

        $laporan = $query->get();

        return response()->json([
            'total' => $laporan->count(),
            'data' => $laporan,
        ], 200);
    }

    public function laporanDokter(Request $request)
    {
        $query = RekamMedis::select('dokter_id')
            ->selectRaw('count(*) as jumlah_pasien')
            ->selectRaw('count(distinct pasien_id) as pasien_unik')
            ->with('dokter')
            ->groupBy('dokter_id');

        if ($request->has('tanggal_mulai') && $request->has('tanggal_akhir')) {
            $query->whereBetween('tanggal_kunjungan', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        $laporan = $query->get();

        return response()->json([
            'total' => $laporan->count(),
            'data' => $laporan,
        ], 200);
    }

    public function laporanPendaftaran(Request $request)
    {
        $query = Pendaftaran::select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status');

        if ($request->has('tanggal_mulai') && $request->has('tanggal_akhir')) {
            $query->whereBetween('tanggal_pendaftaran', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        $laporan = $query->get();

        return response()->json([
            'data' => $laporan,
        ], 200);
    }

    public function exportPDF(Request $request)
    {
        return response()->json([
            'message' => 'PDF export akan diimplementasikan',
            'tipe' => $request->tipe,
        ], 200);
    }

    public function exportExcel(Request $request)
    {
        return response()->json([
            'message' => 'Excel export akan diimplementasikan',
            'tipe' => $request->tipe,
        ], 200);
    }
}
