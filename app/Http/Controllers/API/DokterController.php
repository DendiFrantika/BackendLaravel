<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Dokter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DokterController extends Controller
{
    /**
     * Master dokter: paginasi + pencarian + filter status (selaras admin Blade / frontend).
     *
     * Query: search, status (1|0|true|false), per_page (1–100), page
     */
    public function index(Request $request)
    {
        $query = Dokter::query()->orderBy('nama');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('nama', 'like', $like)
                    ->orWhere('spesialisasi', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('no_telepon', 'like', $like)
                    ->orWhere('no_identitas', 'like', $like)
                    ->orWhere('no_lisensi', 'like', $like);
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $raw = $request->input('status');
            $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool !== null) {
                $query->where('status', $bool);
            } elseif (in_array((string) $raw, ['0', '1'], true)) {
                $query->where('status', (bool) (int) $raw);
            }
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));

        return response()->json($query->paginate($perPage), 200);
    }

    /**
     * Daftar dokter aktif untuk pemilihan di portal pasien (tanpa data sensitif admin).
     */
    public function indexForPasien()
    {
        $dokters = Dokter::query()
            ->where('status', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'spesialisasi', 'jam_praktek_mulai', 'jam_praktek_selesai']);

        // Fallback untuk data lama: jika semua status nonaktif, tetap tampilkan daftar dokter.
        if ($dokters->isEmpty()) {
            $dokters = Dokter::query()
                ->orderBy('nama')
                ->get(['id', 'nama', 'spesialisasi', 'jam_praktek_mulai', 'jam_praktek_selesai']);
        }

        return response()->json(['data' => $dokters], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'no_identitas' => 'required|string|max:255|unique:dokters,no_identitas',
            'spesialisasi' => 'required|string|max:255',
            'no_lisensi' => 'required|string|max:255|unique:dokters,no_lisensi',
            'no_telepon' => 'required|string|max:50',
            'email' => 'required|email|max:255|unique:dokters,email',
            'alamat' => 'required|string|max:2000',
            'jam_praktek_mulai' => 'required|date_format:H:i',
            'jam_praktek_selesai' => 'required|date_format:H:i|after:jam_praktek_mulai',
            'hari_libur' => 'nullable|string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dokter = Dokter::create($validator->validated());

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
            'no_identitas' => 'sometimes|required|string|max:255|unique:dokters,no_identitas,'.$dokter->id,
            'spesialisasi' => 'sometimes|required|string|max:255',
            'no_lisensi' => 'sometimes|required|string|max:255|unique:dokters,no_lisensi,'.$dokter->id,
            'no_telepon' => 'sometimes|required|string|max:50',
            'email' => 'sometimes|required|email|max:255|unique:dokters,email,'.$dokter->id,
            'alamat' => 'sometimes|required|string|max:2000',
            'jam_praktek_mulai' => 'sometimes|required|date_format:H:i',
            'jam_praktek_selesai' => 'sometimes|required|date_format:H:i',
            'hari_libur' => 'nullable|string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'status' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('jam_praktek_mulai') || $request->has('jam_praktek_selesai')) {
            $mulai = $request->input('jam_praktek_mulai', $dokter->jam_praktek_mulai);
            $selesai = $request->input('jam_praktek_selesai', $dokter->jam_praktek_selesai);
            if (strtotime($selesai) <= strtotime($mulai)) {
                return response()->json([
                    'errors' => ['jam_praktek_selesai' => ['Jam selesai harus setelah jam mulai.']],
                ], 422);
            }
        }

        $dokter->update($request->only([
            'nama',
            'no_identitas',
            'spesialisasi',
            'no_lisensi',
            'no_telepon',
            'email',
            'alamat',
            'jam_praktek_mulai',
            'jam_praktek_selesai',
            'hari_libur',
            'status',
        ]));

        return response()->json([
            'message' => 'Dokter updated successfully',
            'data' => $dokter->fresh(),
        ], 200);
    }

    public function destroy(Dokter $dokter)
    {
        try {
            $dokter->delete();

            return response()->json([
                'message' => 'Dokter berhasil dihapus permanen dari database',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus dokter. Data mungkin masih terikat dengan jadwal atau pendaftaran.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getBySpesialisasi($spesialisasi)
    {
        $dokters = Dokter::where('spesialisasi', $spesialisasi)
            ->where('status', 1) // ✅ pakai 1 bukan true
            ->get();

        return response()->json([
            'data' => $dokters,
        ], 200);
    }
}
