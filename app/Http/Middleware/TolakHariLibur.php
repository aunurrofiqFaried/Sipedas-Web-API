<?php

namespace App\Http\Middleware;

use App\Models\KalenderAkademik;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TolakHariLibur
{
    public function handle(Request $request, Closure $next): Response
    {
        $libur = KalenderAkademik::liburPada(now());

        if ($libur) {
            return response()->json([
                'message' => "Hari ini libur: {$libur->judul}. Fitur ini tidak tersedia pada hari libur.",
            ], 422);
        }

        return $next($request);
    }
}