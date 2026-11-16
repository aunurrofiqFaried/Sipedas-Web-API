<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Untuk menu yang boleh dibuka pimpinan (semua data) maupun kaprodi (data jurusannya saja).
// Penyaringan per jurusan dilakukan di controller lewat LingkupJurusan.
class PimpinanAtauKaprodi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Tidak terautentikasi.'], 401);
        }

        if ($user->isPimpinan() || $user->isKaprodi()) {
            return $next($request);
        }

        return response()->json(['message' => 'Anda tidak memiliki akses.'], 403);
    }
}