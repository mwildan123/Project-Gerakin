<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Activity — Representasi satu sesi aktivitas olahraga.
 *
 * Setiap record = 1 sesi latihan lengkap (dari mulai hingga selesai).
 * Menggunakan Single Table Design: semua 8 jenis olahraga masuk ke tabel ini.
 * Kolom yang tidak relevan untuk suatu olahraga bernilai NULL.
 */
class Activity extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang digunakan model ini.
     */
    protected $table = 'activities';

    /**
     * Kolom yang boleh diisi secara massal.
     * Semua metrik olahraga didaftarkan di sini.
     */
    protected $fillable = [
        'user_id',              // ID pengguna yang melakukan olahraga
        'jenis_olahraga',       // Enum: berjalan, berlari_outdoor, dll.
        'durasi_detik',         // Durasi total dalam detik
        'jarak_km',             // Jarak tempuh (Km)
        'kalori',               // Kalori terbakar (hasil kalkulasi MET)
        'pace',                 // Pace rata-rata (min/km) — untuk olahraga lari
        'kecepatan_kmh',        // Kecepatan saat ini (km/jam) — untuk sepeda
        'kecepatan_rata2_kmh',  // Kecepatan rata-rata (km/jam)
        'kecepatan_maks_kmh',   // Kecepatan maksimum (km/jam)
        'langkah',              // Total langkah (steps) — Berjalan
        'heart_rate_bpm',       // Rata-rata detak jantung (BPM)
        'kadens_spm',           // Kadens langkah per menit (SPM)
        'kadens_rpm',           // Kadens putaran per menit (RPM) — sepeda
        'elevasi_naik_m',       // Total kenaikan elevasi (meter)
        'altitud_m',            // Ketinggian/Altitud (meter)
        'is_outdoor',           // Boolean: true = outdoor, false = indoor
        'catatan',              // Catatan opsional dari pengguna
    ];

    /**
     * Casting otomatis: Laravel akan mengkonversi tipe data saat baca/tulis.
     */
    protected $casts = [
        'durasi_detik'         => 'integer',
        'jarak_km'             => 'float',
        'kalori'               => 'float',
        'kecepatan_kmh'        => 'float',
        'kecepatan_rata2_kmh'  => 'float',
        'kecepatan_maks_kmh'   => 'float',
        'langkah'              => 'integer',
        'heart_rate_bpm'       => 'integer',
        'kadens_spm'           => 'integer',
        'kadens_rpm'           => 'integer',
        'elevasi_naik_m'       => 'float',
        'altitud_m'            => 'float',
        'is_outdoor'           => 'boolean',
    ];

    /**
     * Relasi: Activity milik satu User.
     * Contoh: $activity->user mengembalikan data pemilik aktivitas.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi: Satu Activity memiliki banyak ActivityCoordinate.
     * Koordinat diurutkan berdasarkan kolom 'urutan' secara ascending.
     * Contoh: $activity->coordinates mengembalikan semua titik GPS.
     */
    public function coordinates()
    {
        return $this->hasMany(ActivityCoordinate::class)->orderBy('urutan', 'asc');
    }
}
