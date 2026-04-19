<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'no_identitas' => 'required|string|unique:pasiens,no_identitas',
            'tanggal_lahir' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 1. Create User
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'pasien',
        ]);

        // 2. Create Pasien
        $pasien = Pasien::create([
            'user_id'       => $user->id,
            'nama'          => $request->name,
            'email'         => $request->email,
            'no_identitas'  => $request->no_identitas,
            'tanggal_lahir' => Carbon::parse($request->tanggal_lahir)->format('Y-m-d'),
            'no_pendaftaran'=> 'TEMP-' . time(),
        ]);

        // 3. Update No Pendaftaran Final
        $pasien->update([
            'no_pendaftaran' => 'PSN-' . str_pad($pasien->id, 5, '0', STR_PAD_LEFT)
        ]);

        return response()->json([
            'message' => 'Registrasi Berhasil',
            'user' => $user->load('pasien'), // Memuat relasi pasien
            'token' => $user->createToken('auth_token')->plainTextToken
        ], 201);
    }

    public function login(Request $request)
<<<<<<< HEAD
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'errors' => $validator->errors()
        ], 422);
    }

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'Email atau password salah',
        ], 401);
    }

    // hapus token lama
    $user->tokens()->delete();

    // buat token baru
    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'message' => 'Login berhasil',
        'user' => $user,
        'token' => $token,
    ]);
}

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ], 200);
    }


    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => array_merge($user->toArray(), [
                'photo_url' => $this->photoUrlForUser($user->id),
            ]),
        ], 200);
    }


    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $request->user()->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $user->update($request->only(['name', 'email']));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => array_merge($user->fresh()->toArray(), [
                'photo_url' => $this->photoUrlForUser($user->id),
            ]),
        ], 200);
    }

    public function updatePhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $path = $this->storePhotoForUser($request, $user->id);

        return response()->json([
            'message' => 'Foto profile updated successfully',
            'photo_url' => asset(str_replace(public_path() . DIRECTORY_SEPARATOR, '', $path)),
            'user' => array_merge($user->toArray(), [
                'photo_url' => $this->photoUrlForUser($user->id),
            ]),
        ], 200);
    }


    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => array_merge($user->toArray(), [
                'photo_url' => $this->photoUrlForUser($user->id),
            ]),
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password does not match'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'message' => 'Password changed successfully'
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
=======
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Kredensial tidak valid'], 401);
        }

        return response()->json([
            'message' => 'Login Berhasil',
            'user' => $user->load('pasien'),
            'token' => $user->createToken('auth_token')->plainTextToken
        ], 200);
    }
}
>>>>>>> 1b323f4 (fix: relasi table pasein)
