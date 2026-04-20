<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pasien;
use App\Models\Dokter;
use App\Models\Pendaftaran;
use App\Models\RekamMedis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

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

    /**
     * Aktivitas hari ini — mengembalikan flat array agar langsung
     * bisa dipakai oleh frontend tanpa transformasi tambahan.
     */
    public function aktivitasHariIni()
    {
        $activities = collect();

        // Pendaftaran hari ini
        $pendaftaran = Pendaftaran::with(['pasien', 'dokter'])
            ->whereDate('tanggal_pendaftaran', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'id'          => 'pendaftaran-' . $p->id,
                'description' => 'Pendaftaran baru: ' . ($p->pasien?->nama ?? '-') .
                                 ' ke dr. ' . ($p->dokter?->nama ?? '-'),
                'type'        => 'pendaftaran',
                'status'      => $p->status ?? '-',
                'timestamp'   => $p->created_at,
            ]);

        // Rekam medis hari ini
        $rekamMedis = RekamMedis::with(['pasien', 'dokter'])
            ->whereDate('tanggal_kunjungan', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => [
                'id'          => 'rekam-medis-' . $r->id,
                'description' => 'Rekam medis dicatat: ' . ($r->pasien?->nama ?? '-') .
                                 ' oleh dr. ' . ($r->dokter?->nama ?? '-'),
                'type'        => 'rekam_medis',
                'status'      => null,
                'timestamp'   => $r->created_at,
            ]);

        // Pasien baru hari ini
        $pasienBaru = Pasien::whereDate('created_at', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'id'          => 'pasien-' . $p->id,
                'description' => 'Pasien baru terdaftar: ' . $p->nama,
                'type'        => 'pasien_baru',
                'status'      => null,
                'timestamp'   => $p->created_at,
            ]);

        $result = $activities
            ->merge($pendaftaran)
            ->merge($rekamMedis)
            ->merge($pasienBaru)
            ->sortByDesc('timestamp')
            ->values();

        return response()->json($result, 200);
    }

    public function admin()
    {
        $totalPasien = Pasien::count();
        $totalDokter = Dokter::where('status', true)->count();
        $totalPendaftaran = Pendaftaran::count();
        $pendaftaranHariIni = Pendaftaran::whereDate('tanggal_pendaftaran', now())->count();
        $pendaftaranPending = Pendaftaran::where('status', 'pending')->count();

        $pasienBaru = Pasien::whereDate('created_at', now())->count();
        $pasienAktif = Pendaftaran::where('status', '!=', 'cancelled')
            ->distinct()
            ->count('pasien_id');

        $dokterSeluruh = Dokter::count();
        $spesialisasi = Dokter::select('spesialisasi', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('spesialisasi')
            ->get();

        return response()->json([
            'totalPasien'        => $totalPasien,
            'totalDokter'        => $totalDokter,
            'totalPendaftaran'   => $totalPendaftaran,
            'pendaftaranHariIni' => $pendaftaranHariIni,
            'pendaftaranPending' => $pendaftaranPending,
            'statistikPasien'    => [
                'pasienBaruHariIni' => $pasienBaru,
                'pasienAktif'       => $pasienAktif,
                'totalPasien'       => $totalPasien,
            ],
            'statistikDokter'    => [
                'totalDokterSeluruh' => $dokterSeluruh,
                'dokterAktif'        => $totalDokter,
                'spesialisasi'       => $spesialisasi,
            ],
        ], 200);
    }

    public function pasien(Request $request)
    {
        $user   = $request->user();
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
            'pasien'             => array_merge($pasien->toArray(), [
                'photo_url' => $this->photoUrlForUser($user->id),
            ]),
            'pendaftaranTerbaru' => $pendaftaranTerbaru,
            'totalKunjungan'     => $totalKunjungan,
        ], 200);
    }

    // ─── Recent Activities ────────────────────────────────────────────────────

    public function recentActivities()
    {
        $activities = collect();

        $pendaftaran = Pendaftaran::with(['pasien', 'dokter'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'id'          => 'pendaftaran-' . $p->id,
                'icon'        => '📋',
                'description' => 'Pendaftaran baru: ' . ($p->pasien?->nama ?? '-') .
                                 ' ke dr. ' . ($p->dokter?->nama ?? '-'),
                'type'        => 'pendaftaran',
                'timestamp'   => $p->created_at,
            ]);

        $rekamMedis = RekamMedis::with(['pasien', 'dokter'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'id'          => 'rekam-medis-' . $r->id,
                'icon'        => '🩺',
                'description' => 'Rekam medis: ' . ($r->pasien?->nama ?? '-') .
                                 ' oleh dr. ' . ($r->dokter?->nama ?? '-'),
                'type'        => 'rekam_medis',
                'timestamp'   => $r->created_at,
            ]);

        $pasienBaru = Pasien::orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'id'          => 'pasien-' . $p->id,
                'icon'        => '👤',
                'description' => 'Pasien baru terdaftar: ' . $p->nama,
                'type'        => 'pasien_baru',
                'timestamp'   => $p->created_at,
            ]);

        $result = $activities
            ->merge($pendaftaran)
            ->merge($rekamMedis)
            ->merge($pasienBaru)
            ->sortByDesc('timestamp')
            ->take(10)
            ->values();

        return response()->json($result, 200);
    }

    // ─── Chart Data ───────────────────────────────────────────────────────────

    public function chartData(Request $request)
    {
        $range = $request->get('range', 'week');

        switch ($range) {
            case 'month':
                // 4 minggu terakhir
                $data = collect();
                for ($i = 3; $i >= 0; $i--) {
                    $start = now()->startOfWeek()->subWeeks($i);
                    $end   = (clone $start)->endOfWeek();
                    $data->push([
                        'name'        => 'Minggu ' . (4 - $i),
                        'pendaftaran' => Pendaftaran::whereBetween('tanggal_pendaftaran', [$start, $end])->count(),
                        'pasien'      => Pasien::whereBetween('created_at', [$start, $end])->count(),
                    ]);
                }
                break;

            case 'year':
                // 12 bulan terakhir
                $data = collect();
                for ($i = 11; $i >= 0; $i--) {
                    $month = now()->subMonths($i);
                    $data->push([
                        'name'        => $month->translatedFormat('M'),
                        'pendaftaran' => Pendaftaran::whereYear('tanggal_pendaftaran', $month->year)
                                            ->whereMonth('tanggal_pendaftaran', $month->month)
                                            ->count(),
                        'pasien'      => Pasien::whereYear('created_at', $month->year)
                                            ->whereMonth('created_at', $month->month)
                                            ->count(),
                    ]);
                }
                break;

            default: // week — 7 hari terakhir
                $data = collect();
                for ($i = 6; $i >= 0; $i--) {
                    $day = now()->subDays($i);
                    $data->push([
                        'name'        => $day->translatedFormat('D'),
                        'pendaftaran' => Pendaftaran::whereDate('tanggal_pendaftaran', $day)->count(),
                        'pasien'      => Pasien::whereDate('created_at', $day)->count(),
                    ]);
                }
                break;
        }

        return response()->json($data, 200);
    }

    private function photoUrlForUser(int $userId): ?string
    {
        $dir = public_path('assets/profile');
        if (!File::isDirectory($dir)) {
            return null;
        }

        $matches = File::glob($dir . DIRECTORY_SEPARATOR . 'user-' . $userId . '.*');
        if (!$matches) {
            return null;
        }

        return asset('assets/profile/' . basename($matches[0]));
    }
}