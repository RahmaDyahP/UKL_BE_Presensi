<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Presences;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PresenceController extends Controller
{
    /**
     * Fungsi untuk mencatat presensi user.
     */
    public function store(Request $request)
    {
        // Ambil user yang sedang login
        $user = Auth::user();

        // Cek apakah user yang login adalah admin
        if ($user->role === 'admin') {
            // Validasi input untuk admin (memerlukan user_id)
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'status' => 'required|in:hadir,izin,sakit',
            ]);

            $userId = $validated['user_id'];
        } else {
            // Validasi input untuk siswa (tidak memerlukan user_id)
            $validated = $request->validate([
                'status' => 'required|in:hadir,izin,telat',
            ]);

            $userId = $user->id; // Siswa hanya bisa melakukan presensi untuk dirinya sendiri
        }

        // Menyimpan presensi baru
        $presence = Presences::create([
            'user_id' => $userId,
            'date' => now()->toDateString(),  // Menggunakan waktu saat ini
            'time' => now()->toTimeString(),  // Menggunakan waktu saat ini
            'status' => $validated['status'], // Status dari request
        ]);

        // Mengembalikan response JSON
        return response()->json([
            'status' => 'sukses',
            'message' => 'Presensi berhasil dicatat',
            'data' => [
                'id' => $presence->id,
                'user_id' => $presence->user_id,
                'date' => $presence->date,
                'time' => $presence->time,
                'status' => $presence->status,
            ]
        ]);
    }
    public function riwayat(Request $request, $user_id = null)
{
    $user = auth()->user();

    // Admin dapat melihat riwayat presensi siapa saja
    if ($user->role === 'admin') {
        // Jika admin tidak menyertakan user_id, tampilkan semua presensi
        $presences = $user_id 
            ? Presences::where('user_id', $user_id)->get()
            : Presences::all();
    } 
    // Siswa hanya bisa melihat riwayatnya sendiri
    else if ($user->role === 'siswa') {
        // Jika siswa mencoba mengakses presensi siswa lain
        if ($user_id && $user_id != $user->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk melihat riwayat presensi siswa lain.'
            ], 403);
        }

        // Menampilkan riwayat presensi siswa yang sedang login
        $presences = Presences::where('user_id', $user->id)->get();
    } else {
        return response()->json([
            'message' => 'Role tidak dikenali.'
        ], 403);
    }

    // Jika tidak ada presensi yang ditemukan
    if ($presences->isEmpty()) {
        return response()->json([
            'message' => 'Riwayat presensi tidak ditemukan.'
        ], 404);
    }

    return response()->json([
        'message' => 'Riwayat presensi ditemukan.',
        'data' => $presences
    ]);
}
    public function riwayatByUserId($user_id)
    {
        // Mengecek apakah yang mengakses adalah admin atau bukan
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Hanya admin yang bisa melihat riwayat presensi.'
            ], 403); // Status 403 berarti "Forbidden"
        }

        // Jika admin, ambil riwayat presensi berdasarkan user_id
        $presences = Presences::where('user_id', $user_id)->get();

        if ($presences->isEmpty()) {
            return response()->json([
                'message' => 'Riwayat presensi tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'message' => 'Riwayat presensi ditemukan.',
            'data' => $presences
        ]);
    }
    public function summary($user_id)
    {
        $userRecords = Presences::where('user_id', $user_id)->get();
        $userGroupedByMonth = $userRecords->groupBy(function ($date) {
            return Carbon::parse($date->date)->format('m-Y');
        });

        $summary = [];

        foreach ($userGroupedByMonth as $monthYear => $records) {
            $hadir = $records->where('status', 'hadir')->count();
            $izin = $records->where('status', 'izin')->count();
            $sakit = $records->where('status', 'sakit')->count();

            $summary[] = [
                'month' => $monthYear,
                'attendance_summary' => [
                    'hadir' => $hadir,
                    'izin' => $izin,
                    'sakit' => $sakit,
                ],
            ];
        }
        return response()->json([
            'status' => 'success',
            'data' => [
                'id_user' => $user_id,
                'attendance_summary_by_month' => $summary
            ]
        ]);
    }
    public function analysis(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'group_by' => 'required|string',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $users = User::where('role', $validated['group_by'])->get();

        $groupedAnalysis = [];

        foreach ($users as $user) {
            $attendanceRecords = Presences::where('user_id', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $hadir = $attendanceRecords->where('status', 'hadir')->count();
            $izin = $attendanceRecords->where('status', 'izin')->count();
            $telat = $attendanceRecords->where('status', 'sakit')->count();

            $totalAttendance = $hadir + $izin + $telat;
            $hadirPercentage = $totalAttendance > 0 ? ($hadir / $totalAttendance) * 100 : 0;
            $izinPercentage = $totalAttendance > 0 ? ($izin / $totalAttendance) * 100 : 0;
            $telatPercentage = $totalAttendance > 0 ? ($telat / $totalAttendance) * 100 : 0;

            $groupedAnalysis[] = [
                'group' => $user->role,
                'total_users' => $users->count(),
                'attendance_rate' => [
                    'hadir_percentage' => round($hadirPercentage, 2),
                    'izin_percentage' => round($izinPercentage, 2),
                    'telat_percentage' => round($telatPercentage, 2),
                ],
                'total_attendance' => [
                    'hadir' => $hadir,
                    'izin' => $izin,
                    'telat' => $telat,
                ],
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'analysis_period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'grouped_analysis' => $groupedAnalysis,
            ]
        ], 200);
    }
}
