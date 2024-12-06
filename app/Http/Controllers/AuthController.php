<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; // Model User
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // Fungsi untuk Register User
    public function register(Request $request)
{
    // Validasi input dari pengguna
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:6',
        'role' => 'required|in:siswa,admin'
    ]);

    // Jika validasi gagal, kembalikan response error dengan status 400
    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    // Membuat pengguna baru
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password), // Meng-hash password sebelum disimpan
        'role' => $request->role,
    ]);

    // Membuat token JWT untuk pengguna
    $token = JWTAuth::fromUser($user);

    // Mengembalikan response sukses dengan data pengguna dan token
    return response()->json([
        'status' => 'success',
        'message' => 'Pengguna berhasil ditambahkan',
        'user' => $user,
        'token' => $token
    ], 201);
}

    // Fungsi untuk Login User
    public function login(Request $request)
{
    $credentials = $request->only('email', 'password');

    if (!$token = JWTAuth::attempt($credentials)) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Login berhasil',
        'token' => $token
    ], 200);
}

    // Fungsi untuk mendapatkan user yang sedang login
    public function getAuthenticatedUser()
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token tidak valid'], 401);
        }

        return response()->json(compact('user'));
    }
    public function get($id)
    {
        try {
            // Mencari pengguna berdasarkan ID
            $user = User::findOrFail($id);
    
            // Mengembalikan response sukses dengan data pengguna
            return response()->json([
                'status' => 'success',
                'message' => 'Pengguna ditemukan',
                'user' => $user
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Jika pengguna tidak ditemukan, kembalikan response error
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna tidak ditemukan'
            ], 404);
        }
    }
    public function update(Request $request, $id)
{
    // Validasi input dari pengguna
    $validator = Validator::make($request->all(), [
        'name' => 'nullable|string|max:255',
        'email' => 'nullable|string|email|max:255|unique:users,email,' . $id,
        'password' => 'nullable|string|min:6',
        'role' => 'nullable|in:siswa,admin'
    ]);

    // Jika validasi gagal, kembalikan response error dengan status 400
    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    // Mencari pengguna berdasarkan ID
    $user = User::findOrFail($id);

    // Memperbarui data pengguna hanya jika field di request ada
    if ($request->has('name')) {
        $user->name = $request->name;
    }
    if ($request->has('email')) {
        $user->email = $request->email;
    }
    if ($request->has('password')) {
        $user->password = Hash::make($request->password);
    }
    if ($request->has('role')) {
        $user->role = $request->role;
    }

    // Menyimpan perubahan ke database
    $user->save();

    // Mengembalikan response sukses dengan data pengguna yang diperbarui
    return response()->json([
        'status' => 'success',
        'message' => 'Pengguna berhasil diperbarui',
        'user' => $user
    ], 200);
}
public function delete($id)
{
    try {
        // Mencari pengguna berdasarkan ID
        $user = User::findOrFail($id);

        // Menghapus pengguna dari database
        $user->delete();

        // Mengembalikan response sukses setelah data dihapus
        return response()->json([
            'status' => 'success',
            'message' => 'Pengguna berhasil dihapus'
        ], 200);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        // Jika pengguna tidak ditemukan, kembalikan response error
        return response()->json([
            'status' => 'error',
            'message' => 'Pengguna tidak ditemukan'
        ], 404);
    }
}


}