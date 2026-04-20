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

    // ─── Helper: shared HTML wrapper ───────────────────────────────────────────

    private function buildHtmlWrapper(string $title, string $bodyContent, array $filters = []): string
    {
        $filterRows = '';
        if (!empty($filters)) {
            $filterRows .=
                '<div class="section"><strong>Filter</strong>' .
                '<div>Tanggal: ' . ($filters['tanggal_mulai'] ?? '-') . ' s/d ' . ($filters['tanggal_akhir'] ?? '-') . '</div>';

            if (isset($filters['jenis_kelamin'])) {
                $filterRows .= '<div>Jenis Kelamin: ' . ($filters['jenis_kelamin'] ?: '-') . '</div>';
            }
            if (isset($filters['status_pernikahan'])) {
                $filterRows .= '<div>Status Pernikahan: ' . ($filters['status_pernikahan'] ?: '-') . '</div>';
            }
            if (isset($filters['search'])) {
                $filterRows .= '<div>Pencarian: ' . ($filters['search'] ?: '-') . '</div>';
            }
            $filterRows .= '</div>';
        }

        return
            '<!doctype html><html><head><meta charset="utf-8"><style>' .
                'body{font-family:Arial,sans-serif;font-size:12px;color:#333;margin:0;padding:16px;}' .
                '.header{text-align:center;margin-bottom:20px;border-bottom:2px solid #3498db;padding-bottom:12px;}' .
                '.header h1{margin:0 0 4px;font-size:18px;color:#2c3e50;}' .
                '.header .small{color:#888;font-size:11px;}' .
                '.section{margin-bottom:14px;}' .
                '.section strong{display:block;margin-bottom:4px;color:#2c3e50;}' .
                'table{width:100%;border-collapse:collapse;margin-top:8px;}' .
                'th{background:#3498db;color:#fff;padding:7px 8px;text-align:left;font-size:11px;}' .
                'td{border:1px solid #ddd;padding:6px 8px;font-size:11px;}' .
                'tr:nth-child(even) td{background:#f9f9f9;}' .
                '.badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:bold;}' .
                '.badge-success{background:#d4edda;color:#155724;}' .
                '.badge-warning{background:#fff3cd;color:#856404;}' .
                '.badge-danger{background:#f8d7da;color:#721c24;}' .
                '.badge-secondary{background:#e2e3e5;color:#383d41;}' .
            '</style></head><body>' .
            '<div class="header"><h1>' . $title . '</h1>' .
            '<div class="small">Dicetak: ' . now()->format('d/m/Y H:i') . '</div></div>' .
            $filterRows .
            $bodyContent .
            '</body></html>';
    }

    // ─── Export: Pasien ────────────────────────────────────────────────────────

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

        $summaryHtml =
            '<div class="section"><strong>Ringkasan</strong>' .
            '<div>Total Pasien: ' . number_format($summary['total_pasien']) . '</div>' .
            '<div>Pasien Baru Hari Ini: ' . number_format($summary['pasien_baru_hari_ini']) . '</div>' .
            '<div>Pasien Baru Bulan Ini: ' . number_format($summary['pasien_baru_bulan_ini']) . '</div>' .
            '</div>';

        $tableHtml =
            '<table><thead><tr>' .
            '<th>No</th><th>No. Pendaftaran</th><th>Nama</th><th>No. Identitas</th>' .
            '<th>Jenis Kelamin</th><th>Tanggal Lahir</th><th>No. Telepon</th>' .
            '<th>Email</th><th>Pendaftaran</th><th>Rekam Medis</th>' .
            '</tr></thead><tbody>';

        foreach ($data as $index => $pasien) {
            $tableHtml .=
                '<tr>' .
                '<td>' . ($index + 1) . '</td>' .
                '<td>' . e($pasien->no_pendaftaran) . '</td>' .
                '<td>' . e($pasien->nama) . '</td>' .
                '<td>' . e($pasien->no_identitas) . '</td>' .
                '<td>' . e($pasien->jenis_kelamin ?? '-') . '</td>' .
                '<td>' . ($pasien->tanggal_lahir ? $pasien->tanggal_lahir->format('d/m/Y') : '-') . '</td>' .
                '<td>' . e($pasien->no_telepon ?? '-') . '</td>' .
                '<td>' . e($pasien->email ?? '-') . '</td>' .
                '<td>' . ($pasien->pendaftaran_count ?? 0) . '</td>' .
                '<td>' . ($pasien->rekam_medis_count ?? 0) . '</td>' .
                '</tr>';
        }

        $tableHtml .= '</tbody></table>';

        $html = $this->buildHtmlWrapper('Laporan Pasien', $summaryHtml . $tableHtml, $filters);
        $pdf  = Pdf::loadHTML($html)->setPaper('a4', 'landscape');

        return $pdf->download('laporan_pasien_' . now()->format('Ymd_His') . '.pdf');
    }

    // ─── Export: Rekam Medis ───────────────────────────────────────────────────

    public function exportRekamMedisPDF(Request $request)
    {
        $query = RekamMedis::with(['pasien', 'dokter']);

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_akhir')) {
            $query->whereBetween('tanggal_kunjungan', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        if ($request->filled('dokter_id')) {
            $query->where('dokter_id', $request->dokter_id);
        }

        if ($request->filled('pasien_id')) {
            $query->where('pasien_id', $request->pasien_id);
        }

        $data    = $query->orderByDesc('tanggal_kunjungan')->get();
        $filters = $request->only(['tanggal_mulai', 'tanggal_akhir']);

        $summaryHtml =
            '<div class="section"><strong>Ringkasan</strong>' .
            '<div>Total Rekam Medis: ' . number_format($data->count()) . '</div>' .
            '</div>';

        $tableHtml =
            '<table><thead><tr>' .
            '<th>No</th><th>Pasien</th><th>Dokter</th><th>Tanggal Kunjungan</th>' .
            '<th>Keluhan</th><th>Diagnosis</th><th>Resep / Tindakan</th>' .
            '</tr></thead><tbody>';

        foreach ($data as $index => $item) {
            $tableHtml .=
                '<tr>' .
                '<td>' . ($index + 1) . '</td>' .
                '<td>' . e($item->pasien?->nama ?? $item->pasien?->name ?? '-') . '</td>' .
                '<td>' . e($item->dokter?->nama ?? $item->dokter?->name ?? '-') . '</td>' .
                '<td>' . ($item->tanggal_kunjungan
                    ? \Carbon\Carbon::parse($item->tanggal_kunjungan)->format('d/m/Y')
                    : '-') . '</td>' .
                '<td>' . e($item->keluhan_utama ?? '-') . '</td>' .
                '<td>' . e($item->diagnosis ?? '-') . '</td>' .
                '<td>' . e($item->tindakan ?? $item->resep ?? '-') . '</td>' .
                '</tr>';
        }

        $tableHtml .= '</tbody></table>';

        $html = $this->buildHtmlWrapper('Laporan Rekam Medis', $summaryHtml . $tableHtml, $filters);
        $pdf  = Pdf::loadHTML($html)->setPaper('a4', 'landscape');

        return $pdf->download('laporan_rekam_medis_' . now()->format('Ymd_His') . '.pdf');
    }

    // ─── Export: Pendaftaran ───────────────────────────────────────────────────

    public function exportPendaftaranPDF(Request $request)
    {
        $query = Pendaftaran::select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status');

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_akhir')) {
            $query->whereBetween('tanggal_pendaftaran', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        $data    = $query->get();
        $filters = $request->only(['tanggal_mulai', 'tanggal_akhir']);

        $grandTotal = $data->sum('total');

        $summaryHtml =
            '<div class="section"><strong>Ringkasan</strong>' .
            '<div>Total Pendaftaran: ' . number_format($grandTotal) . '</div>' .
            '</div>';

        // Badge warna berdasarkan status
        $badgeMap = [
            'selesai'   => 'badge-success',
            'menunggu'  => 'badge-warning',
            'dibatalkan'=> 'badge-danger',
        ];

        $tableHtml =
            '<table><thead><tr>' .
            '<th>No</th><th>Status</th><th>Total</th><th>Persentase</th>' .
            '</tr></thead><tbody>';

        foreach ($data as $index => $item) {
            $status    = $item->status ?? '-';
            $badgeClass = $badgeMap[strtolower($status)] ?? 'badge-secondary';
            $persen    = $grandTotal > 0
                ? number_format(($item->total / $grandTotal) * 100, 1) . '%'
                : '0%';

            $tableHtml .=
                '<tr>' .
                '<td>' . ($index + 1) . '</td>' .
                '<td><span class="badge ' . $badgeClass . '">' . e($status) . '</span></td>' .
                '<td>' . number_format($item->total) . '</td>' .
                '<td>' . $persen . '</td>' .
                '</tr>';
        }

        // Baris total
        $tableHtml .=
            '<tr style="font-weight:bold;background:#eaf4fb;">' .
            '<td colspan="2">TOTAL</td>' .
            '<td>' . number_format($grandTotal) . '</td>' .
            '<td>100%</td>' .
            '</tr>';

        $tableHtml .= '</tbody></table>';

        $html = $this->buildHtmlWrapper('Laporan Pendaftaran', $summaryHtml . $tableHtml, $filters);
        $pdf  = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        return $pdf->download('laporan_pendaftaran_' . now()->format('Ymd_His') . '.pdf');
    }

    // ─── Export: Dokter ────────────────────────────────────────────────────────

    public function exportDokterPDF(Request $request)
    {
        $query = RekamMedis::select('dokter_id')
            ->selectRaw('count(*) as jumlah_pasien')
            ->selectRaw('count(distinct pasien_id) as pasien_unik')
            ->with('dokter')
            ->groupBy('dokter_id')
            ->orderByDesc('jumlah_pasien');

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_akhir')) {
            $query->whereBetween('tanggal_kunjungan', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        $data    = $query->get();
        $filters = $request->only(['tanggal_mulai', 'tanggal_akhir']);

        $totalKunjungan = $data->sum('jumlah_pasien');
        $totalUnik      = $data->sum('pasien_unik');

        $summaryHtml =
            '<div class="section"><strong>Ringkasan</strong>' .
            '<div>Total Dokter Aktif: ' . number_format($data->count()) . '</div>' .
            '<div>Total Kunjungan: ' . number_format($totalKunjungan) . '</div>' .
            '<div>Total Pasien Unik: ' . number_format($totalUnik) . '</div>' .
            '</div>';

        $tableHtml =
            '<table><thead><tr>' .
            '<th>No</th><th>Nama Dokter</th><th>Jumlah Kunjungan</th>' .
            '<th>Pasien Unik</th><th>Rata-rata Kunjungan/Pasien</th>' .
            '</tr></thead><tbody>';

        foreach ($data as $index => $item) {
            $namaDokter = $item->dokter?->nama ?? $item->dokter?->name ?? 'Dokter ' . $item->dokter_id;
            $rataRata   = $item->pasien_unik > 0
                ? number_format($item->jumlah_pasien / $item->pasien_unik, 1)
                : '0';

            $tableHtml .=
                '<tr>' .
                '<td>' . ($index + 1) . '</td>' .
                '<td>' . e($namaDokter) . '</td>' .
                '<td>' . number_format($item->jumlah_pasien) . '</td>' .
                '<td>' . number_format($item->pasien_unik) . '</td>' .
                '<td>' . $rataRata . 'x</td>' .
                '</tr>';
        }

        // Baris total
        $tableHtml .=
            '<tr style="font-weight:bold;background:#eaf4fb;">' .
            '<td colspan="2">TOTAL</td>' .
            '<td>' . number_format($totalKunjungan) . '</td>' .
            '<td>' . number_format($totalUnik) . '</td>' .
            '<td>-</td>' .
            '</tr>';

        $tableHtml .= '</tbody></table>';

        $html = $this->buildHtmlWrapper('Laporan Dokter', $summaryHtml . $tableHtml, $filters);
        $pdf  = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        return $pdf->download('laporan_dokter_' . now()->format('Ymd_His') . '.pdf');
    }

    // ─── Helper: paksa nilai sebagai teks di Excel ────────────────────────────

    /**
     * Wrap angka panjang (NIK, telepon) dengan =\"...\" agar Excel
     * tidak mengkonversinya ke notasi ilmiah (3,2E+15 dst).
     */
    private function asText(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '-') {
            return '-';
        }
        $str = (string) $value;
        // Angka >= 10 digit → paksa sebagai teks
        if (preg_match('/^\d{10,}$/', $str)) {
            return '="' . $str . '"';
        }
        return $str;
    }

    // ─── Helper: stream CSV response ──────────────────────────────────────────

    private function csvResponse(string $filename, array $headers, array $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $callback = function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // BOM agar Excel membaca UTF-8 dengan benar
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ]);
    }

    // ─── Export CSV: Pasien ────────────────────────────────────────────────────

    public function exportPasienCsv(Request $request)
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

        $headers = [
            'No', 'No. Pendaftaran', 'Nama', 'No. Identitas',
            'Jenis Kelamin', 'Tanggal Lahir', 'No. Telepon',
            'Email', 'Jumlah Pendaftaran', 'Jumlah Rekam Medis',
        ];

        $rows = $data->map(fn($p, $i) => [
            $i + 1,
            $p->no_pendaftaran ?? '-',
            $p->nama ?? '-',
            $this->asText($p->no_identitas),   // paksa teks agar tidak jadi notasi ilmiah
            $p->jenis_kelamin ?? '-',
            $p->tanggal_lahir ? $p->tanggal_lahir->format('d/m/Y') : '-',
            $this->asText($p->no_telepon),      // paksa teks
            $p->email ?? '-',
            $p->pendaftaran_count ?? 0,
            $p->rekam_medis_count ?? 0,
        ])->toArray();

        $filename = 'laporan_pasien_' . now()->format('Ymd_His') . '.csv';

        return $this->csvResponse($filename, $headers, $rows);
    }

    // ─── Export CSV: Rekam Medis ───────────────────────────────────────────────

    public function exportRekamMedisCsv(Request $request)
    {
        $query = RekamMedis::with(['pasien', 'dokter']);

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_akhir')) {
            $query->whereBetween('tanggal_kunjungan', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        if ($request->filled('dokter_id')) {
            $query->where('dokter_id', $request->dokter_id);
        }

        if ($request->filled('pasien_id')) {
            $query->where('pasien_id', $request->pasien_id);
        }

        $data = $query->orderByDesc('tanggal_kunjungan')->get();

        $headers = [
            'No', 'Pasien', 'Dokter', 'Tanggal Kunjungan',
            'Keluhan', 'Diagnosis', 'Resep / Tindakan',
        ];

        $rows = $data->map(fn($item, $i) => [
            $i + 1,
            $item->pasien?->nama ?? '-',
            $item->dokter?->nama ?? '-',
            $item->tanggal_kunjungan
                ? \Carbon\Carbon::parse($item->tanggal_kunjungan)->format('d/m/Y')
                : '-',
            $item->keluhan_utama ?? '-',
            $item->diagnosis ?? '-',
            $item->tindakan ?? $item->resep ?? '-',
        ])->toArray();

        $filename = 'laporan_rekam_medis_' . now()->format('Ymd_His') . '.csv';

        return $this->csvResponse($filename, $headers, $rows);
    }

    // ─── Export CSV: Pendaftaran ───────────────────────────────────────────────

    public function exportPendaftaranCsv(Request $request)
    {
        $query = Pendaftaran::select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status');

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_akhir')) {
            $query->whereBetween('tanggal_pendaftaran', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        $data       = $query->get();
        $grandTotal = $data->sum('total');

        $headers = ['No', 'Status', 'Total', 'Persentase'];

        $rows = $data->map(fn($item, $i) => [
            $i + 1,
            $item->status ?? '-',
            $item->total ?? 0,
            $grandTotal > 0
                ? number_format(($item->total / $grandTotal) * 100, 1) . '%'
                : '0%',
        ])->toArray();

        // Baris total
        $rows[] = ['', 'TOTAL', $grandTotal, '100%'];

        $filename = 'laporan_pendaftaran_' . now()->format('Ymd_His') . '.csv';

        return $this->csvResponse($filename, $headers, $rows);
    }

    // ─── Export CSV: Dokter ────────────────────────────────────────────────────

    public function exportDokterCsv(Request $request)
    {
        $query = RekamMedis::select('dokter_id')
            ->selectRaw('count(*) as jumlah_pasien')
            ->selectRaw('count(distinct pasien_id) as pasien_unik')
            ->with('dokter')
            ->groupBy('dokter_id')
            ->orderByDesc('jumlah_pasien');

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_akhir')) {
            $query->whereBetween('tanggal_kunjungan', [
                $request->tanggal_mulai,
                $request->tanggal_akhir,
            ]);
        }

        $data           = $query->get();
        $totalKunjungan = $data->sum('jumlah_pasien');
        $totalUnik      = $data->sum('pasien_unik');

        $headers = [
            'No', 'Nama Dokter', 'Jumlah Kunjungan',
            'Pasien Unik', 'Rata-rata Kunjungan/Pasien',
        ];

        $rows = $data->map(fn($item, $i) => [
            $i + 1,
            $item->dokter?->nama ?? $item->dokter?->name ?? 'Dokter ' . $item->dokter_id,
            $item->jumlah_pasien ?? 0,
            $item->pasien_unik ?? 0,
            $item->pasien_unik > 0
                ? number_format($item->jumlah_pasien / $item->pasien_unik, 1) . 'x'
                : '0x',
        ])->toArray();

        // Baris total
        $rows[] = ['', 'TOTAL', $totalKunjungan, $totalUnik, '-'];

        $filename = 'laporan_dokter_' . now()->format('Ymd_His') . '.csv';

        return $this->csvResponse($filename, $headers, $rows);
    }
}