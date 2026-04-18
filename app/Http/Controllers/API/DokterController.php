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
    // ✅ tampilkan semua, termasuk status 0
    $dokters = Dokter::paginate(15);
    return response()->json($dokters, 200);
}

   public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'nama'                 => 'required|string|max:255',
        'no_identitas'         => 'required|unique:dokters',
        'spesialisasi'         => 'required|string',
        'no_lisensi'           => 'required|unique:dokters',
        'no_telepon'           => 'required|string',
        'email'                => 'required|email|unique:dokters',
        'alamat'               => 'required|string',
        'jam_praktek_mulai'    => 'required|date_format:H:i',
        'jam_praktek_selesai'  => 'required|date_format:H:i',
        'hari_libur'           => 'nullable|string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
        'status'               => 'required|boolean', // ✅ wajib diisi
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $dokter = Dokter::create([
        'nama'                => $request->nama,
        'no_identitas'        => $request->no_identitas,
        'spesialisasi'        => $request->spesialisasi,
        'no_lisensi'          => $request->no_lisensi,
        'no_telepon'          => $request->no_telepon,
        'email'               => $request->email,
        'alamat'              => $request->alamat,
        'jam_praktek_mulai'   => $request->jam_praktek_mulai,
        'jam_praktek_selesai' => $request->jam_praktek_selesai,
        'hari_libur'          => $request->hari_libur,
        'status'              => $request->status, // ✅ tidak ada default, ikut input user
    ]);

    return response()->json([
        'message' => 'Dokter created successfully',
        'data'    => $dokter,
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
            'nama'        => 'sometimes|required|string|max:255',
            'spesialisasi'=> 'sometimes|required|string',
            'no_telepon'  => 'sometimes|required|string',
            'hari_libur'  => 'nullable|string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu', // ✅ tambah
            'status'      => 'nullable|boolean', // ✅ tambah
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dokter->update([
            'nama'        => $request->nama ?? $dokter->nama,
            'spesialisasi'=> $request->spesialisasi ?? $dokter->spesialisasi,
            'no_telepon'  => $request->no_telepon ?? $dokter->no_telepon,
            'hari_libur'  => $request->has('hari_libur') ? $request->hari_libur : $dokter->hari_libur, // ✅
            'status'      => $request->has('status') ? $request->status : $dokter->status,             // ✅
        ]);

        return response()->json([
            'message' => 'Dokter updated successfully',
            'data'    => $dokter->fresh(), // ✅ fresh() agar return data terbaru
        ], 200);
    }

   public function destroy(Dokter $dokter)
{
    try {

        $dokter->delete(); // Menghapus data dari database secara permanen

        return response()->json([
            'message' => 'Dokter berhasil dihapus permanen dari database',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Gagal menghapus dokter. Data mungkin masih terikat dengan jadwal atau pendaftaran.',
            'error' => $e->getMessage()
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
