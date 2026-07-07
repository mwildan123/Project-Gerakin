<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model ActivityCoordinate — Satu titik GPS dalam jalur aktivitas.
 *
 * Setiap record = 1 titik koordinat GPS yang direkam saat olahraga outdoor.
 * Kumpulan titik ini membentuk polyline/rute yang bisa ditampilkan di peta.
 * HANYA diisi untuk aktivitas outdoor (is_outdoor = true).
 */
class ActivityCoordinate extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang digunakan model ini.
     */
    protected $table = 'activity_coordinates';

    /**
     * Kolom yang boleh diisi secara massal.
     */
    protected $fillable = [
        'activity_id',     // FK ke tabel activities
        'latitude',        // Koordinat latitude (contoh: -6.2088)
        'longitude',       // Koordinat longitude (contoh: 106.8456)
        'altitude',        // Ketinggian titik ini dalam meter (dari GPS)
        'urutan',          // Nomor urut titik (0, 1, 2, ...) untuk merekonstruksi jalur
        'recorded_at',     // Waktu perekaman titik GPS ini
    ];

    /**
     * Casting otomatis tipe data.
     */
    protected $casts = [
        'latitude'    => 'double',
        'longitude'   => 'double',
        'altitude'    => 'float',
        'urutan'      => 'integer',
        'recorded_at' => 'datetime',
    ];

    /**
     * Relasi: Coordinate milik satu Activity.
     */
    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
