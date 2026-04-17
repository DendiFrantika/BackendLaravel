<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Dokter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DokterController extends Controller
{
    public function index()
    {
        $dokters = Dokter::where('status', true)->paginate(15);
        return response()->json($dokters, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'no_identitas' => 'required|unique:dokters',
            'spesialisasi' => 'required|string',
            'no_lisensi' => 'required|unique:dokters',
            'no_telepon' => 'required|string',
            'email' => 'required|email|unique:dokters',
            'alamat' => 'required|string',
            'jam_praktek_mulai' => 'required|date_format:H:i',
            'jam_praktek_selesai' => 'required|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dokter = Dokter::create($request->all());

        return response()->json([
            'message' => 'Dokter created successfully',
            'data' => $dokter,
        ], 201);
    }

    public function show(Dokter $dokter)
    {
        return response()->json([
            'data' => $dokter->load(['jadwalDokter', 'pendaftaran']),
        ], 200);
    }

    public function update(Request $request, Dokter $dokter)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|required|string|max:255',
            'spesialisasi' => 'sometimes|required|string',
            'no_telepon' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dokter->update($request->all());

        return response()->json([
            'message' => 'Dokter updated successfully',
            'data' => $dokter,
        ], 200);
    }

    public function destroy(Dokter $dokter)
    {
        $dokter->update(['status' => false]);

        return response()->json([
            'message' => 'Dokter deactivated successfully',
        ], 200);
    }

    public function getBySpesialisasi($spesialisasi)
    {
        $dokters = Dokter::where('spesialisasi', $spesialisasi)
            ->where('status', true)
            ->get();

        return response()->json([
            'data' => $dokters,
        ], 200);
    }
}
