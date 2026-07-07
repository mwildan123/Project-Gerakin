<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * AuthController — Menangani autentikasi pengguna Gerak.in.
 *
 * Mendukung flow "Deferred Authentication":
 * - register() → Daftar akun baru, return Sanctum token
 * - login()    → Login akun existing, return Sanctum token
 * - logout()   → Hapus token, keluar dari akun
 * - profile()  → Ambil data profil user yang login
 * - updateProfile() → Update profil fisik (berat, tinggi, jenis kelamin)
 */
class AuthController extends Controller
{
    /**
     * REGISTER — Daftarkan akun pengguna baru.
     *
     * POST /api/auth/register
     * Body (JSON): { name, email, password, password_confirmation, berat_badan?, tinggi_badan?, jenis_kelamin? }
     *
     * Digunakan saat flow "Deferred Authentication":
     * Pengguna selesai olahraga → tekan Simpan → pop-up registrasi muncul → data ini dikirim.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Validasi input registrasi
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'email'          => 'required|email|unique:users,email',   // Email harus unik
            'password'       => ['required', 'confirmed', Password::min(6)], // Min 6 karakter + konfirmasi
            'berat_badan'    => 'nullable|numeric|min:20|max:300',     // BB wajar: 20-300 kg
            'tinggi_badan'   => 'nullable|numeric|min:50|max:300',     // TB wajar: 50-300 cm
            'jenis_kelamin'  => 'nullable|in:L,P',                     // L = Laki-laki, P = Perempuan
        ], [
            // Pesan error dalam bahasa Indonesia
            'name.required'       => 'Nama wajib diisi.',
            'email.required'      => 'Email wajib diisi.',
            'email.unique'        => 'Email ini sudah terdaftar.',
            'password.required'   => 'Password wajib diisi.',
            'password.confirmed'  => 'Konfirmasi password tidak cocok.',
            'password.min'        => 'Password minimal 6 karakter.',
        ]);

        // Buat user baru (password otomatis di-hash karena cast 'hashed' di model)
        $user = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password'      => $validated['password'],
            'berat_badan'   => $validated['berat_badan'] ?? null,
            'tinggi_badan'  => $validated['tinggi_badan'] ?? null,
            'jenis_kelamin' => $validated['jenis_kelamin'] ?? null,
        ]);

        // Buat Sanctum token untuk user baru
        // Token ini dikirim ke Ionic frontend dan disimpan di localStorage
        $token = $user->createToken('gerakin-auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil! Selamat datang di Gerak.in 🎉',
            'data'    => [
                'user'  => $user,
                'token' => $token,   // Bearer token untuk request selanjutnya
            ],
        ], 201);
    }

    /**
     * LOGIN — Masuk ke akun yang sudah terdaftar.
     *
     * POST /api/auth/login
     * Body (JSON): { email, password }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validasi input login
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required'    => 'Email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        // Cari user berdasarkan email
        $user = User::where('email', $validated['email'])->first();

        // Verifikasi: user ada DAN password cocok
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.',
            ], 401); // HTTP 401 = Unauthorized
        }

        // Hapus token lama untuk perangkat ini (keamanan: mencegah token menumpuk)
        $user->tokens()->delete();

        // Buat token baru
        $token = $user->createToken('gerakin-auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil! Selamat kembali 👋',
            'data'    => [
                'user'  => $user,
                'token' => $token,
            ],
        ], 200);
    }

    /**
     * LOGOUT — Keluar dari akun (hapus token Sanctum).
     *
     * POST /api/auth/logout
     * Header: Authorization: Bearer {token}
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Hapus token yang sedang digunakan saat ini
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar dari akun.',
        ], 200);
    }

    /**
     * PROFILE — Ambil data profil user yang sedang login.
     *
     * GET /api/auth/profile
     * Header: Authorization: Bearer {token}
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user(),
        ], 200);
    }

    /**
     * UPDATE PROFILE — Perbarui data profil fisik pengguna.
     *
     * PUT /api/auth/profile
     * Header: Authorization: Bearer {token}
     * Body (JSON): { name?, berat_badan?, tinggi_badan?, jenis_kelamin? }
     *
     * Digunakan di halaman Pengaturan (Tab Profil) untuk update data fisik
     * yang mempengaruhi akurasi perhitungan kalori.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:100',
            'berat_badan'   => 'nullable|numeric|min:20|max:300',
            'tinggi_badan'  => 'nullable|numeric|min:50|max:300',
            'jenis_kelamin' => 'nullable|in:L,P',
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => $user->fresh(),  // Ambil data terbaru dari DB
        ], 200);
    }
}
