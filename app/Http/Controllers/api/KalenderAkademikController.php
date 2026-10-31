<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use App\Models\TahunAjaran;
use Illuminate\Http\JsonResponse;

class KalenderAkademikController extends Controller
{
    // GET /api/kalender-akademik
    // Semua acara yang beririsan dengan tahun ajaran aktif
    public function index(): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $data = KalenderAkademik::where('tanggal_selesai', '>=', $ta->tanggal_mulai->toDateString())
            ->where('tanggal_mulai', '<=', $ta->tanggal_selesai->toDateString())
            ->orderBy('tanggal_mulai')
            ->get()
            ->map(fn (KalenderAkademik $e) => [
                'id' => $e->id,
                'judul' => $e->judul,
                'jenis' => $e->jenis,
                'tanggal_mulai' => $e->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $e->tanggal_selesai->toDateString(),
                'keterangan' => $e->keterangan,
            ]);

        return response()->json(['data' => $data]);
    }
}