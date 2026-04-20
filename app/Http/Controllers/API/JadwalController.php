<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\JadwalDokter;
use App\Models\Dokter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JadwalController extends Controller
{
    public function index()
    {
        $jadwals = JadwalDokter::with('dokter')->paginate(15);
        return response()->json($jadwals, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dokter_id' => 'required|exists:dokters,id',
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'kapasitas' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jadwal = JadwalDokter::create($request->all());

        return response()->json([
            'message' => 'Jadwal created successfully',
            'data' => $jadwal->load('dokter'),
        ], 201);
    }

    public function show(JadwalDokter $jadwal)
    {
        return response()->json([
            'data' => $jadwal->load('dokter'),
        ], 200);
    }

    public function update(Request $request, JadwalDokter $jadwal)
    {
        $validator = Validator::make($request->all(), [
            'jam_mulai' => 'sometimes|required|date_format:H:i',
            'jam_selesai' => 'sometimes|required|date_format:H:i',
            'kapasitas' => 'sometimes|required|integer|min:1',
            'status' => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jadwal->update($request->all());

        return response()->json([
            'message' => 'Jadwal updated successfully',
            'data' => $jadwal,
        ], 200);
    }

    public function destroy(JadwalDokter $jadwal)
    {
        $jadwal->delete();

        return response()->json([
            'message' => 'Jadwal deleted successfully',
        ], 200);
    }

    public function getByDokter($dokter_id)
    {
        $jadwals = JadwalDokter::where('dokter_id', $dokter_id)
            ->where('status', true)
            ->get();

        // Fallback agar dropdown jadwal tetap terisi bila data lama status-nya belum diset.
        if ($jadwals->isEmpty()) {
            $jadwals = JadwalDokter::where('dokter_id', $dokter_id)->get();
        }

        return response()->json([
            'data' => $jadwals,
        ], 200);
    }

   public function getJadwalByLogin(Request $request)
{
    try {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        // ⚠️ PENTING: sesuaikan mapping user ke dokter
        // kalau user email = dokter email
        $dokter = Dokter::where('email', $user->email)->first();

        if (!$dokter) {
            return response()->json([
                'message' => 'Data dokter tidak ditemukan'
            ], 404);
        }

        $jadwal = JadwalDokter::where('dokter_id', $dokter->id)
            ->where('status', true)
            ->get();

        // fallback kalau tidak ada status true
        if ($jadwal->isEmpty()) {
            $jadwal = JadwalDokter::where('dokter_id', $dokter->id)->get();
        }

        return response()->json([
            'dokter' => [
                'id' => $dokter->id,
                'nama' => $dokter->nama,
                'spesialisasi' => $dokter->spesialisasi,
                'no_telepon' => $dokter->no_telepon,
                'email' => $dokter->email,
            ],
            'jadwal' => $jadwal
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}
}
