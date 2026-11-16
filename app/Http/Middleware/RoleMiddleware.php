<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Pemakaian di route: ->middleware('role:admin,kesiswaan')
     * Admin selalu lolos.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Tidak terautentikasi.'], 401);
        }

        if ($user->punyaPeran('admin', ...$roles)) {
            return $next($request);
        }

        return response()->json(['message' => 'Anda tidak memiliki akses.'], 403);
    }
}
