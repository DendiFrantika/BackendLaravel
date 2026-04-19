<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RekamMedis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'pasien_id' => 'required|exists:pasiens,id',
            'dokter_id' => 'required|exists:dokters,id',
            'pendaftaran_id' => 'nullable|exists:pendaftarans,id',
            'tanggal_kunjungan' => 'required|date',
            'keluhan_utama' => 'required|string',
            'diagnosis' => 'required|string',
            'anamnesis' => 'nullable|string',
            'pemeriksaan_fisik' => 'nullable|string',
            'hasil_laboratorium' => 'nullable|string',
            'resep' => 'nullable|string',
            'tindakan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rekamMedis = RekamMedis::create($request->all());

        return response()->json([
            'message' => 'Rekam medis created successfully',
            'data' => $rekamMedis->load(['pasien', 'dokter']),
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
    $rekamMedis = \App\Models\RekamMedis::findOrFail($id);

    $validated = $request->validate([
        'pasien_id' => 'required|exists:pasiens,id',
        'dokter_id' => 'required|exists:dokters,id',
        'pendaftaran_id' => 'nullable|exists:pendaftarans,id',
        'tanggal_kunjungan' => 'required|date',
        'keluhan_utama' => 'required|string',
        'diagnosis' => 'required|string',
        'anamnesis' => 'nullable|string',
        'pemeriksaan_fisik' => 'nullable|string',
        'hasil_laboratorium' => 'nullable|string',
        'resep' => 'nullable|string',
        'tindakan' => 'nullable|string',
        'catatan_dokter' => 'nullable|string',
    ]);

    $rekamMedis->update($validated);

    // 🔥 ambil ulang + relasi (PENTING!)
    $rekamMedis->load(['pasien', 'dokter']);

    return response()->json([
        'message' => 'Rekam medis updated successfully',
        'data' => $rekamMedis
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
}
