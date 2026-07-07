<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel 'users'.
     * Tabel ini menyimpan data profil pengguna termasuk data fisik
     * yang diperlukan untuk kalkulasi kalori berbasis MET.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // Kolom standar autentikasi Laravel
            $table->id();
            $table->string('name');                                  // Nama lengkap pengguna
            $table->string('email')->unique();                       // Email unik untuk login
            $table->timestamp('email_verified_at')->nullable();      // Waktu verifikasi email
            $table->string('password');                               // Password ter-hash (bcrypt)
            $table->rememberToken();                                 // Token "Remember Me"

            // === Kolom tambahan untuk Gerak.in ===
            // Nullable karena pengguna bisa mendaftar tanpa langsung mengisi data fisik
            $table->float('berat_badan')->nullable();                // Berat badan dalam Kg (untuk rumus kalori MET)
            $table->float('tinggi_badan')->nullable();               // Tinggi badan dalam Cm
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();   // L = Laki-laki, P = Perempuan

            $table->timestamps();                                    // created_at & updated_at otomatis
        });
    }

    /**
     * Rollback migration: hapus tabel 'users'.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
