<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PasienController extends Controller
{
    public function index()
    {
        $pasiens = Pasien::paginate(15);
        return response()->json($pasiens, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_pendaftaran' => 'required|unique:pasiens',
            'nama' => 'required|string|max:255',
            'no_identitas' => 'required|unique:pasiens',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'tanggal_lahir' => 'required|date',
            'alamat' => 'required|string',
            'no_telepon' => 'required|string',
            'email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pasien = Pasien::create($request->all());

        return response()->json([
            'message' => 'Pasien created successfully',
            'data' => $pasien,
        ], 201);
    }

    public function show(Pasien $pasien)
    {
        return response()->json([
            'data' => $pasien->load(['pendaftaran', 'rekamMedis']),
        ], 200);
    }

    public function update(Request $request, Pasien $pasien)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|required|string|max:255',
            'no_identitas' => 'sometimes|required|unique:pasiens,no_identitas,' . $pasien->id,
            'jenis_kelamin' => 'sometimes|required|in:Laki-laki,Perempuan',
            'tanggal_lahir' => 'sometimes|required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pasien->update($request->all());

        return response()->json([
            'message' => 'Pasien updated successfully',
            'data' => $pasien,
        ], 200);
    }

    public function destroy(Pasien $pasien)
    {
        $pasien->delete();

        return response()->json([
            'message' => 'Pasien deleted successfully',
        ], 200);
    }
}
