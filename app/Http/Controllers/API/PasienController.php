<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class PasienController extends Controller
{
    /**
     * Master pasien: paginasi + pencarian + filter jenis kelamin (selaras admin Blade / frontend).
     *
     * Query: search, jenis_kelamin (Laki-laki|Perempuan), per_page (1–100), page
     */
    public function index(Request $request)
    {
        $query = Pasien::query()->orderByDesc('updated_at')->orderBy('nama');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('nama', 'like', $like)
                    ->orWhere('no_identitas', 'like', $like)
                    ->orWhere('no_pendaftaran', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('no_telepon', 'like', $like);
            });
        }

        if ($request->filled('jenis_kelamin') && in_array($request->input('jenis_kelamin'), ['Laki-laki', 'Perempuan'], true)) {
            $query->where('jenis_kelamin', $request->input('jenis_kelamin'));
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));

        return response()->json($query->paginate($perPage), 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_pendaftaran' => 'required|string|max:100|unique:pasiens,no_pendaftaran',
            'nama' => 'required|string|max:255',
            'no_identitas' => 'required|string|max:255|unique:pasiens,no_identitas',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'tanggal_lahir' => 'required|date|before_or_equal:today',
            'alamat' => 'required|string|max:2000',
            'no_telepon' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pasien = Pasien::create($validator->validated());

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
            'no_pendaftaran' => 'sometimes|required|string|max:100|unique:pasiens,no_pendaftaran,'.$pasien->id,
            'nama' => 'sometimes|required|string|max:255',
            'no_identitas' => 'sometimes|required|string|max:255|unique:pasiens,no_identitas,'.$pasien->id,
            'jenis_kelamin' => 'sometimes|required|in:Laki-laki,Perempuan',
            'tanggal_lahir' => 'sometimes|required|date|before_or_equal:today',
            'alamat' => 'sometimes|required|string|max:2000',
            'no_telepon' => 'sometimes|required|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pasien->update($request->only([
            'no_pendaftaran',
            'nama',
            'no_identitas',
            'jenis_kelamin',
            'tanggal_lahir',
            'alamat',
            'no_telepon',
            'email',
        ]));

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

    public function profile(Request $request)
    {
        $pasien = Pasien::where('email', $request->user()->email)->first();

        if (!$pasien) {
            return response()->json([
                'message' => 'Data pasien belum terhubung dengan akun ini',
            ], 404);
        }

        return response()->json([
            'data' => array_merge($pasien->toArray(), [
                'photo_url' => $this->photoUrlForUser($request->user()->id),
            ]),
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        $pasien = Pasien::where('email', $request->user()->email)->first();

        if (!$pasien) {
            return response()->json([
                'message' => 'Data pasien belum terhubung dengan akun ini',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|required|string|max:255',
            'no_identitas' => 'sometimes|required|unique:pasiens,no_identitas,' . $pasien->id,
            'jenis_kelamin' => 'sometimes|required|in:Laki-laki,Perempuan',
            'tanggal_lahir' => 'sometimes|required|date',
            'alamat' => 'sometimes|required|string',
            'no_telepon' => 'sometimes|required|string',
            'email' => 'sometimes|nullable|email',
            'status_pernikahan' => 'sometimes|nullable|string|max:255',
            'pekerjaan' => 'sometimes|nullable|string|max:255',
            'agama' => 'sometimes|nullable|string|max:255',
            'berat_badan' => 'sometimes|nullable|numeric',
            'tinggi_badan' => 'sometimes|nullable|numeric',
            'golongan_darah' => 'sometimes|nullable|string|max:8',
            'alergi' => 'sometimes|nullable|string',
            'riwayat_penyakit' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pasien->update($request->only([
            'nama',
            'no_identitas',
            'jenis_kelamin',
            'tanggal_lahir',
            'alamat',
            'no_telepon',
            'email',
            'status_pernikahan',
            'pekerjaan',
            'agama',
            'berat_badan',
            'tinggi_badan',
            'golongan_darah',
            'alergi',
            'riwayat_penyakit',
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => array_merge($pasien->fresh()->toArray(), [
                'photo_url' => $this->photoUrlForUser($request->user()->id),
            ]),
        ], 200);
    }

    public function updatePhoto(Request $request)
    {
        $pasien = Pasien::where('email', $request->user()->email)->first();

        if (! $pasien) {
            return response()->json([
                'message' => 'Data pasien belum terhubung dengan akun ini',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $path = $this->storePhotoForUser($request, $request->user()->id);

        return response()->json([
            'message' => 'Foto profile updated successfully',
            'photo_url' => asset(str_replace(public_path() . DIRECTORY_SEPARATOR, '', $path)),
            'data' => array_merge($pasien->toArray(), [
                'photo_url' => $this->photoUrlForUser($request->user()->id),
            ]),
        ], 200);
    }

    private function photoUrlForUser(int $userId): ?string
    {
        $dir = public_path('assets/profile');
        if (! File::isDirectory($dir)) {
            return null;
        }

        $matches = File::glob($dir . DIRECTORY_SEPARATOR . 'user-' . $userId . '.*');
        if (! $matches) {
            return null;
        }

        return asset('assets/profile/' . basename($matches[0]));
    }

    private function storePhotoForUser(Request $request, int $userId): string
    {
        $dir = public_path('assets/profile');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        foreach (File::glob($dir . DIRECTORY_SEPARATOR . 'user-' . $userId . '.*') ?: [] as $oldFile) {
            File::delete($oldFile);
        }

        $extension = $request->file('photo')->getClientOriginalExtension();
        $filename = 'user-' . $userId . '.' . strtolower($extension);

        $request->file('photo')->move($dir, $filename);

        return $dir . DIRECTORY_SEPARATOR . $filename;
    }
}
