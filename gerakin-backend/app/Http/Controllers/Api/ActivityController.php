<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityCoordinate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * ActivityController — Menangani semua operasi CRUD aktivitas olahraga.
 *
 * Fitur utama:
 * 1. store()       → Simpan data olahraga baru (validasi per jenis, GPS coordinates, kalkulasi kalori)
 * 2. index()       → Ambil daftar aktivitas milik user yang login
 * 3. show()        → Detail satu aktivitas beserta koordinat GPS
 * 4. hitungKalori() → Kalkulasi otomatis kalori terbakar berbasis nilai MET
 */
class ActivityController extends Controller
{
    /**
     * Daftar nilai MET (Metabolic Equivalent of Task) per jenis olahraga.
     * MET = rasio metabolisme saat aktivitas vs istirahat.
     * Sumber: Compendium of Physical Activities (Ainsworth et al.)
     *
     * Rumus Kalori: Kalori = MET × Berat_Badan(kg) × Durasi(jam)
     */
    private const MET_VALUES = [
        'berjalan'         => 3.5,   // Jalan kaki normal (~5 km/jam)
        'berlari_outdoor'  => 9.8,   // Lari outdoor (~8-10 km/jam)
        'lari_treadmill'   => 8.0,   // Lari treadmill (sedikit lebih rendah karena tanpa angin)
        'lari_trail'       => 10.0,  // Lari trail (medan tidak rata, lebih berat)
        'mendaki'          => 6.0,   // Hiking (tergantung kemiringan)
        'bersepeda_road'   => 7.5,   // Sepeda jalan raya (~20 km/jam)
        'bersepeda_mtb'    => 8.5,   // Mountain bike (medan berat)
        'bersepeda_statis' => 6.8,   // Sepeda statis (intensitas sedang)
    ];

    /**
     * Daftar olahraga yang bersifat outdoor (memerlukan GPS).
     * Olahraga indoor (treadmill & sepeda statis) TIDAK menyimpan koordinat.
     */
    private const OUTDOOR_SPORTS = [
        'berjalan',
        'berlari_outdoor',
        'lari_trail',
        'mendaki',
        'bersepeda_road',
        'bersepeda_mtb',
    ];

    /**
     * INDEX — Ambil daftar semua aktivitas milik user yang sedang login.
     *
     * GET /api/activities
     * Header: Authorization: Bearer {token}
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Ambil user dari Sanctum token
        $user = $request->user();

        // Query aktivitas user, urutkan dari terbaru, dengan pagination
        $activities = Activity::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')     // Terbaru di atas
            ->paginate(20);                      // 20 item per halaman

        return response()->json([
            'success' => true,
            'data'    => $activities,
        ], 200);
    }

    /**
     * SHOW — Ambil detail satu aktivitas beserta koordinat GPS-nya.
     *
     * GET /api/activities/{id}
     * Header: Authorization: Bearer {token}
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        // Cari aktivitas milik user ini (keamanan: user hanya bisa lihat miliknya)
        $activity = Activity::with('coordinates')       // Eager load koordinat GPS
            ->where('user_id', $user->id)               // Pastikan milik user yang login
            ->findOrFail($id);                           // 404 jika tidak ditemukan

        return response()->json([
            'success' => true,
            'data'    => $activity,
        ], 200);
    }

    /**
     * STORE — Simpan data aktivitas olahraga baru.
     *
     * POST /api/activities
     * Header: Authorization: Bearer {token}
     * Body (JSON): { jenis_olahraga, durasi_detik, jarak_km, ..., koordinat: [...] }
     *
     * Alur:
     * 1. Validasi input berdasarkan jenis olahraga
     * 2. Hitung kalori otomatis menggunakan MET
     * 3. Simpan aktivitas + koordinat GPS dalam satu transaksi database
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // === STEP 1: Validasi input ===
        // Aturan validasi dasar yang berlaku untuk semua jenis olahraga
        $baseRules = [
            'jenis_olahraga' => ['required', Rule::in(array_keys(self::MET_VALUES))],
            'durasi_detik'   => 'required|integer|min:1',             // Minimal 1 detik
            'jarak_km'       => 'nullable|numeric|min:0|max:999',     // Jarak opsional, max 999 km
            'catatan'        => 'nullable|string|max:500',            // Catatan opsional max 500 karakter
        ];

        // Aturan validasi tambahan per metrik opsional
        $metricRules = [
            'pace'                => 'nullable|string|max:10',         // Format "5:30"
            'kecepatan_kmh'       => 'nullable|numeric|min:0|max:200',
            'kecepatan_rata2_kmh' => 'nullable|numeric|min:0|max:200',
            'kecepatan_maks_kmh'  => 'nullable|numeric|min:0|max:200',
            'langkah'             => 'nullable|integer|min:0',
            'heart_rate_bpm'      => 'nullable|integer|min:30|max:250', // BPM wajar: 30-250
            'kadens_spm'          => 'nullable|integer|min:0|max:300',
            'kadens_rpm'          => 'nullable|integer|min:0|max:200',
            'elevasi_naik_m'      => 'nullable|numeric|min:0|max:9999',
            'altitud_m'           => 'nullable|numeric|min:-500|max:9999', // Bisa negatif (bawah laut)
        ];

        // Aturan validasi untuk array koordinat GPS (hanya outdoor)
        $coordinateRules = [
            'koordinat'              => 'nullable|array|max:10000',     // Max 10.000 titik GPS
            'koordinat.*.latitude'   => 'required_with:koordinat|numeric|between:-90,90',
            'koordinat.*.longitude'  => 'required_with:koordinat|numeric|between:-180,180',
            'koordinat.*.altitude'   => 'nullable|numeric',
            'koordinat.*.recorded_at'=> 'nullable|date',
        ];

        // Gabungkan semua aturan validasi
        $validated = $request->validate(
            array_merge($baseRules, $metricRules, $coordinateRules),
            [
                // Pesan error dalam bahasa Indonesia
                'jenis_olahraga.required' => 'Jenis olahraga wajib dipilih.',
                'jenis_olahraga.in'       => 'Jenis olahraga tidak valid.',
                'durasi_detik.required'   => 'Durasi olahraga wajib diisi.',
                'durasi_detik.min'        => 'Durasi minimal 1 detik.',
            ]
        );

        // === STEP 2: Tentukan apakah olahraga ini outdoor ===
        $isOutdoor = in_array($validated['jenis_olahraga'], self::OUTDOOR_SPORTS);

        // === STEP 3: Hitung kalori otomatis menggunakan rumus MET ===
        $user = $request->user();
        $kaloriTerhitung = $this->hitungKalori(
            $validated['jenis_olahraga'],
            $validated['durasi_detik'],
            $user->berat_badan             // Ambil berat badan dari profil user
        );

        // === STEP 4: Simpan ke database menggunakan Transaction ===
        // Transaction menjamin: aktivitas + koordinat tersimpan BERSAMA.
        // Jika salah satu gagal, keduanya di-rollback (tidak ada data setengah jadi).
        DB::beginTransaction();

        try {
            // 4a. Simpan data aktivitas utama
            $activity = Activity::create([
                'user_id'              => $user->id,
                'jenis_olahraga'       => $validated['jenis_olahraga'],
                'durasi_detik'         => $validated['durasi_detik'],
                'jarak_km'             => $validated['jarak_km'] ?? null,
                'kalori'               => $kaloriTerhitung,                // Hasil kalkulasi MET
                'pace'                 => $validated['pace'] ?? null,
                'kecepatan_kmh'        => $validated['kecepatan_kmh'] ?? null,
                'kecepatan_rata2_kmh'  => $validated['kecepatan_rata2_kmh'] ?? null,
                'kecepatan_maks_kmh'   => $validated['kecepatan_maks_kmh'] ?? null,
                'langkah'              => $validated['langkah'] ?? null,
                'heart_rate_bpm'       => $validated['heart_rate_bpm'] ?? null,
                'kadens_spm'           => $validated['kadens_spm'] ?? null,
                'kadens_rpm'           => $validated['kadens_rpm'] ?? null,
                'elevasi_naik_m'       => $validated['elevasi_naik_m'] ?? null,
                'altitud_m'            => $validated['altitud_m'] ?? null,
                'is_outdoor'           => $isOutdoor,
                'catatan'              => $validated['catatan'] ?? null,
            ]);

            // 4b. Simpan koordinat GPS jika olahraga outdoor DAN ada data koordinat
            if ($isOutdoor && !empty($validated['koordinat'])) {
                $coordinatesData = [];

                foreach ($validated['koordinat'] as $index => $coord) {
                    $coordinatesData[] = [
                        'activity_id' => $activity->id,
                        'latitude'    => $coord['latitude'],
                        'longitude'   => $coord['longitude'],
                        'altitude'    => $coord['altitude'] ?? null,
                        'urutan'      => $index,                          // Urutan sesuai index array
                        'recorded_at' => $coord['recorded_at'] ?? now(),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }

                // Bulk insert (lebih efisien daripada insert satu per satu)
                ActivityCoordinate::insert($coordinatesData);
            }

            // Semua berhasil → commit transaksi
            DB::commit();

            // Load relasi coordinates untuk response
            $activity->load('coordinates');

            return response()->json([
                'success' => true,
                'message' => 'Aktivitas berhasil disimpan! 🎉',
                'data'    => $activity,
            ], 201); // HTTP 201 = Created

        } catch (\Exception $e) {
            // Jika ada error → rollback semua perubahan database
            DB::rollBack();

            // Log error untuk debugging (tidak ditampilkan ke user)
            Log::error('Gagal menyimpan aktivitas: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.',
            ], 500); // HTTP 500 = Internal Server Error
        }
    }

    /**
     * Hitung estimasi kalori terbakar menggunakan rumus MET.
     *
     * Rumus: Kalori = MET × Berat_Badan(kg) × Durasi(jam)
     *
     * Contoh perhitungan:
     * - Berlari outdoor (MET=9.8) selama 30 menit (0.5 jam) dengan BB 70 kg
     * - Kalori = 9.8 × 70 × 0.5 = 343 kkal
     *
     * @param string $jenisOlahraga  Kunci enum jenis olahraga
     * @param int    $durasiDetik    Durasi aktivitas dalam detik
     * @param float|null $beratBadan Berat badan pengguna dalam kg
     * @return float Estimasi kalori terbakar (dibulatkan 1 desimal)
     */
    private function hitungKalori(string $jenisOlahraga, int $durasiDetik, ?float $beratBadan): float
    {
        // Ambil nilai MET dari konstanta, default 4.0 jika tidak ditemukan
        $met = self::MET_VALUES[$jenisOlahraga] ?? 4.0;

        // Jika berat badan belum diisi, gunakan asumsi 65 kg (rata-rata orang Indonesia)
        $berat = $beratBadan ?? 65.0;

        // Konversi durasi dari detik ke jam (1 jam = 3600 detik)
        $durasiJam = $durasiDetik / 3600;

        // Rumus MET: Kalori = MET × Berat (kg) × Durasi (jam)
        $kalori = $met * $berat * $durasiJam;

        // Bulatkan ke 1 desimal agar rapi
        return round($kalori, 1);
    }
}
