<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    // GET /api/pengumuman
    public function index(Request $request): JsonResponse
    {
        $terakhirDibaca = (int) $request->user()->pengumuman_dibaca_id;

        $daftar = Pengumuman::berlaku()
            ->with('pembuat:id,name')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json([
            'belum_dibaca' => Pengumuman::berlaku()->where('id', '>', $terakhirDibaca)->count(),
            'terakhir_dibaca_id' => $terakhirDibaca,
            'data' => $daftar->map(fn (Pengumuman $p) => [
                'id' => $p->id,
                'judul' => $p->judul,
                'isi' => $p->isi,
                'tingkat' => $p->tingkat,
                'tanggal' => $p->created_at->format('Y-m-d H:i:s'),
                'berlaku_sampai' => $p->berlaku_sampai?->toDateString(),
                'pembuat' => $p->pembuat?->name,
            ]),
        ]);
    }

    // POST /api/pengumuman/dibaca
    public function dibaca(Request $request): JsonResponse
    {
        $user = $request->user();
        $terbaru = (int) Pengumuman::berlaku()->max('id');

        if ($terbaru > (int) $user->pengumuman_dibaca_id) {
            $user->forceFill(['pengumuman_dibaca_id' => $terbaru])->save();
        }

        return response()->json(['message' => 'Ditandai sudah dibaca.']);
    }
}