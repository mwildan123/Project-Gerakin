<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel 'activities' menyimpan semua data ringkasan aktivitas olahraga.
     * Menggunakan pendekatan Single Table: semua 8 jenis olahraga masuk ke 1 tabel.
     * Kolom yang tidak relevan untuk suatu olahraga akan bernilai NULL.
     *
     * 8 Jenis Olahraga yang didukung:
     * - berjalan, berlari_outdoor, lari_treadmill, lari_trail,
     * - mendaki, bersepeda_road, bersepeda_mtb, bersepeda_statis
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            // Foreign key ke tabel users — siapa yang melakukan olahraga
            $table->foreignId('user_id')
                  ->constrained('users')                             // Relasi ke tabel users
                  ->onDelete('cascade');                              // Hapus aktivitas jika user dihapus

            // Jenis olahraga menggunakan ENUM agar konsisten dan tervalidasi di level DB
            $table->enum('jenis_olahraga', [
                'berjalan',
                'berlari_outdoor',
                'lari_treadmill',
                'lari_trail',
                'mendaki',
                'bersepeda_road',
                'bersepeda_mtb',
                'bersepeda_statis',
            ]);

            // === Metrik Universal (hampir semua olahraga punya ini) ===
            $table->integer('durasi_detik')->default(0);              // Durasi total dalam detik
            $table->float('jarak_km')->nullable();                    // Jarak tempuh dalam Kilometer
            $table->float('kalori')->nullable();                      // Kalori terbakar (dihitung via MET)

            // === Metrik Pace & Kecepatan ===
            $table->string('pace')->nullable();                       // Pace rata-rata (format: "5:30" = 5 menit 30 detik per km)
            $table->float('kecepatan_kmh')->nullable();               // Kecepatan saat ini (km/jam) — untuk sepeda
            $table->float('kecepatan_rata2_kmh')->nullable();         // Kecepatan rata-rata (km/jam)
            $table->float('kecepatan_maks_kmh')->nullable();          // Kecepatan maksimum (km/jam) — MTB

            // === Metrik Langkah & Kadens ===
            $table->integer('langkah')->nullable();                   // Total langkah (steps) — Berjalan only
            $table->integer('heart_rate_bpm')->nullable();            // Rata-rata detak jantung (BPM)
            $table->integer('kadens_spm')->nullable();                // Kadens langkah per menit (SPM) — lari & jalan
            $table->integer('kadens_rpm')->nullable();                // Kadens putaran per menit (RPM) — sepeda

            // === Metrik Elevasi ===
            $table->float('elevasi_naik_m')->nullable();              // Total kenaikan elevasi (meter) — trail, hiking, MTB
            $table->float('altitud_m')->nullable();                   // Ketinggian/Altitud maksimum (meter) — hiking

            // === Metadata ===
            $table->boolean('is_outdoor')->default(true);             // True = outdoor (ada GPS), False = indoor
            $table->text('catatan')->nullable();                      // Catatan opsional dari pengguna

            $table->timestamps();                                     // created_at & updated_at

            // Index untuk query performa: filter per user dan per jenis
            $table->index(['user_id', 'jenis_olahraga']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Rollback migration: hapus tabel 'activities'.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
