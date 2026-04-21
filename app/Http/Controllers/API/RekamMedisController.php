<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RekamMedis;
use App\Models\Dokter;           // ✅ FIX: import Dokter
use App\Models\Pendaftaran;      // ✅ FIX: import Pendaftaran
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;   // ✅ FIX: import DB facade

class RekamMedisController extends Controller
{
    public function index()
    {
        $rekamMedis = RekamMedis::with(['pasien', 'dokter'])->paginate(15);
        return response()->json($rekamMedis, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pasien_id'          => 'required|exists:pasiens,id',
            'dokter_id'          => 'required|exists:dokters,id',
            'pendaftaran_id'     => 'nullable|exists:pendaftarans,id',
            'tanggal_kunjungan'  => 'required|date',
            'keluhan_utama'      => 'required|string',
            'diagnosis'          => 'required|string',
            'anamnesis'          => 'nullable|string',
            'pemeriksaan_fisik'  => 'nullable|string',
            'hasil_laboratorium' => 'nullable|string',
            'resep'              => 'nullable|string',
            'tindakan'           => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rekamMedis = RekamMedis::create($request->all());

        return response()->json([
            'message' => 'Rekam medis created successfully',
            'data'    => $rekamMedis->load(['pasien', 'dokter']),
        ], 201);
    }

    public function show(RekamMedis $rekamMedis)
    {
        return response()->json([
            'data' => $rekamMedis->load(['pasien', 'dokter']),
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $rekamMedis = RekamMedis::findOrFail($id);

        $validated = $request->validate([
            'pasien_id'          => 'required|exists:pasiens,id',
            'dokter_id'          => 'required|exists:dokters,id',
            'pendaftaran_id'     => 'nullable|exists:pendaftarans,id',
            'tanggal_kunjungan'  => 'required|date',
            'keluhan_utama'      => 'required|string',
            'diagnosis'          => 'required|string',
            'anamnesis'          => 'nullable|string',
            'pemeriksaan_fisik'  => 'nullable|string',
            'hasil_laboratorium' => 'nullable|string',
            'resep'              => 'nullable|string',
            'tindakan'           => 'nullable|string',
            'catatan_dokter'     => 'nullable|string',
        ]);

        $rekamMedis->update($validated);
        $rekamMedis->load(['pasien', 'dokter']);

        return response()->json([
            'message' => 'Rekam medis updated successfully',
            'data'    => $rekamMedis,
        ]);
    }

    public function destroy(RekamMedis $rekamMedis)
    {
        $rekamMedis->delete();

        return response()->json([
            'message' => 'Rekam medis deleted successfully',
        ], 200);
    }

    public function getByPasien($pasien_id)
    {
        $rekamMedis = RekamMedis::where('pasien_id', $pasien_id)
            ->with(['dokter'])
            ->orderBy('tanggal_kunjungan', 'desc')
            ->paginate(10);

        return response()->json($rekamMedis, 200);
    }

    public function getByDokter($dokter_id)
    {
        $rekamMedis = RekamMedis::where('dokter_id', $dokter_id)
            ->with(['pasien'])
            ->orderBy('tanggal_kunjungan', 'desc')
            ->paginate(10);

        return response()->json($rekamMedis, 200);
    }

    /**
     * Dokter menyimpan rekam medis dari halaman pemeriksaan.
     * Dipanggil via POST /dokter/rekam-medis
     */
    public function storeFromDokter(Request $request)
    {
        $user   = $request->user();
        // ✅ FIX: Dokter model sudah di-import
        $dokter = Dokter::where('email', $user->email)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'pendaftaran_id'     => 'required|exists:pendaftarans,id',
            'keluhan_utama'      => 'required|string',
            'anamnesis'          => 'nullable|string',
            'pemeriksaan_fisik'  => 'nullable|string',
            'hasil_laboratorium' => 'nullable|string',
            'catatan_dokter'     => 'nullable|string',
            // Vital signs — dikirim terpisah, digabung ke pemeriksaan_fisik
            'tekanan_darah'      => 'nullable|string',
            'nadi'               => 'nullable|string',
            'suhu'               => 'nullable|string',
            'respirasi'          => 'nullable|string',
            'berat_badan'        => 'nullable|string',
            'tinggi_badan'       => 'nullable|string',
            // ✅ FIX: diagnosis wajib array sesuai yang dikirim frontend
            'diagnosis'          => 'required|array|min:1',
            'diagnosis.*.code'   => 'required|string',
            'diagnosis.*.desc'   => 'required|string',
            'tindakan'           => 'nullable|array',
            'tindakan.*'         => 'string',
            'resep'              => 'nullable|array',
            'resep.*.nama_obat'  => 'required_with:resep|string',
            'resep.*.dosis'      => 'nullable|string',
            'resep.*.satuan'     => 'nullable|string',
            'resep.*.frekuensi'  => 'nullable|string',
            'resep.*.waktu'      => 'nullable|string',
            'resep.*.durasi'     => 'nullable|string',
            'resep.*.catatan'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // ✅ FIX: Pendaftaran model sudah di-import
        $pendaftaran = Pendaftaran::with('pasien')->findOrFail($request->pendaftaran_id);

        // Pastikan pendaftaran memang milik dokter yang login
        if ((int) $pendaftaran->dokter_id !== (int) $dokter->id) {
            return response()->json(['message' => 'Anda tidak berhak mengakses pendaftaran ini.'], 403);
        }

        // ── Gabungkan vital signs ke kolom pemeriksaan_fisik ──
        $vitals = array_filter([
            'Tekanan Darah' => $request->tekanan_darah,
            'Nadi'          => $request->nadi,
            'Suhu'          => $request->suhu,
            'Respirasi'     => $request->respirasi,
            'Berat Badan'   => $request->berat_badan,
            'Tinggi Badan'  => $request->tinggi_badan,
        ]);

        $vitalText = '';
        if (!empty($vitals)) {
            $vitalText = "=== VITAL SIGNS ===\n";
            foreach ($vitals as $label => $val) {
                $vitalText .= "{$label}: {$val}\n";
            }
            $vitalText .= "\n";
        }
        $pemeriksaanFisik = trim($vitalText . ($request->pemeriksaan_fisik ?? '')) ?: null;

        // ── Format diagnosis array → string "A09 - Diare; I10 - Hipertensi" ──
        $diagnosisStr = collect($request->diagnosis)
            ->map(fn($d) => "{$d['code']} - {$d['desc']}")
            ->implode('; ');

        // ── Format tindakan array → string ──
        $tindakanStr = !empty($request->tindakan)
            ? implode(', ', $request->tindakan)
            : null;

        // ── Resep array → JSON string ──
        $resepStr = !empty($request->resep)
            ? json_encode($request->resep, JSON_UNESCAPED_UNICODE)
            : null;

        DB::beginTransaction();
        try {
            $rekamMedis = RekamMedis::create([
                'pasien_id'          => $pendaftaran->pasien_id,
                'dokter_id'          => $dokter->id,
                'pendaftaran_id'     => $pendaftaran->id,
                'tanggal_kunjungan'  => now()->toDateString(),
                'keluhan_utama'      => $request->keluhan_utama,
                'anamnesis'          => $request->anamnesis,
                'pemeriksaan_fisik'  => $pemeriksaanFisik,
                'hasil_laboratorium' => $request->hasil_laboratorium,
                'diagnosis'          => $diagnosisStr,
                'tindakan'           => $tindakanStr,
                'resep'              => $resepStr,
                'catatan_dokter'     => $request->catatan_dokter,
            ]);

            // Otomatis ubah status pendaftaran → completed
            $pendaftaran->update(['status' => 'completed']);

            DB::commit();

            return response()->json([
                'message' => 'Rekam medis berhasil disimpan. Status pasien: Selesai.',
                'data'    => $rekamMedis->load(['pasien', 'dokter']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ambil rekam medis berdasarkan pendaftaran_id.
     * Dipakai frontend dokter saat halaman rekam medis dibuka.
     */
    public function showByPendaftaran($pendaftaran_id)
    {
        $rekamMedis = RekamMedis::with(['pasien', 'dokter', 'pendaftaran.pasien'])
            ->where('pendaftaran_id', $pendaftaran_id)
            ->first();

        if (!$rekamMedis) {
            return response()->json(['data' => null], 200);
        }

        $data = $rekamMedis->toArray();

        // Parse resep JSON string → array supaya frontend bisa render
        try {
            $decoded = json_decode($rekamMedis->resep, true);
            $data['resep_parsed'] = is_array($decoded) ? $decoded : [];
        } catch (\Exception $e) {
            $data['resep_parsed'] = [];
        }

        return response()->json(['data' => $data], 200);
    }

    // Ambil rekam medis milik dokter yang sedang login
public function getByDokterAuth(Request $request)
{
    $dokter = Dokter::where('email', $request->user()->email)->firstOrFail();

    $rekamMedis = RekamMedis::where('dokter_id', $dokter->id)
        ->with(['pasien'])
        ->orderBy('tanggal_kunjungan', 'desc')
        ->paginate(15);

    return response()->json($rekamMedis, 200);
}

public function riwayatDokter(Request $request)
{
    $user = $request->user();

    $dokter = \App\Models\Dokter::where('email', $user->email)->first();

    if (!$dokter) {
        return response()->json([
            'message' => 'Dokter tidak ditemukan'
        ], 404);
    }

    $riwayat = \App\Models\RekamMedis::with('pasien')
        ->where('dokter_id', $dokter->id)
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'data' => $riwayat
    ]);
}
}
