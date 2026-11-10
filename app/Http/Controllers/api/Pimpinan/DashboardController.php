<?php

namespace App\Http\Controllers\Api\Pimpinan;

use App\Http\Controllers\Controller;
use App\Support\DashboardPimpinan;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    // GET /api/pimpinan/dashboard
    public function index(): JsonResponse
    {
        return response()->json(DashboardPimpinan::susun());
    }
}