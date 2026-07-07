<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel 'activity_coordinates' menyimpan array titik-titik koordinat GPS
     * yang direkam selama aktivitas outdoor berlangsung.
     * Setiap baris = 1 titik GPS pada waktu tertentu.
     * Tabel ini HANYA diisi untuk olahraga outdoor (is_outdoor = true).
     */
    public function up(): void
    {
        Schema::create('activity_coordinates', function (Blueprint $table) {
            $table->id();

            // Foreign key ke tabel activities
            $table->foreignId('activity_id')
                  ->constrained('activities')                        // Relasi ke tabel activities
                  ->onDelete('cascade');                              // Hapus semua koordinat jika aktivitas dihapus

            $table->double('latitude', 10, 7);                       // Koordinat latitude (presisi 7 desimal ≈ 1.1 cm)
            $table->double('longitude', 10, 7);                      // Koordinat longitude
            $table->float('altitude')->nullable();                   // Ketinggian titik ini (meter dari permukaan laut)
            $table->integer('urutan')->default(0);                   // Urutan titik ke-N (untuk merekonstruksi jalur)
            $table->timestamp('recorded_at')->nullable();            // Waktu perekaman titik GPS ini

            $table->timestamps();

            // Index untuk query jalur per aktivitas secara berurutan
            $table->index(['activity_id', 'urutan']);
        });
    }

    /**
     * Rollback migration: hapus tabel 'activity_coordinates'.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_coordinates');
    }
};
