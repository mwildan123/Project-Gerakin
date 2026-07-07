<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Model User — Representasi pengguna Gerak.in
 *
 * Menyimpan data autentikasi dan profil fisik pengguna.
 * Data fisik (berat_badan, tinggi_badan) digunakan untuk kalkulasi kalori MET.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Kolom yang boleh diisi secara massal (mass assignment protection).
     * Hanya kolom di sini yang bisa diisi via create() atau fill().
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'berat_badan',      // Berat badan (Kg) — untuk rumus kalori MET
        'tinggi_badan',     // Tinggi badan (Cm)
        'jenis_kelamin',    // 'L' = Laki-laki, 'P' = Perempuan
    ];

    /**
     * Kolom yang disembunyikan saat serialisasi ke JSON.
     * Password dan token tidak boleh terekspos ke API response.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casting tipe data otomatis untuk kolom tertentu.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',     // Otomatis hash saat di-set
        'berat_badan'       => 'float',
        'tinggi_badan'      => 'float',
    ];

    /**
     * Relasi: Satu User memiliki banyak Activity.
     * Contoh: $user->activities akan mengembalikan semua aktivitas olahraga user.
     */
    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}
