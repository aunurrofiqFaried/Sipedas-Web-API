<?php

namespace App\Http\Controllers\Api\Pimpinan;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengumumanKelolaController extends Controller
{
    // GET /api/pimpinan/pengumuman
    // Semua pengumuman, termasuk yang dinonaktifkan atau sudah kedaluwarsa
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $daftar = Pengumuman::with('pembuat:id,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $daftar->map(fn (Pengumuman $p) => $this->bentuk($p, $user)),
        ]);
    }

    // POST /api/pimpinan/pengumuman
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->aturan(baru: true));

        $pengumuman = Pengumuman::create([
            'judul' => $data['judul'],
            'isi' => $data['isi'],
            'tingkat' => $data['tingkat'],
            'berlaku_sampai' => $data['berlaku_sampai'] ?? null,
            'is_aktif' => $data['is_aktif'] ?? true,
            'dibuat_oleh' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Pengumuman diterbitkan.',
            'data' => $this->bentuk($pengumuman->load('pembuat:id,name'), $request->user()),
        ], 201);
    }

    // PUT /api/pimpinan/pengumuman/{pengumuman}
    public function update(Request $request, Pengumuman $pengumuman): JsonResponse
    {
        $this->pastikanBoleh($request->user(), $pengumuman);

        $data = $request->validate($this->aturan(baru: false));

        $pengumuman->update([
            'judul' => $data['judul'],
            'isi' => $data['isi'],
            'tingkat' => $data['tingkat'],
            'berlaku_sampai' => $data['berlaku_sampai'] ?? null,
            'is_aktif' => $data['is_aktif'] ?? $pengumuman->is_aktif,
        ]);

        return response()->json([
            'message' => 'Pengumuman diperbarui.',
            'data' => $this->bentuk($pengumuman->load('pembuat:id,name'), $request->user()),
        ]);
    }

    // DELETE /api/pimpinan/pengumuman/{pengumuman}
    public function destroy(Request $request, Pengumuman $pengumuman): JsonResponse
    {
        $this->pastikanBoleh($request->user(), $pengumuman);

        $pengumuman->delete();

        return response()->json(['message' => 'Pengumuman dihapus.']);
    }

    private function aturan(bool $baru): array
    {
        return [
            'judul' => ['required', 'string', 'max:150'],
            'isi' => ['required', 'string', 'max:5000'],
            'tingkat' => ['required', Rule::in(array_keys(Pengumuman::TINGKAT))],
            // Saat membuat, tanggal berlaku tidak boleh di masa lalu.
            // Saat mengubah dibiarkan, supaya pengumuman lama tetap bisa diedit.
            'berlaku_sampai' => array_values(array_filter([
                'nullable',
                'date',
                $baru ? 'after_or_equal:today' : null,
            ])),
            'is_aktif' => ['sometimes', 'boolean'],
        ];
    }

    private function pastikanBoleh(User $user, Pengumuman $pengumuman): void
    {
        abort_unless(
            $pengumuman->dapatDiubahOleh($user),
            403,
            'Anda hanya dapat mengubah pengumuman buatan sendiri.'
        );
    }

    private function bentuk(Pengumuman $p, User $user): array
    {
        return [
            'id' => $p->id,
            'judul' => $p->judul,
            'isi' => $p->isi,
            'tingkat' => $p->tingkat,
            'tanggal' => $p->created_at->format('Y-m-d H:i:s'),
            'berlaku_sampai' => $p->berlaku_sampai?->toDateString(),
            'is_aktif' => $p->is_aktif,
            'kedaluwarsa' => (bool) $p->berlaku_sampai?->isBefore(today()),
            'pembuat' => $p->pembuat?->name,
            'dapat_diubah' => $p->dapatDiubahOleh($user),
        ];
    }
}