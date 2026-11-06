<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisRemisi;
use App\Models\RemisiSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RemisiController extends Controller
{
    // GET /api/remisi/jenis
    public function jenis(): JsonResponse
    {
        $data = JenisRemisi::where('is_aktif', true)
            ->orderBy('poin')
            ->get(['id', 'nama', 'poin', 'keterangan']);

        return response()->json(['data' => $data]);
    }

    // POST /api/remisi
    public function store(Request $request): JsonResponse
    {
        $ta = TahunAjaran::aktif() ?? abort(422, 'Tahun ajaran aktif belum diatur.');

        $data = $request->validate([
            'siswa_id' => ['required', 'integer', 'exists:siswa,id'],
            'jenis_remisi_id' => [
                'required', 'integer',
                Rule::exists('jenis_remisi', 'id')->where('is_aktif', true),
            ],
            'tanggal' => [
                'nullable', 'date',
                'before_or_equal:today',
                'after_or_equal:' . $ta->tanggal_mulai->toDateString(),
            ],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $siswa = Siswa::findOrFail($data['siswa_id']);

        $terdaftar = DB::table('kelas_siswa')
            ->where('siswa_id', $siswa->id)
            ->where('tahun_ajaran_id', $ta->id)
            ->exists();
        abort_unless($terdaftar, 422, 'Siswa tidak terdaftar di tahun ajaran aktif.');

        $jenis = JenisRemisi::findOrFail($data['jenis_remisi_id']);

        return DB::transaction(function () use ($request, $siswa, $jenis, $ta, $data) {
            // kunci baris siswa supaya dua pemberian bersamaan tidak membuat poin negatif
            Siswa::whereKey($siswa->id)->lockForUpdate()->first();

            $sebelum = $siswa->totalPoin($ta->id);

            abort_if(
                $jenis->poin > $sebelum,
                422,
                "Remisi {$jenis->poin} poin melebihi poin aktif siswa saat ini ({$sebelum})."
            );

            RemisiSiswa::create([
                'siswa_id' => $siswa->id,
                'tahun_ajaran_id' => $ta->id,
                'jenis_remisi_id' => $jenis->id,
                'poin' => $jenis->poin, // snapshot
                'tanggal' => $data['tanggal'] ?? now()->toDateString(),
                'keterangan' => $data['keterangan'] ?? null,
                'dicatat_oleh' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Remisi diberikan.',
                'poin_dikurangi' => $jenis->poin,
                'total_poin_sebelum' => $sebelum,
                'total_poin_sekarang' => $sebelum - $jenis->poin,
            ], 201);
        });
    }
}