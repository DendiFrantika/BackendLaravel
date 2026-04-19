<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pendaftaran;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PendaftaranController extends Controller
{
    public function index()
    {
        $pendaftarans = Pendaftaran::with(['pasien', 'dokter', 'jadwalDokter'])->paginate(15);
        return response()->json($pendaftarans, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pasien_id' => 'required|exists:pasiens,id',
            'dokter_id' => 'required|exists:dokters,id',
            'jadwal_dokter_id' => 'required|exists:jadwal_dokters,id',
            'tanggal_pendaftaran' => 'required|date|after_or_equal:today',
            'jam_kunjungan' => 'required|date_format:H:i',
            'keluhan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        if ($user && $user->role === 'pasien') {
            $pasien = Pasien::where('email', $user->email)->first();
            if (! $pasien || (int) $request->pasien_id !== (int) $pasien->id) {
                return response()->json([
                    'message' => 'Anda hanya dapat mendaftar untuk akun pasien Anda sendiri.',
                ], 403);
            }
        }

        $pendaftaran = Pendaftaran::create(array_merge($request->all(), [
            'status' => 'pending',
            'no_antrian' => $this->generateNoAntrian(),
        ]));

        return response()->json([
            'message' => 'Pendaftaran created successfully',
            'data' => $pendaftaran->load(['pasien', 'dokter']),
        ], 201);
    }

    public function show(Pendaftaran $pendaftaran)
    {
        return response()->json([
            'data' => $pendaftaran->load(['pasien', 'dokter', 'rekamMedis']),
        ], 200);
    }

    public function update(Request $request, Pendaftaran $pendaftaran)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|required|in:pending,confirmed,checked_in,completed,cancelled',
            'keluhan' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pendaftaran->update($request->all());

        return response()->json([
            'message' => 'Pendaftaran updated successfully',
            'data' => $pendaftaran,
        ], 200);
    }

    public function destroy(Pendaftaran $pendaftaran)
    {
        $pendaftaran->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Pendaftaran cancelled successfully',
        ], 200);
    }

    public function getByPasien($pasien_id)
    {
        $pendaftarans = Pendaftaran::where('pasien_id', $pasien_id)
            ->with(['dokter', 'jadwalDokter'])
            ->orderBy('tanggal_pendaftaran', 'desc')
            ->paginate(10);

        return response()->json($pendaftarans, 200);
    }

    public function getByDokter($dokter_id)
    {
        $pendaftarans = Pendaftaran::where('dokter_id', $dokter_id)
            ->with(['pasien'])
            ->orderBy('tanggal_pendaftaran', 'desc')
            ->paginate(10);

        return response()->json($pendaftarans, 200);
    }

    private function generateNoAntrian()
    {
        $today = now()->format('Ymd');
        $count = Pendaftaran::whereDate('created_at', now())->count() + 1;
        return 'ANT-' . $today . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    public function verifikasi(Request $request, $id)
    {
        $pendaftaran = Pendaftaran::find($id);

        if (!$pendaftaran) {
            return response()->json(['message' => 'Pendaftaran tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:confirmed,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pendaftaran->update([
            'status' => $request->status === 'confirmed' ? 'confirmed' : 'cancelled',
        ]);

        return response()->json([
            'message' => 'Pendaftaran ' . $request->status . ' successfully',
            'data' => $pendaftaran,
        ], 200);
    }

    public function riwayat(Request $request)
    {
        $user = $request->user();
        $pasien = Pasien::where('email', $user->email)->first();

        if (!$pasien) {
            return response()->json(['message' => 'Data pasien tidak ditemukan'], 404);
        }

        $riwayat = Pendaftaran::where('pasien_id', $pasien->id)
            ->with(['dokter', 'jadwalDokter', 'rekamMedis'])
            ->orderBy('tanggal_pendaftaran', 'desc')
            ->paginate(10);

        return response()->json($riwayat, 200);
    }

    public function antrian(Request $request)
    {
        $user = $request->user();
        $pasien = Pasien::where('email', $user->email)->first();

        if (!$pasien) {
            return response()->json(['message' => 'Data pasien tidak ditemukan'], 404);
        }

        $antrian = Pendaftaran::where('pasien_id', $pasien->id)
            ->where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->with(['dokter', 'jadwalDokter'])
            ->orderBy('tanggal_pendaftaran')
            ->first();

        if (!$antrian) {
            return response()->json(['message' => 'Tidak ada antrian'], 404);
        }

        return response()->json([
            'data' => $antrian,
        ], 200);
    }
}
