<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PasienController extends Controller
{
    /**
     * Menampilkan daftar pasien dengan paginasi dan pencarian.
     */
    public function index(Request $request)
    {
        $query = Pasien::query()->orderByDesc('updated_at');

        // Pencarian Global
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('nama', 'like', $like)
                  ->orWhere('no_identitas', 'like', $like)
                  ->orWhere('no_pendaftaran', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('no_telepon', 'like', $like);
            });
        }

        // Filter Jenis Kelamin
        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->input('jenis_kelamin'));
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));
        return response()->json($query->paginate($perPage), 200);
    }

    /**
     * Menyimpan data pasien baru (Manual oleh Admin).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama'            => 'required|string|max:255',
            'no_identitas'    => 'required|string|max:255|unique:pasiens,no_identitas',
            'jenis_kelamin'   => 'required|in:Laki-laki,Perempuan',
            'tanggal_lahir'   => 'required|date|before_or_equal:today',
            'no_telepon'      => 'required|string|max:50',
            'alamat'          => 'nullable|string|max:2000',
            'email'           => 'nullable|email|max:255|unique:pasiens,email',
            'no_pendaftaran'  => 'nullable|string|unique:pasiens,no_pendaftaran',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // LOGIC: Jika no_pendaftaran kosong, buat otomatis (Contoh: PAS-20240420001)
        if (empty($data['no_pendaftaran'])) {
            $count = Pasien::whereDate('created_at', now())->count() + 1;
            $data['no_pendaftaran'] = 'PAS-' . now()->format('Ymd') . str_pad($count, 3, '0', STR_PAD_LEFT);
        }

        // Default value untuk alamat jika kosong agar tidak error di beberapa versi DB
        $data['alamat'] = $data['alamat'] ?? '-';

        $pasien = Pasien::create($data);

        return response()->json([
            'message' => 'Data pasien berhasil ditambahkan',
            'data' => $pasien,
        ], 201);
    }

    /**
     * Detail pasien beserta riwayat medisnya.
     */
    public function show(Pasien $pasien)
    {
        return response()->json([
            'data' => $pasien->load(['pendaftaran', 'rekamMedis']),
        ], 200);
    }

    /**
     * Update data pasien oleh Admin.
     */
    public function update(Request $request, Pasien $pasien)
    {
        $validator = Validator::make($request->all(), [
            'nama'            => 'sometimes|required|string|max:255',
            'no_identitas'    => 'sometimes|required|string|unique:pasiens,no_identitas,' . $pasien->id,
            'jenis_kelamin'   => 'sometimes|required|in:Laki-laki,Perempuan',
            'tanggal_lahir'   => 'sometimes|required|date|before_or_equal:today',
            'no_telepon'      => 'sometimes|required|string|max:50',
            'alamat'          => 'sometimes|required|string|max:2000',
            'email'           => 'nullable|email|unique:pasiens,email,' . $pasien->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pasien->update($validator->validated());

        return response()->json([
            'message' => 'Data pasien berhasil diperbarui',
            'data' => $pasien,
        ], 200);
    }

    /**
     * Menghapus data pasien.
     */
    public function destroy(Pasien $pasien)
    {
        // Gunakan Transaction jika ada relasi yang harus ikut terhapus
        DB::transaction(function () use ($pasien) {
            $pasien->delete();
        });

        return response()->json(['message' => 'Data pasien telah dihapus'], 200);
    }

    /**
     * API untuk Pasien melihat Profil sendiri (berdasarkan Email Login).
     */
    public function profile(Request $request)
    {
        $pasien = Pasien::where('email', $request->user()->email)->first();

        if (!$pasien) {
            return response()->json(['message' => 'Profil pasien tidak ditemukan'], 404);
        }

        return response()->json([
            'data' => array_merge($pasien->toArray(), [
                'photo_url' => $this->photoUrlForUser($request->user()->id),
            ]),
        ], 200);
    }

    /**
     * Update Profil oleh Pasien sendiri.
     */
    public function updateProfile(Request $request)
    {
        $pasien = Pasien::where('email', $request->user()->email)->first();

        if (!$pasien) {
            return response()->json(['message' => 'Profil tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama'             => 'sometimes|required|string|max:255',
            'alamat'           => 'sometimes|required|string',
            'no_telepon'       => 'sometimes|required|string',
            'status_pernikahan'=> 'nullable|string',
            'pekerjaan'        => 'nullable|string',
            'berat_badan'      => 'nullable|numeric',
            'tinggi_badan'     => 'nullable|numeric',
            'alergi'           => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pasien->update($validator->validated());

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'data' => $pasien->fresh(),
        ], 200);
    }

    // --- HELPER METHODS UNTUK FOTO ---

    public function updatePhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $path = $this->storePhotoForUser($request, $request->user()->id);

        return response()->json([
            'message' => 'Foto berhasil diperbarui',
            'photo_url' => asset('assets/profile/' . basename($path)),
        ], 200);
    }

    private function photoUrlForUser(int $userId): ?string
    {
        $dir = public_path('assets/profile');
        $matches = File::glob($dir . DIRECTORY_SEPARATOR . 'user-' . $userId . '.*');
        return $matches ? asset('assets/profile/' . basename($matches[0])) : null;
    }

    private function storePhotoForUser(Request $request, int $userId): string
    {
        $dir = public_path('assets/profile');
        if (!File::isDirectory($dir)) File::makeDirectory($dir, 0755, true);

        foreach (File::glob($dir . "/user-$userId.*") as $old) File::delete($old);

        $file = $request->file('photo');
        $filename = "user-$userId." . $file->getClientOriginalExtension();
        $file->move($dir, $filename);

        return $dir . DIRECTORY_SEPARATOR . $filename;
    }
}