<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Gerak.in Backend
|--------------------------------------------------------------------------
|
| Semua route di sini otomatis mendapat prefix '/api'.
| Contoh: Route::post('/auth/register', ...) → URL: /api/auth/register
|
| Grup middleware:
| - 'public'     → Bisa diakses tanpa login (register & login)
| - 'auth:sanctum' → Wajib login dengan Bearer token
|
*/

// =====================================================
// ROUTE PUBLIK (Tidak perlu login)
// =====================================================
Route::prefix('auth')->group(function () {
    // POST /api/auth/register — Daftar akun baru
    Route::post('/register', [AuthController::class, 'register']);

    // POST /api/auth/login — Login ke akun existing
    Route::post('/login', [AuthController::class, 'login']);
});

// =====================================================
// ROUTE TERPROTEKSI (Wajib login dengan Sanctum token)
// =====================================================
Route::middleware('auth:sanctum')->group(function () {

    // --- Auth & Profil ---
    // POST /api/auth/logout — Keluar dari akun
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // GET /api/auth/profile — Ambil data profil user
    Route::get('/auth/profile', [AuthController::class, 'profile']);

    // PUT /api/auth/profile — Update profil fisik (berat, tinggi, kelamin)
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

    // --- Aktivitas Olahraga ---
    // GET  /api/activities     — Daftar semua aktivitas user (paginated)
    // POST /api/activities     — Simpan aktivitas baru
    // GET  /api/activities/{id} — Detail satu aktivitas + koordinat GPS
    Route::get('/activities', [ActivityController::class, 'index']);
    Route::post('/activities', [ActivityController::class, 'store']);
    Route::get('/activities/{id}', [ActivityController::class, 'show']);
});
