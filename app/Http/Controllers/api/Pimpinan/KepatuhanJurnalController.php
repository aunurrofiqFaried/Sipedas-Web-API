<?php

namespace App\Http\Controllers\Api\Pimpinan;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\KepatuhanJurnal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class KepatuhanJurnalController extends Controller
{
    // GET /api/pimpinan/kepatuhan-jurnal?dari=2026-11-01&sampai=2026-11-30
    // Tanpa dari dan sampai: seluruh tahun ajaran berjalan
    public function index(Request $request): JsonResponse
    {
        [$dari, $sampai] = $this->rentang($request);

        $hasil = KepatuhanJurnal::hitung($dari, $sampai);
        unset($hasil['terlewat']);

        return response()->json($hasil);
    }

    // GET /api/pimpinan/kepatuhan-jurnal/{guru}?dari=...&sampai=...
    public function show(Request $request, User $guru): JsonResponse
    {
        [$dari, $sampai] = $this->rentang($request);

        $hasil = KepatuhanJurnal::hitung($dari, $sampai, $guru->id);
        $baris = $hasil['guru'][0] ?? null;

        return response()->json([
            'guru' => ['id' => $guru->id, 'nama' => $guru->name, 'nip' => $guru->nip],
            'periode' => $hasil['periode'],
            'ringkasan' => $baris ?? [
                'sesi_per_minggu' => 0, 'seharusnya' => 0, 'terisi' => 0, 'terlewat' => 0,
                'persen' => null, 'status' => null, 'terakhir_isi' => null,
            ],
            'terlewat' => $hasil['terlewat'],
        ]);
    }

    private function rentang(Request $request): array
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $dari = Carbon::parse($data['dari'] ?? $ta->tanggal_mulai->toDateString())->startOfDay();
        $sampai = Carbon::parse($data['sampai'] ?? $ta->tanggal_selesai->toDateString())->startOfDay();

        abort_if($sampai->lt($dari), 422, 'Tanggal akhir tidak boleh lebih awal dari tanggal awal.');
        abort_if($dari->diffInDays($sampai) > 400, 422, 'Rentang tanggal maksimal sekitar satu tahun.');

        return [$dari, $sampai];
    }
}