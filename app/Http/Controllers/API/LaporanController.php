<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pasien;
use App\Models\Pendaftaran;
use App\Models\RekamMedis;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function laporanPasien(Request $request)
    {
        $query = Pasien::withCount(['pendaftaran', 'rekamMedis'])->orderByDesc('created_at');

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_akhir')) {
            $query->whereBetween('created_at', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->jenis_kelamin);
        }

        if ($request->filled('status_pernikahan')) {
            $query->where('status_pernikahan', $request->status_pernikahan);
        }

        if ($request->filled('search')) {
            $search = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($request->search)) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', $search)
                  ->orWhere('no_identitas', 'like', $search)
                  ->orWhere('no_pendaftaran', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('no_telepon', 'like', $search);
            });
        }

        $laporan = $query->get();

        $summary = [
            'total_pasien' => Pasien::count(),
            'pasien_baru_hari_ini' => Pasien::whereDate('created_at', now())->count(),
            'pasien_baru_bulan_ini' => Pasien::whereMonth('created_at', now())->whereYear('created_at', now())->count(),
            'jenis_kelamin' => Pasien::select('jenis_kelamin')
                ->selectRaw('count(*) as total')
                ->groupBy('jenis_kelamin')
                ->get(),
        ];

        return response()->json([
            'summary' => $summary,
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
        $query = Pasien::withCount(['pendaftaran', 'rekamMedis'])->orderByDesc('created_at');

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_akhir')) {
            $query->whereBetween('created_at', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->jenis_kelamin);
        }

        if ($request->filled('status_pernikahan')) {
            $query->where('status_pernikahan', $request->status_pernikahan);
        }

        if ($request->filled('search')) {
            $search = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($request->search)) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', $search)
                  ->orWhere('no_identitas', 'like', $search)
                  ->orWhere('no_pendaftaran', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('no_telepon', 'like', $search);
            });
        }

        $data = $query->get();

        $summary = [
            'total_pasien' => Pasien::count(),
            'pasien_baru_hari_ini' => Pasien::whereDate('created_at', now())->count(),
            'pasien_baru_bulan_ini' => Pasien::whereMonth('created_at', now())->whereYear('created_at', now())->count(),
        ];

        $filters = $request->only(['tanggal_mulai', 'tanggal_akhir', 'jenis_kelamin', 'status_pernikahan', 'search']);

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>' .
                    'body{font-family:Arial,sans-serif;font-size:12px;color:#333;}' .
                    '.header{text-align:center;margin-bottom:20px;}' .
                    '.header h1{margin:0;font-size:18px;}' .
                    '.section{margin-bottom:16px;}' .
                    'table{width:100%;border-collapse:collapse;}' .
                    'th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;}' .
                    'th{background:#f4f4f4;}' .
                '</style></head><body>' .
                '<div class="header"><h1>Laporan Pasien</h1>' .
                '<div class="small">Dicetak: ' . now()->format('d/m/Y H:i') . '</div></div>' .
                '<div class="section"><strong>Filter</strong>' .
                '<div>Tanggal: ' . ($filters['tanggal_mulai'] ?? '-') . ' - ' . ($filters['tanggal_akhir'] ?? '-') . '</div>' .
                '<div>Jenis Kelamin: ' . ($filters['jenis_kelamin'] ?? '-') . '</div>' .
                '<div>Status Pernikahan: ' . ($filters['status_pernikahan'] ?? '-') . '</div>' .
                '<div>Pencarian: ' . ($filters['search'] ?? '-') . '</div></div>' .
                '<div class="section"><strong>Ringkasan</strong>' .
                '<div>Total Pasien: ' . number_format($summary['total_pasien']) . '</div>' .
                '<div>Pasien Baru Hari Ini: ' . number_format($summary['pasien_baru_hari_ini']) . '</div>' .
                '<div>Pasien Baru Bulan Ini: ' . number_format($summary['pasien_baru_bulan_ini']) . '</div></div>' .
                '<table><thead><tr><th>No</th><th>No. Pendaftaran</th><th>Nama</th><th>No. Identitas</th><th>Jenis Kelamin</th><th>Tanggal Lahir</th><th>No. Telepon</th><th>Email</th><th>Pendaftaran</th><th>Rekam Medis</th></tr></thead><tbody>';

        foreach ($data as $index => $pasien) {
            $html .= '<tr>' .
                '<td>' . ($index + 1) . '</td>' .
                '<td>' . $pasien->no_pendaftaran . '</td>' .
                '<td>' . $pasien->nama . '</td>' .
                '<td>' . $pasien->no_identitas . '</td>' .
                '<td>' . ($pasien->jenis_kelamin ?? '-') . '</td>' .
                '<td>' . ($pasien->tanggal_lahir ? $pasien->tanggal_lahir->format('d/m/Y') : '-') . '</td>' .
                '<td>' . ($pasien->no_telepon ?? '-') . '</td>' .
                '<td>' . ($pasien->email ?? '-') . '</td>' .
                '<td>' . ($pasien->pendaftaran_count ?? 0) . '</td>' .
                '<td>' . ($pasien->rekam_medis_count ?? 0) . '</td>' .
                '</tr>';
        }

        $html .= '</tbody></table></body></html>';

        $pdf = Pdf::loadHTML($html);

        $filename = 'laporan_pasien_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    public function exportExcel(Request $request)
    {
        return response()->json([
            'message' => 'Excel export akan diimplementasikan',
            'tipe' => $request->tipe,
        ], 200);
    }
}
